<?php

namespace Tests\Release;

use App\Models\User;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\AssignmentFixture;
use Tests\Support\OrganizationFixtures as Fixture;

class DeadlockRetryTest extends TestCase
{
    public function test_registration_retries_a_real_innodb_deadlock_without_duplicate_mutations(): void
    {
        $this->assertSame('1', getenv('AMS_TEST_MYSQL'));
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
        (new IdentitySeeder)->run();
        $tree = Fixture::tree();
        $type = AssignmentFixture::type();
        $actor = Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN);
        $branch = Fixture::insert('branches', 'DEADLOCK-'.$tree['code'], 'Synthetic');
        $barrier = tempnam(sys_get_temp_dir(), 'ams-deadlock-');
        $mysql = config('database.connections.mysql');
        $code = 'require "vendor/autoload.php"; Tests\\Support\\DeadlockWorker::run('.$branch.', '.var_export($barrier, true).');';
        $process = new Process([PHP_BINARY, '-r', $code], base_path(), [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $mysql['host'], 'DB_PORT' => (string) $mysql['port'], 'DB_DATABASE' => $mysql['database'],
            'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => (string) $mysql['password'], 'CACHE_STORE' => 'array',
        ], timeout: 20);
        $triggered = false;
        $attempts = 0;
        try {
            $number = DB::table('asset_number_sequences')->where('key', 'AST')->value('next_number');
            $process->start();
            $deadline = microtime(true) + 10;
            while (! is_file($barrier.'.ready') && $process->isRunning() && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($barrier.'.ready', $process->getErrorOutput());
            DB::listen(function (QueryExecuted $query) use ($branch, $barrier, &$triggered, &$attempts): void {
                if (str_starts_with($query->sql, 'insert into `assets`')) {
                    $attempts++;
                    if (! $triggered) {
                        $triggered = true;
                        file_put_contents($barrier.'.inserted', 'inserted');
                        DB::table('branches')->where('id', $branch)->lockForUpdate()->sole();
                    }
                }
            });
            $id = AssignmentFixture::assign($actor, $actor->employee_id, $tree['location'], $type->id);
            $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            $this->assertSame('deadlock-released', $process->getOutput());
            $this->assertSame(2, $attempts, 'The original registration must be retried after InnoDB rollback.');
            $this->assertSame(sprintf('AST-%06d', $number), DB::table('assets')->where('id', $id)->value('asset_code'));
            $this->assertSame(1, DB::table('assets')->where('asset_type_id', $type->id)->count());
            $this->assertSame(1, DB::table('asset_assignments')->where('asset_id', $id)->whereNull('ended_at')->count());
            $this->assertSame(1, DB::table('audit_logs')->where('asset_id', $id)->where('event', 'asset.registered')->count());
            $this->assertSame($number + 1, DB::table('asset_number_sequences')->where('key', 'AST')->value('next_number'));
        } finally {
            $process->stop(1);
            AssignmentFixture::clean($type);
            DB::table('audit_logs')->where('actor_id', $actor->id)->delete();
            DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $actor->id)->delete();
            DB::table('users')->where('id', $actor->id)->delete();
            DB::table('employees')->where('id', $actor->employee_id)->delete();
            foreach (['locations' => 'location', 'departments' => 'department', 'divisions' => 'division', 'branches' => 'branch', 'positions' => 'position'] as $table => $key) {
                DB::table($table)->where('id', $tree[$key])->delete();
            }
            DB::table('branches')->where('id', $branch)->delete();
            foreach ([$barrier, $barrier.'.ready', $barrier.'.inserted'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
