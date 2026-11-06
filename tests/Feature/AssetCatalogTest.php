<?php

namespace Tests\Feature;

use App\Actions\Assets\ResolveAssetCatalog;
use App\Models\AssetCategory;
use App\Models\AssetCondition;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\AttributeDefinition;
use App\Models\User;
use App\Policies\AssetMasterPolicy;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;
use Tests\Support\OrganizationFixtures as Fixture;

class AssetCatalogTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private startMysqlTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        if (getenv('AMS_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Set AMS_TEST_MYSQL=1 with a migrated MySQL test database.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
        $this->startMysqlTransaction();
        (new IdentitySeeder)->run();
    }

    public function test_mst_001_category_hierarchy_rejects_cycles_and_active_descendant_archival(): void
    {
        $actor = Fixture::actor(Fixture::tree())->assignRole(IdentityAccess::SUPER_ADMIN);
        $this->as($actor)->get('/asset-catalog/categories/new')->assertOk();
        $this->post('/asset-catalog/categories', ['master_code' => 'EQUIP', 'name' => 'Equipment', 'is_active' => 1])->assertRedirect();
        $root = AssetCategory::query()->where('code', 'EQUIP')->sole();
        $this->post('/asset-catalog/categories', ['master_code' => 'COMP', 'name' => 'Computers', 'is_active' => 1, 'parent_id' => $root->id])->assertRedirect();
        $child = AssetCategory::query()->where('code', 'COMP')->sole();
        $this->post('/asset-catalog/types', ['master_code' => 'LAP', 'name' => 'Laptop', 'is_active' => 1, 'category_id' => $child->id])->assertRedirect();
        $this->get('/asset-catalog/types?q=LAP')->assertOk()->assertSee('Computers');

        foreach ([$root->id, $child->id] as $parent) {
            $this->from('/asset-catalog/categories/'.$root->id)->put('/asset-catalog/categories/'.$root->id,
                ['name' => $root->name, 'is_active' => 1, 'parent_id' => $parent])->assertSessionHasErrors('parent_id');
        }
        $this->assertNull($root->fresh()->parent_id);
        foreach ([$root, $child] as $category) {
            $this->put('/asset-catalog/categories/'.$category->id, ['name' => $category->name, 'is_active' => 0])->assertSessionHasErrors('is_active');
        }
        $this->delete('/asset-catalog/categories/'.$root->id)->assertStatus(405);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'subject_id' => $child->id, 'event' => 'asset-master.created']);
    }

    public function test_ast_001_seeded_status_and_condition_use_stable_codes_and_require_valid_replacements(): void
    {
        $tree = Fixture::tree();
        $actor = Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN);
        $manager = Fixture::actor($tree, 'branch', [AssetMasterPolicy::VIEW, AssetMasterPolicy::MANAGE]);
        $this->as($actor);
        $category = AssetCategory::query()->create(['code' => 'COMPUTER', 'name' => 'Computers']);
        $type = AssetType::query()->create(['category_id' => $category->id, 'code' => 'LAPTOP', 'name' => 'Laptop']);
        $available = AssetStatus::query()->where('code', 'AVAILABLE')->sole();
        $good = AssetCondition::query()->where('code', 'GOOD')->sole();
        $this->get('/asset-catalog/statuses')->assertOk()->assertSee('Tersedia')->assertSee(__('asset_catalog.default'));
        $this->get('/asset-catalog/conditions')->assertOk()->assertSee('Baik');
        $resolved = app(ResolveAssetCatalog::class)->resolve($category->id, $type->id, []);
        $this->assertSame($available->id, $resolved['status_id']);
        $this->assertSame($good->id, $resolved['condition_id']);

        $this->put('/asset-catalog/statuses/'.$available->id, ['name' => 'Tersedia', 'is_active' => 0])->assertSessionHasErrors('is_active');
        $this->put('/asset-catalog/conditions/'.$good->id, ['name' => 'Baik', 'is_active' => 0])->assertSessionHasErrors('is_active');
        $next = AssetStatus::query()->create(['code' => 'READY', 'name' => 'Siap', 'is_active' => false]);
        $this->post('/asset-catalog/statuses/'.$next->id.'/default')->assertSessionHasErrors('is_active');
        $this->put('/asset-catalog/statuses/'.$next->id, ['name' => 'Siap', 'is_active' => 1])->assertRedirect();
        $this->as($manager)->post('/asset-catalog/statuses/'.$next->id.'/default')->assertForbidden();
        $this->as($actor)->post('/asset-catalog/statuses/'.$next->id.'/default')->assertRedirect();
        $this->put('/asset-catalog/statuses/'.$available->id, ['name' => 'Tersedia', 'is_active' => 0])->assertRedirect();
        $this->assertDatabaseHas('asset_catalog_defaults', ['status_id' => $next->id, 'condition_id' => $good->id]);
        $this->assertSame($next->id, app(ResolveAssetCatalog::class)->resolve($category->id, $type->id, [])['status_id']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'event' => 'asset-master.default_changed']);
        $this->assertInvalid(fn () => app(ResolveAssetCatalog::class)->resolve($category->id, $type->id, [], $available->id), ['status_id']);
    }

    public function test_mst_002_validation_uses_the_current_type_and_preserves_versions(): void
    {
        $actor = Fixture::actor(Fixture::tree())->assignRole(IdentityAccess::SUPER_ADMIN);
        $this->as($actor);
        $category = AssetCategory::query()->create(['code' => 'COMPUTER', 'name' => 'Computers']);
        $type = AssetType::query()->create(['category_id' => $category->id, 'code' => 'LAPTOP', 'name' => 'Laptop']);
        $otherCategory = AssetCategory::query()->create(['code' => 'TOOLS', 'name' => 'Tools']);
        $otherType = AssetType::query()->create(['category_id' => $otherCategory->id, 'code' => 'OTHER', 'name' => 'Other']);
        $base = '/asset-catalog/types/'.$type->id.'/attributes';
        $this->get($base.'/new')->assertOk();
        $this->post($base, ['data_type' => 'regex'] + $this->attribute('storage', 'Storage', 'integer'))->assertSessionHasErrors('data_type');
        $this->post($base, $this->attribute('storage', 'Storage', 'integer', ['min' => '1', 'max' => '2048']))->assertRedirect();
        $this->post($base, $this->attribute('color', 'Color', 'select', ['options' => "Black\nSilver", 'is_required' => 0]))->assertRedirect();
        $this->post($base, $this->attribute('weight', 'Weight', 'decimal', ['min' => '0', 'max' => '999999999999.999999']))->assertRedirect();
        $this->post($base, $this->attribute('enabled', 'Enabled', 'boolean'))->assertRedirect();
        $this->post($base, $this->attribute('built', 'Built date', 'date'))->assertRedirect();
        $storage = AttributeDefinition::query()->where('key', 'storage')->sole();
        $color = AttributeDefinition::query()->where('key', 'color')->sole();
        $weight = AttributeDefinition::query()->where('key', 'weight')->sole();
        $enabled = AttributeDefinition::query()->where('key', 'enabled')->sole();
        $built = AttributeDefinition::query()->where('key', 'built')->sole();
        $this->get($base)->assertOk()->assertSee('Storage');
        $this->get('/asset-catalog/types/'.$otherType->id.'/attributes/'.$storage->id)->assertNotFound();

        $validator = app(ResolveAssetCatalog::class);
        $this->assertInvalid(fn () => $validator->resolve($otherCategory->id, $type->id, []), ['asset_type_id']);
        $this->assertInvalid(fn () => $validator->resolve($category->id, $type->id,
            ['color' => 'Blue', 'other' => 1, 'weight' => '1e3', 'enabled' => [], 'built' => '2026-02-30']),
            ['attributes', 'attributes.storage', 'attributes.color', 'attributes.weight', 'attributes.enabled', 'attributes.built']);
        $values = ['storage' => '512', 'color' => 'Black', 'weight' => '999999999999.999999', 'enabled' => '0', 'built' => '2024-02-29'];
        $preview = $validator->resolve($category->id, $type->id, $values);
        $this->assertSame($preview, $validator->resolve($category->id, $type->id, $values, forWrite: true));
        $this->assertSame(512, $preview['attributes'][$storage->id]);
        $this->assertSame('Black', $preview['attributes'][$color->id]);
        $this->assertSame('999999999999.999999', $preview['attributes'][$weight->id]);
        $this->assertFalse($preview['attributes'][$enabled->id]);
        $this->assertSame('2024-02-29', $preview['attributes'][$built->id]);

        $this->post($base, $this->attribute('storage', 'Duplicate', 'integer'))->assertSessionHasErrors('key');
        $this->put($base.'/'.$storage->id, $this->attribute(null, 'Storage GB', 'integer', ['min' => '1024']))->assertRedirect();
        $this->get($base.'/'.$storage->id)->assertOk()->assertSee('Storage')->assertSee(__('masters.archived'));
        $this->assertEquals(['min' => '1', 'max' => '2048'], $storage->fresh()->validation_rules);
        $this->assertTrue($storage->fresh()->configuration->is_required);
        $this->assertInvalid(fn () => $validator->resolve($category->id, $type->id, $values), ['attributes.storage']);
        $current = AttributeDefinition::query()->where('key', 'storage')->where('is_active', true)->sole();
        $this->assertSame(2, $current->version);
        $this->post($base.'/'.$current->id.'/archive')->assertRedirect();
        $this->assertInvalid(fn () => $validator->resolve($category->id, $type->id, $values), ['attributes']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'subject_id' => $storage->id, 'event' => 'asset-attribute.archived']);
    }

    public function test_shared_catalog_requires_view_permission_scope_and_management_permission(): void
    {
        $tree = Fixture::tree();
        $this->get('/asset-catalog/categories')->assertRedirect('/login');
        $this->as(Fixture::actor($tree, permissions: [AssetMasterPolicy::VIEW, AssetMasterPolicy::MANAGE]))
            ->get('/asset-catalog/categories')->assertForbidden();
        $reader = Fixture::actor($tree, 'department', [AssetMasterPolicy::VIEW]);
        $this->as($reader)->get('/asset-catalog/vendors')->assertOk()->assertDontSee(__('asset_catalog.add'));
        $data = ['master_code' => 'VEN', 'name' => 'Vendor Test', 'is_active' => 1];
        $this->post('/asset-catalog/vendors', $data)->assertForbidden();
        $manager = Fixture::actor($tree, 'department', [AssetMasterPolicy::VIEW, AssetMasterPolicy::MANAGE]);
        $this->as($manager)->post('/asset-catalog/vendors', $data)->assertRedirect();
        $this->post('/asset-catalog/brands', ['master_code' => 'BRAND'] + $data)->assertRedirect();
        $this->as($reader)->get('/asset-catalog/vendors')->assertSee('Vendor Test');
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);
    }

    private function attribute(?string $key, string $label, string $dataType, array $overrides = []): array
    {
        return $overrides + ($key === null ? [] : ['key' => $key]) + [
            'label' => $label, 'data_type' => $dataType, 'is_required' => 1, 'sort_order' => 0,
        ];
    }

    private function assertInvalid(callable $check, array $fields): void
    {
        try {
            $check();
            $this->fail('Catalog validation unexpectedly passed.');
        } catch (ValidationException $exception) {
            $this->assertEqualsCanonicalizing($fields, array_keys($exception->errors()));
        }
    }
}
