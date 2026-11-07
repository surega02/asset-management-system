<?php

namespace Tests\Feature;

use App\Actions\Imports\AssetImportBatches;
use App\Jobs\ProcessAssetImport;
use App\Queries\Assets;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\Support\AssignmentFixture;
use Tests\Support\OrganizationFixtures as Fixture;

class AssetImportConcurrencyTest extends TestCase
{
    public function test_duplicate_database_queue_deliveries_commit_the_same_import_row_once(): void
    {
        if (getenv('AMS_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Set AMS_TEST_MYSQL=1 with a migrated MySQL test database.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
        (new IdentitySeeder)->run();
        $tree = Fixture::tree();
        $actor = Fixture::actor($tree, 'branch', [Assets::VIEW, Assets::IMPORT, Assets::CREATE]);
        $type = AssignmentFixture::type();
        $location = $tree['location'];
        $queue = 'asset-import-race-'.$tree['code'];
        $batch = null;
        $workers = [];
        try {
            $imports = app(AssetImportBatches::class);
            $batch = $imports->upload($actor, UploadedFile::fake()->createWithContent('race.csv',
                "asset_code,name,category_id,asset_type_id,physical_location_id,assignee,purchase_date,acquisition_cost\n"
                .",Concurrent import,{$type->category_id},{$type->id},{$location},location:{$location},2026-01-15,10.00\n"));
            $queueManager = Queue::getFacadeRoot();
            Queue::fake([ProcessAssetImport::class]);
            try {
                $imports->decide($actor, $batch, [], true);
            } finally {
                Queue::swap($queueManager);
            }
            Queue::connection('database')->pushOn($queue, new ProcessAssetImport($batch));
            Queue::connection('database')->pushOn($queue, new ProcessAssetImport($batch));

            $mysql = config('database.connections.mysql');
            $env = [
                'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $mysql['host'], 'DB_PORT' => (string) $mysql['port'], 'DB_DATABASE' => $mysql['database'],
                'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => (string) $mysql['password'], 'CACHE_STORE' => 'array',
            ];
            DB::beginTransaction();
            DB::table('import_batches')->where('id', $batch)->lockForUpdate()->first();
            for ($i = 0; $i < 2; $i++) {
                $worker = new Process([PHP_BINARY, 'artisan', 'queue:work', 'database', '--once', '--queue='.$queue, '--tries=1'],
                    base_path(), $env, timeout: 20);
                $worker->start();
                $workers[] = $worker;
            }

            $deadline = microtime(true) + 8;
            do {
                $waiting = DB::table('information_schema.PROCESSLIST')->where('DB', $mysql['database'])
                    ->where('INFO', 'like', 'select % from `import_batches`%for update')->count();
                if ($waiting === 2 || ! $workers[0]->isRunning() || ! $workers[1]->isRunning()) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, implode("\n", array_map(fn (Process $worker) => $worker->getErrorOutput().$worker->getOutput(), $workers)));
            DB::commit();

            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            }
            $row = DB::table('import_rows')->where('import_batch_id', $batch)->sole();
            $this->assertSame('succeeded', $row->status);
            $this->assertSame(1, $row->attempt_count);
            $this->assertSame(1, DB::table('assets')->where('physical_location_id', $location)->count());
            $this->assertSame(1, DB::table('asset_assignments')->where('asset_id', $row->asset_id)->whereNull('ended_at')->count());
            $this->assertSame(1, DB::table('audit_logs')->where('asset_id', $row->asset_id)->where('event', 'asset.imported')->count());
            $this->assertSame(0, DB::table('jobs')->where('queue', $queue)->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop(1);
            }
            DB::table('jobs')->where('queue', $queue)->delete();
            DB::table('failed_jobs')->where('queue', $queue)->delete();
            if ($batch !== null) {
                DB::table('import_batches')->where('id', $batch)->delete();
            }
            AssignmentFixture::clean($type);
            DB::table('audit_logs')->where('actor_id', $actor->id)->delete();
            DB::table('model_has_permissions')->where('model_id', $actor->id)->where('model_type', $actor->getMorphClass())->delete();
            DB::table('user_data_scopes')->where('user_id', $actor->id)->delete();
            DB::table('users')->where('id', $actor->id)->delete();
            DB::table('employees')->where('id', $actor->employee_id)->delete();
            foreach (['locations' => 'location', 'departments' => 'department', 'divisions' => 'division', 'branches' => 'branch', 'positions' => 'position'] as $table => $key) {
                DB::table($table)->where('id', $tree[$key])->delete();
            }
        }
    }
}
