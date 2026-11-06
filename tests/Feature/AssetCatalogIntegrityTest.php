<?php

namespace Tests\Feature;

use App\Actions\Assets\ManageAssetAttributes;
use App\Actions\Assets\ResolveAssetCatalog;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\User;
use App\Policies\AssetMasterPolicy;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\Support\OrganizationFixtures as Fixture;

class AssetCatalogIntegrityTest extends TestCase
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

    public function test_two_parallel_parent_changes_cannot_create_a_cycle(): void
    {
        $tree = Fixture::tree();
        $permissions = [AssetMasterPolicy::VIEW, AssetMasterPolicy::MANAGE];
        $actors = [Fixture::actor($tree, 'branch', $permissions), Fixture::actor($tree, 'branch', $permissions)];
        $left = AssetCategory::query()->create(['code' => 'LEFT-'.$tree['code'], 'name' => 'Left']);
        $right = AssetCategory::query()->create(['code' => 'RIGHT-'.$tree['code'], 'name' => 'Right']);
        $workers = [];

        try {
            DB::beginTransaction();
            DB::table('asset_catalog_defaults')->where('id', 1)->lockForUpdate()->first();
            $workers[] = $this->start($actors[0], $left->id, $right->id);
            $workers[] = $this->start($actors[1], $right->id, $left->id);
            $this->assertBothWaiting($workers);
            DB::commit();

            $outcomes = [];
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getOutput().$worker->getErrorOutput());
                $outcomes[] = $worker->getOutput();
            }
            $this->assertEqualsCanonicalizing(['saved', 'cycle'], $outcomes);
            $this->assertSame(1, AssetCategory::query()->whereKey([$left->id, $right->id])->whereNotNull('parent_id')->count());
            $this->assertSame(1, DB::table('audit_logs')->whereIn('actor_id', array_column($actors, 'id'))->where('event', 'asset-master.updated')->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop(1);
            }
            DB::table('asset_categories')->whereIn('id', [$left->id, $right->id])->update(['parent_id' => null]);
            DB::table('asset_categories')->whereIn('id', [$left->id, $right->id])->delete();
            $this->clean($tree, $actors);
        }
    }

    public function test_archived_attribute_version_remains_readable_through_a_persisted_value_fk(): void
    {
        $tree = Fixture::tree();
        $actor = Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN);
        $category = AssetCategory::query()->create(['code' => $tree['code'], 'name' => 'Equipment']);
        $type = AssetType::query()->create(['code' => $tree['code'], 'name' => 'Laptop', 'category_id' => $category->id]);
        $this->assertFalse(Schema::hasTable('asset_catalog_test_values'));
        Schema::create('asset_catalog_test_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_definition_id')->constrained()->restrictOnDelete();
            $table->json('value_json');
        });

        try {
            $action = app(ManageAssetAttributes::class);
            $input = ['label' => 'Color', 'data_type' => 'select', 'options' => "Black\nSilver", 'is_required' => 1, 'sort_order' => 0];
            $original = $action->save($actor, $type, null, ['key' => 'color'] + $input);
            $resolved = app(ResolveAssetCatalog::class)->resolve($category->id, $type->id, ['color' => 'Silver']);
            DB::table('asset_catalog_test_values')->insert([
                'attribute_definition_id' => $original->id, 'value_json' => json_encode($resolved['attributes'][$original->id], JSON_THROW_ON_ERROR),
            ]);
            $revised = $action->save($actor, $type, $original->id, ['options' => 'Black'] + $input);
            $action->archive($actor, $type, $revised->id);

            $historical = DB::table('asset_catalog_test_values')->join('attribute_definitions', 'attribute_definitions.id', '=', 'attribute_definition_id')
                ->sole(['attribute_definitions.*', 'value_json']);
            $this->assertSame($original->id, $historical->id);
            $this->assertSame('Silver', json_decode($historical->value_json, true));
            $this->assertSame(['Black', 'Silver'], json_decode($historical->allowed_options, true));
            $this->assertSame(0, $historical->is_active);
            try {
                DB::table('attribute_definitions')->where('id', $original->id)->delete();
                $this->fail('MySQL allowed deleting a definition with a persisted value.');
            } catch (QueryException $exception) {
                $this->assertSame(1451, (int) $exception->errorInfo[1]);
            }
        } finally {
            Schema::drop('asset_catalog_test_values');
            DB::table('asset_type_attributes')->where('asset_type_id', $type->id)->delete();
            DB::table('attribute_definitions')->where('asset_type_id', $type->id)->delete();
            DB::table('asset_types')->where('id', $type->id)->delete();
            DB::table('asset_categories')->where('id', $category->id)->delete();
            $this->clean($tree, [$actor]);
        }
    }

    private function start(User $actor, int $category, int $parent): Process
    {
        $mysql = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'AMS_TEST_MYSQL' => '1', 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $mysql['host'], 'DB_PORT' => (string) $mysql['port'], 'DB_DATABASE' => $mysql['database'],
            'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => (string) $mysql['password'], 'CACHE_STORE' => 'array',
        ];
        $script = 'require "vendor/autoload.php"; Tests\Support\AssetCategoryRaceWorker::run(...array_map("intval", array_slice($argv, 1)));';
        $process = new Process([PHP_BINARY, '-r', $script, (string) $actor->id, (string) $category, (string) $parent], base_path(), $env, timeout: 15);
        $process->start();

        return $process;
    }

    private function assertBothWaiting(array $workers): void
    {
        $deadline = microtime(true) + 8;
        do {
            $waiting = DB::table('information_schema.PROCESSLIST')->where('DB', config('database.connections.mysql.database'))
                ->where('INFO', 'like', 'select % from '.chr(96).'asset_catalog_defaults'.chr(96).'%for update')->count();
            if ($waiting === 2 || array_filter($workers, fn (Process $worker) => ! $worker->isRunning()) !== []) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->assertSame(2, $waiting, implode("\n", array_map(fn (Process $worker) => $worker->getOutput().$worker->getErrorOutput(), $workers)));
    }

    private function clean(array $tree, array $actors): void
    {
        $ids = array_column($actors, 'id');
        DB::table('audit_logs')->whereIn('actor_id', $ids)->delete();
        DB::table('user_data_scopes')->whereIn('user_id', $ids)->delete();
        foreach (['model_has_permissions', 'model_has_roles'] as $table) {
            DB::table($table)->where('model_type', User::class)->whereIn('model_id', $ids)->delete();
        }
        DB::table('users')->whereIn('id', $ids)->delete();
        DB::table('employees')->whereIn('id', array_column($actors, 'employee_id'))->delete();
        foreach (['locations' => 'location', 'departments' => 'department', 'divisions' => 'division', 'branches' => 'branch', 'positions' => 'position'] as $table => $key) {
            DB::table($table)->where('id', $tree[$key])->delete();
        }
    }
}
