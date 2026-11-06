<?php

namespace Tests\Feature;

use App\Actions\Assets\AssetLifecycle;
use App\Actions\Assets\MoveAssetLocation;
use App\Actions\Assets\ReassignAsset;
use App\Actions\Assets\RegisterAsset;
use App\Models\Asset;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\AssignmentFixture;
use Tests\Support\OrganizationFixtures as Fixture;

class AssetTrackingTest extends TestCase
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

    public function test_asn_001_asn_002_reassignment_and_move_preserve_each_other_and_reject_replayed_forms(): void
    {
        $source = Fixture::tree();
        $other = Fixture::tree();
        $admin = Fixture::actor($source)->assignRole(IdentityAccess::SUPER_ADMIN);
        $asset = $this->register($admin, $source, ['department_id' => $source['department']]);
        $old = $asset->activeAssignment()->sole();
        $initial = $this->locationVersion($asset);
        $page = '/assets/'.$asset->id.'/tracking';
        $reassign = '/assets/'.$asset->id.'/reassign';
        $move = '/assets/'.$asset->id.'/move';
        $this->as($admin)->get($page)->assertOk()->assertSee(__('tracking.assignment_history'))->assertSee(__('tracking.location_history'));
        $change = ['assignment_id' => $old->id, 'assignee' => 'location:'.$other['location'], 'reason' => 'Alih tanggung jawab'];
        $this->from($page)->post($reassign, ['reason' => ''] + $change)->assertSessionHasErrors('reason', errorBag: 'assignmentChange');
        $this->post($reassign, $change)->assertRedirect($page);
        $this->post($reassign, $change)->assertSessionHasErrors('assignment_id', errorBag: 'assignmentChange');
        $assignment = $asset->activeAssignment()->sole();
        $this->assertSame($other['location'], $assignment->location_id);
        $this->assertSame($source['location'], $asset->fresh()->physical_location_id);
        $this->assertSame($initial, $this->locationVersion($asset));
        $this->assertEquals($assignment->started_at, $old->fresh()->ended_at);

        $newLocation = Fixture::insert('locations', 'TEST-ROOM', 'Ruang pengujian', ['branch_id' => $other['branch']]);
        $locationChange = ['location_history_id' => $initial, 'physical_location_id' => $newLocation, 'reason' => 'Pindah ruangan'];
        $this->post($move, $locationChange)->assertRedirect($page);
        $this->post($move, $locationChange)->assertSessionHasErrors('location_history_id', errorBag: 'locationMove');
        $this->assertSame($assignment->id, $asset->activeAssignment()->sole()->id);
        $this->assertSame($other['location'], $asset->activeAssignment()->sole()->location_id);
        $this->assertSame($newLocation, $asset->fresh()->physical_location_id);
        $this->assertSame(2, $asset->assignments()->count());
        $this->assertDatabaseHas('asset_location_histories', ['asset_id' => $asset->id, 'from_location_id' => $source['location'], 'to_location_id' => $newLocation]);

        $log = DB::table('audit_logs')->where('asset_id', $asset->id)->where('event', 'asset.reassigned')->sole();
        $this->assertSame($source['department'], json_decode($log->old_values, true)['department_id']);
        $this->assertSame($other['location'], json_decode($log->new_values, true)['location_id']);
        $log = DB::table('audit_logs')->where('asset_id', $asset->id)->where('event', 'asset.location_moved')->sole();
        $this->assertSame($source['location'], json_decode($log->old_values, true)['physical_location_id']);
        $this->assertSame($newLocation, json_decode($log->new_values, true)['physical_location_id']);
        $this->get($page)->assertOk()->assertSee('Alih tanggung jawab')->assertSee('Pindah ruangan')->assertSee('Ruang pengujian');
    }

    public function test_t006_t007_scope_follows_physical_location_and_direct_employee_access_is_read_only(): void
    {
        $source = Fixture::tree();
        $dest = Fixture::tree();
        $admin = Fixture::actor($source)->assignRole(IdentityAccess::SUPER_ADMIN);
        $permissions = [Assets::VIEW, Assets::REASSIGN, Assets::MOVE];
        $holder = Fixture::actor($dest, 'branch', $permissions);
        $colleague = Fixture::actor($dest, 'branch', $permissions);
        $sourceOperator = Fixture::actor($source, 'branch', $permissions);
        $both = Fixture::actor($source, 'branch', $permissions);
        DB::table('user_data_scopes')->insert(['user_id' => $both->id, 'scope_type' => 'branch', 'branch_id' => $dest['branch']]);
        $asset = $this->register($admin, $source, ['employee_id' => $holder->employee_id]);
        $deptAsset = $this->register($admin, $source, ['department_id' => $dest['department']]);
        $tracking = '/assets/'.$asset->id.'/tracking';
        $change = ['assignment_id' => $asset->activeAssignment()->sole()->id, 'assignee' => 'department:'.$source['department'], 'reason' => 'Rotasi'];
        $move = ['location_history_id' => $this->locationVersion($asset), 'physical_location_id' => $dest['location'], 'reason' => 'Pindah cabang'];

        $this->as($holder)->get('/assets?q='.$asset->asset_code)->assertOk()->assertSee($asset->asset_code);
        $this->get('/assets/'.$deptAsset->id)->assertForbidden();
        $this->get($tracking)->assertOk()->assertDontSee(__('tracking.change_assignment'))->assertDontSee(__('tracking.move'));
        $this->post('/assets/'.$asset->id.'/reassign', $change)->assertForbidden();
        $this->post('/assets/'.$asset->id.'/move', $move)->assertForbidden();
        $this->assertFalse(Assets::operational($holder)->whereKey($asset->id)->exists());

        $this->as($colleague)->get($tracking)->assertForbidden();
        $this->post('/assets/'.$asset->id.'/move', $move)->assertForbidden();
        $this->assertFalse(Assets::operational($colleague)->whereKey($asset->id)->exists());
        $this->as($sourceOperator)->get($tracking)->assertOk()->assertSee(__('tracking.change_assignment'));
        $this->post('/assets/'.$asset->id.'/move', $move)->assertForbidden();
        $this->post('/assets/'.$asset->id.'/reassign', $change)->assertRedirect($tracking);
        $this->assertTrue(Assets::operational($sourceOperator)->whereKey($asset->id)->exists());
        $this->as($holder)->get($tracking)->assertForbidden();

        $this->as($both)->post('/assets/'.$asset->id.'/move', $move)->assertRedirect($tracking);
        $this->as($sourceOperator)->get($tracking)->assertForbidden();
        $this->get('/assets')->assertOk()->assertDontSee($asset->asset_code);
        $this->assertFalse(Assets::operational($sourceOperator)->whereKey($asset->id)->exists());
        $this->as($colleague)->get($tracking)->assertOk()->assertSee('Pindah cabang');
        $this->assertTrue(Assets::operational($colleague)->whereKey($asset->id)->exists());
    }

    public function test_tracking_actions_reject_inactive_assets_and_rollback_on_audit_failure(): void
    {
        $tree = Fixture::tree();
        $actor = Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN);
        $asset = $this->register($actor, $tree, ['department_id' => $tree['department']]);
        $assignment = $asset->activeAssignment()->sole();
        $history = $this->locationVersion($asset);
        $otherLocation = Fixture::insert('locations', 'NEXT', 'Other room', ['branch_id' => $tree['branch']]);
        $assign = ['assignment_id' => $assignment->id, 'location_id' => $otherLocation, 'reason' => 'Changed responsibility'];
        $move = ['location_history_id' => $history, 'physical_location_id' => $otherLocation, 'reason' => 'Moved'];
        $reassign = app(ReassignAsset::class);
        $moveAction = app(MoveAssetLocation::class);
        $this->invalid(fn () => $reassign($actor, $asset, $assign + ['department_id' => $tree['department']]), 'assignee');

        $fail = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$fail): void {
            if ($fail && str_starts_with($sql, 'insert into '.chr(96).'audit_logs'.chr(96))) {
                throw new RuntimeException('Injected audit failure.');
            }
        });
        try {
            foreach ([fn () => $reassign($actor, $asset, $assign), fn () => $moveAction($actor, $asset, $move)] as $operation) {
                try {
                    $operation();
                    $this->fail('Tracking write should roll back with the audit failure.');
                } catch (RuntimeException $exception) {
                    $this->assertSame('Injected audit failure.', $exception->getMessage());
                }
            }
        } finally {
            $fail = false;
        }
        $this->assertSame($assignment->id, $asset->activeAssignment()->sole()->id);
        $this->assertSame(1, $asset->assignments()->count());
        $this->assertSame($tree['location'], $asset->fresh()->physical_location_id);
        $this->assertSame($history, $this->locationVersion($asset));

        app(AssetLifecycle::class)->deactivate($actor, $asset, ['reason' => 'Inactive']);
        $this->invalid(fn () => $reassign($actor, $asset, $assign), 'assignment_id');
        $this->invalid(fn () => $moveAction($actor, $asset, $move), 'physical_location_id');
        $this->assertFalse($asset->fresh()->is_active);
        $this->assertNull($asset->activeAssignment()->first());
    }

    private function register(User $actor, array $tree, array $target): Asset
    {
        $type = AssignmentFixture::type();

        return app(RegisterAsset::class)($actor, $target + [
            'name' => 'Asset tracking QA', 'physical_location_id' => $tree['location'], 'asset_type_id' => $type->id,
            'category_id' => $type->category_id, 'purchase_date' => '2026-01-15', 'acquisition_cost' => '10.00',
        ]);
    }

    private function locationVersion(Asset $asset): int
    {
        return DB::table('asset_location_histories')->where('asset_id', $asset->id)->orderByDesc('changed_at')->orderByDesc('id')->value('id');
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);
    }

    private function invalid(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Invalid tracking write was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
