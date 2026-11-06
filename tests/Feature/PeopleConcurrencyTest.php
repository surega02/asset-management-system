<?php

namespace Tests\Feature;

use App\Actions\Assets\ReassignAsset;
use App\Actions\Employees\ManageEmployee;
use App\Models\Asset;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\Support\AssignmentFixture;
use Tests\Support\OrganizationFixtures as Fixture;
use Tests\Support\PeopleRaceWorker;

class PeopleConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('AMS_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Set AMS_TEST_MYSQL=1 with a migrated MySQL test database.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
        (new IdentitySeeder)->run();
    }

    public function test_t008_parallel_same_kind_appointments_only_one_can_commit(): void
    {
        $tree = Fixture::tree();
        $permissions = ['appointments.view', 'appointments.manage'];
        $actors = [Fixture::actor($tree, 'department', $permissions), Fixture::actor($tree, 'department', $permissions)];
        $key = ['role_kind' => 'hod', 'scope_type' => 'department', 'scope_id' => $tree['department']];
        $scopeId = DB::table('structural_appointment_scope_locks')->insertGetId($key);
        $processes = [];
        try {
            DB::beginTransaction();
            DB::table('structural_appointment_scope_locks')->where('id', $scopeId)->lockForUpdate()->first();
            foreach ($actors as $actor) {
                $processes[] = $this->start('appointment', $actor, $actor->employee_id, $tree['department']);
            }
            $this->waitForLocks($processes, 'insert into '.chr(96).'structural_appointment_scope_locks'.chr(96).'%');
            DB::commit();

            $outcomes = array_map(fn (Process $process) => $this->workerResult($process), $processes);
            $this->assertEqualsCanonicalizing(['saved', 'overlap'], $outcomes);
            $this->assertSame(1, DB::table('structural_appointments')->where('department_id', $tree['department'])->count());
            $this->assertSame(1, DB::table('audit_logs')->whereIn('actor_id', array_column($actors, 'id'))->where('event', 'appointment.created')->count());
        } finally {
            $this->finish($processes);
            DB::table('structural_appointments')->where('department_id', $tree['department'])->delete();
            DB::table('structural_appointment_scope_locks')->where('id', $scopeId)->delete();
            $this->clean($tree, $actors);
        }
    }

    public function test_emp_002_and_emp_003_assignment_and_deactivation_cannot_both_win(): void
    {
        $type = AssignmentFixture::type();
        $source = Fixture::tree();
        $dest = Fixture::tree();
        $actor = Fixture::actor($source)->assignRole(IdentityAccess::SUPER_ADMIN);
        $registrar = Fixture::actor($source)->assignRole(IdentityAccess::SUPER_ADMIN);
        $holder = Fixture::actor($source);
        $second = Fixture::actor($source);
        $processes = [];
        try {
            DB::beginTransaction();
            $asset = AssignmentFixture::assign($registrar, $holder->employee_id, $source['location'], $type->id);
            $processes[] = $this->start('deactivate', $actor, $holder->employee_id, 0);
            $this->waitForLocks([$processes[0]], 'select % from '.chr(96).'employees'.chr(96).'%for update');
            DB::commit();
            $this->assertSame('has_assets', $this->workerResult($processes[0]));
            $this->assertSame('active', $holder->employee->fresh()->employment_status);

            $this->actingAs($actor)->withSession(['auth.version' => $actor->auth_version])
                ->get('/employees/'.$holder->employee_id)->assertOk()->assertSee(__('people.assets_warning', ['count' => 1]))->assertSee('Aset sintetis');
            $count = app(ManageEmployee::class)->transfer($actor, $holder->employee, [
                'department_id' => $dest['department'], 'home_location_id' => $dest['location'], 'reason' => 'Rotasi penempatan',
            ]);
            $this->assertSame(1, $count);
            $this->assertDatabaseHas('assets', ['id' => $asset, 'physical_location_id' => $source['location'], 'is_active' => 1]);
            $this->assertDatabaseHas('asset_assignments', ['asset_id' => $asset, 'employee_id' => $holder->employee_id, 'ended_at' => null]);

            DB::beginTransaction();
            app(ManageEmployee::class)->update($actor, $second->employee, PeopleRaceWorker::inactiveProfile($second->employee));
            $processes[] = $this->start('assign', $registrar, $second->employee_id, $source['location'], $type->id);
            $this->waitForLocks([$processes[1]], 'select % from '.chr(96).'employees'.chr(96).'%for update');
            DB::commit();
            $this->assertSame('inactive_employee', $this->workerResult($processes[1]));
            $this->assertSame('inactive', $second->employee->fresh()->employment_status);
            $this->assertDatabaseMissing('asset_assignments', ['employee_id' => $second->employee_id]);

            try {
                app(ManageEmployee::class)->update($actor, $holder->employee, PeopleRaceWorker::inactiveProfile($holder->employee->fresh()));
                $this->fail('An active assignment did not block deactivation.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('employment_status', $exception->errors());
            }
        } finally {
            $this->finish($processes);
            AssignmentFixture::clean($type);
            DB::table('employee_movements')->where('employee_id', $holder->employee_id)->delete();
            $this->clean($source, [$actor, $registrar, $holder, $second]);
            $this->clean($dest, []);
        }
    }

    public function test_t001_parallel_registration_uses_unique_codes_and_one_assignment_per_asset(): void
    {
        $tree = Fixture::tree();
        $type = AssignmentFixture::type();
        $actors = [
            Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN),
            Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN),
        ];
        $processes = [];
        try {
            DB::beginTransaction();
            $number = DB::table('asset_number_sequences')->where('key', 'AST')->lockForUpdate()->sole()->next_number;
            foreach ($actors as $actor) {
                $processes[] = $this->start('assign', $actor, $actor->employee_id, $tree['location'], $type->id);
            }
            $this->waitForLocks($processes, 'select % from '.chr(96).'asset_number_sequences'.chr(96).'%for update');
            DB::commit();
            foreach ($processes as $process) {
                $this->assertSame('saved', $this->workerResult($process));
            }
            $assets = DB::table('assets')->where('asset_type_id', $type->id)->orderBy('id')->get(['id', 'asset_code', 'public_token']);
            $this->assertSame([sprintf('AST-%06d', $number), sprintf('AST-%06d', $number + 1)], $assets->pluck('asset_code')->all());
            $this->assertCount(2, $assets->pluck('public_token')->unique());
            $this->assertEqualsCanonicalizing($assets->pluck('id')->all(), DB::table('asset_assignments')
                ->whereIn('asset_id', $assets->pluck('id'))->whereNull('ended_at')->pluck('active_asset_id')->all());
        } finally {
            $this->finish($processes);
            AssignmentFixture::clean($type);
            $this->clean($tree, $actors);
        }
    }

    public function test_t003_parallel_reassignments_close_only_one_old_assignment(): void
    {
        $tree = Fixture::tree();
        $type = AssignmentFixture::type();
        $owner = Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN);
        $operators = [
            Fixture::actor($tree, 'branch', [Assets::VIEW, Assets::REASSIGN]),
            Fixture::actor($tree, 'branch', [Assets::VIEW, Assets::REASSIGN]),
        ];
        $asset = Asset::query()->findOrFail(AssignmentFixture::assign($owner, $owner->employee_id, $tree['location'], $type->id));
        $original = $asset->activeAssignment()->sole();
        $processes = [];
        try {
            DB::beginTransaction();
            Asset::query()->lockForUpdate()->findOrFail($asset->id);
            foreach ($operators as $operator) {
                $processes[] = $this->start('reassign', $operator, $operator->employee_id, $asset->id, $original->id);
            }
            $this->waitForLocks($processes, 'select % from '.chr(96).'assets'.chr(96).'%for update');
            DB::commit();

            $this->assertEqualsCanonicalizing(['saved', 'stale_assignment'],
                array_map(fn (Process $process) => $this->workerResult($process), $processes));
            $this->assertSame(2, $asset->assignments()->count());
            $this->assertEquals($original->fresh()->ended_at, $asset->activeAssignment()->sole()->started_at);
            $this->assertSame(1, DB::table('audit_logs')->where('asset_id', $asset->id)->where('event', 'asset.reassigned')->count());
        } finally {
            $this->finish($processes);
            AssignmentFixture::clean($type);
            $this->clean($tree, [$owner, ...$operators]);
        }
    }

    public function test_reassignment_rechecks_employee_scope_and_active_state_after_waiting_for_the_employee_lock(): void
    {
        $tree = Fixture::tree();
        $other = Fixture::tree();
        $type = AssignmentFixture::type();
        $admin = Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN);
        $operator = Fixture::actor($tree, 'branch', [Assets::VIEW, Assets::REASSIGN]);
        $moved = Fixture::actor($tree);
        $inactive = Fixture::actor($tree);
        $holder = Fixture::actor($tree);
        $asset = Asset::query()->findOrFail(AssignmentFixture::assign($admin, $admin->employee_id, $tree['location'], $type->id));
        $original = $asset->activeAssignment()->sole();
        $employeeLock = 'select % from '.chr(96).'employees'.chr(96).'%for update';
        $processes = [];
        try {
            DB::beginTransaction();
            app(ManageEmployee::class)->transfer($admin, $moved->employee, [
                'department_id' => $other['department'], 'home_location_id' => $other['location'], 'reason' => 'Parallel transfer',
            ]);
            $processes[] = $this->start('reassign', $operator, $moved->employee_id, $asset->id, $original->id);
            $this->waitForLocks([$processes[0]], $employeeLock);
            DB::commit();
            $this->assertSame('invalid_assignee', $this->workerResult($processes[0]));

            DB::beginTransaction();
            app(ManageEmployee::class)->update($admin, $inactive->employee, PeopleRaceWorker::inactiveProfile($inactive->employee));
            $processes[] = $this->start('reassign', $operator, $inactive->employee_id, $asset->id, $original->id);
            $this->waitForLocks([$processes[1]], $employeeLock);
            DB::commit();
            $this->assertSame('inactive_employee', $this->workerResult($processes[1]));
            $this->assertSame($original->id, $asset->activeAssignment()->sole()->id);

            DB::beginTransaction();
            app(ReassignAsset::class)($operator, $asset, [
                'employee_id' => $holder->employee_id, 'assignment_id' => $original->id, 'reason' => 'Parallel assignment',
            ]);
            $processes[] = $this->start('deactivate', $admin, $holder->employee_id, 0);
            $this->waitForLocks([$processes[2]], $employeeLock);
            DB::commit();
            $this->assertSame('has_assets', $this->workerResult($processes[2]));
            $this->assertSame($holder->employee_id, $asset->activeAssignment()->sole()->employee_id);
        } finally {
            $this->finish($processes);
            AssignmentFixture::clean($type);
            DB::table('employee_movements')->where('employee_id', $moved->employee_id)->delete();
            $this->clean($tree, [$admin, $operator, $moved, $inactive, $holder]);
            $this->clean($other, []);
        }
    }

    private function start(string $operation, User $actor, int $employee, int $target, int $typeId = 0): Process
    {
        $mysql = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'AMS_TEST_MYSQL' => '1', 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $mysql['host'], 'DB_PORT' => (string) $mysql['port'], 'DB_DATABASE' => $mysql['database'],
            'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => (string) $mysql['password'], 'CACHE_STORE' => 'array',
        ];
        $script = 'require "vendor/autoload.php"; Tests\Support\PeopleRaceWorker::run(...array_slice($argv, 1));';
        $process = new Process([PHP_BINARY, '-r', $script, $operation, (string) $actor->id, (string) $employee, (string) $target, (string) $typeId], base_path(), $env, timeout: 15);
        $process->start();

        return $process;
    }

    private function waitForLocks(array $processes, string $statement): void
    {
        $deadline = microtime(true) + 8;
        do {
            $waiting = DB::table('information_schema.PROCESSLIST')->where('DB', config('database.connections.mysql.database'))
                ->where('INFO', 'like', $statement)->count();
            if ($waiting === count($processes) || array_filter($processes, fn (Process $process) => ! $process->isRunning()) !== []) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->assertSame(count($processes), $waiting, implode("\n", array_map(fn (Process $process) => $process->getOutput().$process->getErrorOutput(), $processes)));
    }

    private function workerResult(Process $process): string
    {
        $this->assertSame(0, $process->wait(), $process->getErrorOutput());

        return $process->getOutput();
    }

    private function finish(array $processes): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($processes as $process) {
            $process->stop(1);
        }
    }

    private function clean(array $tree, array $users): void
    {
        if ($users !== []) {
            $ids = array_column($users, 'id');
            DB::table('audit_logs')->whereIn('actor_id', $ids)->delete();
            DB::table('user_data_scopes')->whereIn('user_id', $ids)->delete();
            foreach (['model_has_permissions', 'model_has_roles'] as $table) {
                DB::table($table)->where('model_type', User::class)->whereIn('model_id', $ids)->delete();
            }
            DB::table('users')->whereIn('id', $ids)->delete();
            DB::table('employees')->whereIn('id', array_column($users, 'employee_id'))->delete();
        }
        foreach (['locations' => 'location', 'departments' => 'department', 'divisions' => 'division', 'branches' => 'branch', 'positions' => 'position'] as $table => $key) {
            DB::table($table)->where('id', $tree[$key])->delete();
        }
    }
}
