<?php

namespace Tests\Feature;

use App\Models\User;
use App\Queries\OrganizationMasters;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\OrganizationFixtures as Fixture;

class OrganizationMastersTest extends TestCase
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

    public function test_org_001_creates_each_master_preserves_owners_and_archives_referenced_rows(): void
    {
        $tree = Fixture::tree();
        $this->as(Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN));

        foreach (['branches' => null, 'divisions' => $tree['branch'], 'departments' => $tree['division'], 'positions' => null, 'locations' => $tree['branch']] as $type => $owner) {
            $code = 'NEW-'.$tree['code'];
            $this->get("/masters/$type/new")->assertOk();
            $data = Fixture::data($code, $owner ? ['owner_id' => $owner] : []);
            $this->post("/masters/$type", $data)->assertRedirect();
            $id = DB::table($type)->where('code', $code)->sole()->id;
            $this->get("/masters/$type/$id")->assertOk()->assertSee($code);
            $this->put("/masters/$type/$id", Fixture::data($code, ['name' => 'Nama baru']))->assertRedirect();
            $this->assertDatabaseHas($type, ['id' => $id, 'name' => 'Nama baru']);
            $this->post("/masters/$type", $data)->assertSessionHasErrors('master_code');
        }

        $this->put('/masters/divisions/'.$tree['division'], Fixture::data($tree['code'], ['owner_id' => Fixture::tree()['branch']]))
            ->assertSessionHasErrors('owner_id');
        $this->assertDatabaseHas('divisions', ['id' => $tree['division'], 'branch_id' => $tree['branch']]);
        $this->put('/masters/branches/'.$tree['branch'], Fixture::data($tree['code'], ['is_active' => 0]))->assertRedirect();
        $this->assertDatabaseHas('branches', ['id' => $tree['branch'], 'is_active' => 0]);
        $this->assertDatabaseHas('divisions', ['id' => $tree['division'], 'branch_id' => $tree['branch'], 'is_active' => 1]);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $tree['branch'], 'event' => 'master.archived']);
        $this->post('/masters/divisions', Fixture::data('X', ['owner_id' => $tree['branch']]))->assertSessionHasErrors('owner_id');
        $this->put('/masters/branches/'.$tree['branch'], Fixture::data($tree['code']))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $tree['branch'], 'event' => 'master.reactivated']);
        $this->delete('/masters/branches/'.$tree['branch'])->assertStatus(405);
        $this->assertMySqlRejects(1451, fn () => DB::table('branches')->where('id', $tree['branch'])->delete());
        $this->assertMySqlRejects(1451, fn () => DB::table('positions')->where('id', $tree['position'])->delete());
        $this->assertMySqlRejects(1452, fn () => DB::table('divisions')->insert(['branch_id' => 0, 'code' => 'X', 'name' => 'X']));
        $this->assertMySqlRejects(1452, fn () => DB::table('departments')->insert(['division_id' => 0, 'code' => 'X', 'name' => 'X']));
    }

    public function test_iam_003_requires_both_permission_and_scope_and_never_expands_department_upward(): void
    {
        $tree = Fixture::tree();
        $other = Fixture::tree();
        $sibling = Fixture::insert('departments', 'SIBLING', 'Departemen tetangga', ['division_id' => $tree['division']]);
        $view = OrganizationMasters::VIEW;
        $manage = OrganizationMasters::MANAGE;

        $this->get('/masters/locations')->assertRedirect('/login');
        $this->as(Fixture::actor($tree, 'branch'))->get('/masters/locations')->assertForbidden();
        $this->as(Fixture::actor($tree, permissions: [$view, $manage]))->get('/masters/locations')->assertForbidden();
        $this->as(Fixture::actor($tree, 'branch', [$view]))->get('/masters/locations/'.$tree['location'])->assertOk()->assertDontSee(__('masters.save'));
        $this->put('/masters/locations/'.$tree['location'], Fixture::data('DENIED'))->assertForbidden();
        $this->get('/masters/locations/new')->assertForbidden();

        $this->as(Fixture::actor($tree, 'branch', [$view, $manage]))->get('/masters/locations')
            ->assertOk()->assertSee($tree['code'])->assertDontSee($other['code']);
        $this->get('/masters/locations/'.$other['location'])->assertForbidden();
        $this->put('/masters/locations/'.$other['location'], Fixture::data('DENIED'))->assertForbidden();
        $this->post('/masters/locations', Fixture::data('DENIED', ['owner_id' => $other['branch']]))->assertForbidden();
        $this->put('/masters/positions/'.$tree['position'], Fixture::data('DENIED'))->assertForbidden();
        $this->get('/masters/locations/new')->assertOk()->assertDontSee($other['code']);
        $this->post('/masters/locations', Fixture::data('LOCAL', ['owner_id' => $tree['branch']]))->assertRedirect();
        $this->assertDatabaseHas('locations', ['code' => 'LOCAL', 'branch_id' => $tree['branch']]);

        $this->as(Fixture::actor($tree, 'department', [$view, $manage]))->get('/masters/departments')
            ->assertOk()->assertSee($tree['code'])->assertDontSee('Departemen tetangga');
        $this->get('/masters/departments/'.$sibling)->assertForbidden();
        $this->get('/masters/divisions/'.$tree['division'])->assertForbidden();
        $this->get('/masters/locations/'.$tree['location'])->assertForbidden();
        $this->post('/masters/departments', Fixture::data('DENIED', ['owner_id' => $tree['division']]))->assertForbidden();
    }

    public function test_mst_001_rejects_location_cycles_cross_branch_links_and_deletion_of_a_parent(): void
    {
        $tree = Fixture::tree();
        $other = Fixture::tree();
        $root = $tree['location'];
        $child = Fixture::insert('locations', 'CHILD', 'Lantai 1', ['branch_id' => $tree['branch'], 'parent_id' => $root]);
        $leaf = Fixture::insert('locations', 'LEAF', 'Ruang 1', ['branch_id' => $tree['branch'], 'parent_id' => $child]);
        $this->as(Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN));

        $invalid = array_fill_keys(['owner_id', 'parent_id', 'master_code', 'name', 'is_active'], ['unexpected']);
        $this->from('/masters/locations/new')->post('/masters/locations', $invalid)->assertSessionHasErrors(array_keys($invalid));
        $this->get('/masters/locations/new')->assertOk()->assertSee(__('masters.create_locations'));

        foreach ([$root, $leaf, $other['location'], 999999999] as $parent) {
            $this->from('/masters/locations/'.$root)->put('/masters/locations/'.$root, Fixture::data($tree['code'], ['parent_id' => $parent]))
                ->assertSessionHasErrors('parent_id')->assertSessionHasInput('master_code', $tree['code']);
        }
        $this->assertDatabaseHas('locations', ['id' => $root, 'parent_id' => null]);
        $this->assertMySqlRejects(1452, fn () => DB::table('locations')->where('id', $root)->update(['parent_id' => $other['location']]));
        $this->assertMySqlRejects(1451, fn () => DB::table('locations')->where('id', $child)->delete());

        $this->put('/masters/locations/'.$child, Fixture::data('CHILD', ['parent_id' => $root, 'is_active' => 0]))->assertRedirect();
        $this->assertDatabaseHas('locations', ['id' => $leaf, 'parent_id' => $child, 'is_active' => 1]);
        $this->post('/masters/locations', Fixture::data('NEW', ['owner_id' => $tree['branch'], 'parent_id' => $child]))
            ->assertSessionHasErrors('parent_id');
        $this->put('/masters/locations/'.$leaf, Fixture::data('LEAF', ['parent_id' => $root]))->assertRedirect();
        $audit = DB::table('audit_logs')->where('subject_id', $leaf)->where('event', 'master.updated')->sole();
        $this->assertSame($child, json_decode($audit->old_values, true)['parent_id']);
        $this->assertSame($root, json_decode($audit->new_values, true)['parent_id']);
    }

    public function test_search_pagination_archived_filter_and_locales(): void
    {
        $tree = Fixture::tree();
        for ($i = 1; $i <= 22; $i++) {
            Fixture::insert('locations', "FLOOR-$i", sprintf('Gudang %02d', $i), ['branch_id' => $tree['branch'], 'is_active' => $i < 22]);
        }
        $user = Fixture::actor($tree, 'branch', [OrganizationMasters::VIEW]);
        $this->as($user)->get('/masters/locations?q=Gudang')->assertOk()->assertSee('1–20 dari 21')
            ->assertSee('Gudang 01')->assertDontSee('Gudang 21');
        $this->get('/masters/locations?q=Gudang&page=2')->assertSee('Gudang 21')->assertDontSee('Gudang 22');
        $this->get('/masters/locations?q=FLOOR-22&status=archived')->assertSee('Gudang 22');
        $this->get('/masters/locations?q=%25')->assertSee('Belum ada hasil');
        $this->post('/language', ['locale' => 'en'])->assertRedirect('/workspace');
        $this->as($user->fresh())->get('/masters/locations')->assertSee('Organization masters');
        $this->post('/language', ['locale' => 'zh-CN'])->assertRedirect('/workspace');
        $this->as($user->fresh())->get('/masters/locations')->assertSee('组织主数据');
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);
    }

    private function assertMySqlRejects(int $error, callable $write): void
    {
        try {
            $write();
            $this->fail('MySQL accepted an invalid write.');
        } catch (QueryException $exception) {
            $this->assertSame($error, (int) $exception->errorInfo[1]);
        }
    }
}
