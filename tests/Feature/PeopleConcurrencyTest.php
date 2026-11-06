<?php

namespace Tests\Feature;

use App\Actions\Employees\ManageEmployee;
use App\Models\User;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $this->assertFalse(Schema::hasTable('assets'));
        $this->assertFalse(Schema::hasTable('asset_assignments'));
        AssignmentFixture::migrate();
        $source = Fixture::tree();
        $dest = Fixture::tree();
        $actor = Fixture::actor($source)->assignRole(IdentityAccess::SUPER_ADMIN);
        $holder = Fixture::actor($source);
        $second = Fixture::actor($source);
        $processes = [];
        try {
            DB::beginTransaction();
            $asset = AssignmentFixture::assign($holder->employee_id, $source['location']);
            $processes[] = $this->start('deactivate', $actor, $holder->employee_id, 0);
            $this->waitForLocks([$processes[0]], 'select % from '.chr(96).'employees'.chr(96).'%for update');
            DB::commit();
            $this->assertSame('has_assets', $this->workerResult($processes[0]));
            $this->assertSame('active', $holder->employee->fresh()->employment_status);

            $this->actingAs($actor)->withSession(['auth.version' => $actor->auth_version])
                ->get('/employees/'.$holder->employee_id)->assertOk()->assertSee(__('people.assets_warning', ['count' => 1]))->assertDontSee('Aset sintetis');
            $count = app(ManageEmployee::class)->transfer($actor, $holder->employee, [
                'department_id' => $dest['department'], 'home_location_id' => $dest['location'], 'reason' => 'Rotasi penempatan',
            ]);
            $this->assertSame(1, $count);
            $this->assertDatabaseHas('assets', ['id' => $asset, 'physical_location_id' => $source['location'], 'is_active' => 1]);
            $this->assertDatabaseHas('asset_assignments', ['asset_id' => $asset, 'employee_id' => $holder->employee_id, 'ended_at' => null]);

            DB::beginTransaction();
            app(ManageEmployee::class)->update($actor, $second->employee, PeopleRaceWorker::inactiveProfile($second->employee));
            $processes[] = $this->start('assign', $actor, $second->employee_id, $source['location']);
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
            Schema::dropIfExists('asset_assignments');
            Schema::dropIfExists('assets');
            DB::table('employee_movements')->where('employee_id', $holder->employee_id)->delete();
            $this->clean($source, [$actor, $holder, $second]);
            $this->clean($dest, []);
        }
    }

    private function start(string $operation, User $actor, int $employee, int $target): Process
    {
        $mysql = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'AMS_TEST_MYSQL' => '1', 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $mysql['host'], 'DB_PORT' => (string) $mysql['port'], 'DB_DATABASE' => $mysql['database'],
            'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => (string) $mysql['password'], 'CACHE_STORE' => 'array',
        ];
        $script = 'require "vendor/autoload.php"; Tests\Support\PeopleRaceWorker::run(...array_slice($argv, 1));';
        $process = new Process([PHP_BINARY, '-r', $script, $operation, (string) $actor->id, (string) $employee, (string) $target], base_path(), $env, timeout: 15);
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
