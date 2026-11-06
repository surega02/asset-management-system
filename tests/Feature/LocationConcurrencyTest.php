<?php

namespace Tests\Feature;

use App\Queries\OrganizationMasters;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\OrganizationFixtures as Fixture;

class LocationConcurrencyTest extends TestCase
{
    public function test_mst_001_only_one_of_two_concurrent_inverse_parent_changes_can_commit(): void
    {
        if (getenv('AMS_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Set AMS_TEST_MYSQL=1 with a migrated MySQL test database.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));

        (new IdentitySeeder)->run();
        [$tree, $left, $right, $workers] = DB::transaction(function (): array {
            $tree = Fixture::tree();
            $left = $tree['location'];
            $right = Fixture::insert('locations', 'RIGHT', 'Other location', ['branch_id' => $tree['branch']]);
            $permissions = [OrganizationMasters::VIEW, OrganizationMasters::MANAGE];
            $workers = [Fixture::actor($tree, 'branch', $permissions), Fixture::actor($tree, 'branch', $permissions)];

            return [$tree, $left, $right, $workers];
        });

        $processes = [];
        try {
            $mysql = config('database.connections.mysql');
            $env = [
                'APP_ENV' => 'testing', 'AMS_TEST_MYSQL' => '1', 'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $mysql['host'], 'DB_PORT' => (string) $mysql['port'], 'DB_DATABASE' => $mysql['database'],
                'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => (string) $mysql['password'], 'CACHE_STORE' => 'array',
            ];
            DB::beginTransaction();
            DB::table('branches')->where('id', $tree['branch'])->lockForUpdate()->first();
            foreach ([[$left, $right], [$right, $left]] as $index => [$location, $parent]) {
                $script = 'require "vendor/autoload.php"; Tests\Support\LocationReparentWorker::run(...array_map("intval", array_slice($argv, 1)));';
                $process = new Process([PHP_BINARY, '-r', $script, (string) $workers[$index]->id, (string) $location, (string) $parent], base_path(), $env, timeout: 15);
                $process->start();
                $processes[] = $process;
            }

            // Both real writes must be waiting on the same branch before either can proceed.
            $deadline = microtime(true) + 8;
            do {
                $waiting = DB::table('information_schema.PROCESSLIST')->where('DB', $mysql['database'])
                    ->where('INFO', 'like', 'select % from `branches`%for update')->count();
                if ($waiting === 2 || ! $processes[0]->isRunning() || ! $processes[1]->isRunning()) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, implode("\n", array_map(fn (Process $process) => $process->getErrorOutput(), $processes)));
            DB::commit();

            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $outcomes[] = $process->getOutput();
            }
            $this->assertEqualsCanonicalizing(['saved', 'cycle'], $outcomes);
            $this->assertSame(1, DB::table('locations')->whereIn('id', [$left, $right])->whereNotNull('parent_id')->count());
            $this->assertSame(1, DB::table('audit_logs')->whereIn('actor_id', array_column($workers, 'id'))->where('event', 'master.updated')->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop(1);
            }
            $this->cleanFixtures($tree, $workers);
        }
    }

    private function cleanFixtures(array $tree, array $workers): void
    {
        DB::transaction(function () use ($tree, $workers): void {
            $ids = array_column($workers, 'id');
            DB::table('audit_logs')->whereIn('actor_id', $ids)->delete();
            DB::table('user_data_scopes')->whereIn('user_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('model_id', $ids)->where('model_type', $workers[0]->getMorphClass())->delete();
            DB::table('users')->whereIn('id', $ids)->delete();
            DB::table('employees')->whereIn('id', array_column($workers, 'employee_id'))->delete();
            DB::table('locations')->where('branch_id', $tree['branch'])->update(['parent_id' => null]);
            DB::table('locations')->where('branch_id', $tree['branch'])->delete();
            foreach (['departments' => 'department', 'divisions' => 'division', 'branches' => 'branch', 'positions' => 'position'] as $table => $key) {
                DB::table($table)->where('id', $tree[$key])->delete();
            }
        });
    }
}
