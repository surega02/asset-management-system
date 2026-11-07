<?php

namespace Tests\Feature;

use App\Actions\Assets\AssetLifecycle;
use App\Actions\Assets\PrintAssetLabels;
use App\Actions\Assets\ReassignAsset;
use App\Actions\Assets\RegisterAsset;
use App\Actions\Employees\ManageEmployee;
use App\Actions\Imports\ProcessImportRow;
use App\Models\Asset;
use App\Models\StructuralAppointment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use App\Support\QueueHealth;
use App\Support\SystemNotifications;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssignmentFixture;
use Tests\Support\OrganizationFixtures as Fixture;

class SystemAdministrationTest extends TestCase
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

    public function test_notifications_come_from_committed_events_and_recheck_recipient_scope(): void
    {
        $local = Fixture::tree();
        $remote = Fixture::tree();
        $root = Fixture::actor($local)->assignRole(IdentityAccess::SUPER_ADMIN);
        $localAdmin = Fixture::actor($local, 'branch', [Assets::VIEW, 'employees.view'])->assignRole('asset-admin');
        $remoteAdmin = Fixture::actor($remote, 'branch', [Assets::VIEW, 'employees.view'])->assignRole('asset-admin');
        $oldHolder = Fixture::actor($local, 'branch', [Assets::VIEW]);
        $newHolder = Fixture::actor($local, null, [Assets::VIEW]);
        $asset = $this->asset($root, $local, ['employee_id' => $oldHolder->employee_id]);
        $lifecycle = app(AssetLifecycle::class);
        $publisher = app(SystemNotifications::class);

        DB::beginTransaction();
        try {
            $lifecycle->deactivate($root, $asset, ['reason' => 'Rollback test']);
            $this->assertDatabaseHas('notification_events', ['kind' => 'asset_deactivated', 'subject_id' => $asset->id]);
            $this->assertSame(0, $publisher->inbox($localAdmin)->count());
        } finally {
            DB::rollBack();
        }
        $publisher->publish();
        $this->assertSame(0, $publisher->inbox($localAdmin)->count());
        $this->assertDatabaseMissing('notification_events', ['kind' => 'asset_deactivated', 'subject_id' => $asset->id]);

        app(ReassignAsset::class)($root, $asset, [
            'assignment_id' => $asset->activeAssignment()->sole()->id,
            'employee_id' => $newHolder->employee_id,
            'reason' => 'Shift handover',
        ]);
        app(ManageEmployee::class)->transfer($root, $newHolder->employee, [
            'department_id' => $remote['department'], 'home_location_id' => $remote['location'], 'reason' => 'Transfer test',
        ]);
        $publisher->publish();
        $publisher->publish();
        $notice = $publisher->inbox($oldHolder)->first(['notifications.id']);
        $this->assertNotNull($notice);
        $this->assertSame(['asset_reassigned'], $publisher->inbox($newHolder)->pluck('event.kind')->all());
        $this->assertSame(['employee_transferred'], $publisher->inbox($remoteAdmin)->pluck('event.kind')->all());
        $this->assertSame(0, $publisher->inbox($localAdmin)->count());
        $this->as($oldHolder)->get('/notifications')->assertOk()->assertSee(__('system.notice.asset_reassigned'));
        $this->post('/notifications/'.$notice->id.'/read')->assertRedirect('/notifications');
        $this->assertNotNull(DB::table('notifications')->where('id', $notice->id)->value('read_at'));
        $this->as($newHolder)->post('/notifications/'.$notice->id.'/read')->assertNotFound();
        DB::table('user_data_scopes')->where('user_id', $oldHolder->id)->delete();
        $this->as($oldHolder)->get('/notifications')->assertOk()->assertDontSee(__('system.notice.asset_reassigned'));
        $this->post('/notifications/'.$notice->id.'/read')->assertNotFound();

        $lifecycle->deactivate($root, $asset, ['reason' => 'Retired']);
        $lifecycle->reactivate($root, $asset, [
            'location_id' => $local['location'], 'status_id' => $asset->status_id, 'condition_id' => $asset->condition_id, 'reason' => 'Returned',
        ]);
        DB::table('user_data_scopes')->where('user_id', $localAdmin->id)->delete();
        $publisher->publish();
        $this->assertSame(0, DB::table('notifications')->where('user_id', $localAdmin->id)->count());
        $this->assertEqualsCanonicalizing(['asset_deactivated', 'asset_reactivated'], $publisher->inbox($root)
            ->whereIn('event.kind', ['asset_deactivated', 'asset_reactivated'])->pluck('event.kind')->all());
    }

    public function test_import_and_acting_reminders_are_deduplicated_and_queue_check_records_counts(): void
    {
        $tree = Fixture::tree();
        $root = Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN);
        $importer = Fixture::actor($tree, 'branch', [Assets::VIEW, Assets::IMPORT]);
        $batch = DB::table('import_batches')->insertGetId([
            'user_id' => $importer->id, 'status' => 'running', 'total_rows' => 1, 'created_at' => now(),
        ]);
        DB::table('import_rows')->insert(['import_batch_id' => $batch, 'row_number' => 2, 'action' => 'skip', 'status' => 'pending']);
        $worker = app(ProcessImportRow::class);
        $worker->execute($worker->claim($batch));
        $this->assertNull($worker->claim($batch));
        $this->assertNull($worker->claim($batch));
        $acting = StructuralAppointment::query()->create([
            'employee_id' => $root->employee_id, 'position_id' => $tree['position'], 'role_kind' => 'hod',
            'scope_type' => 'department', 'department_id' => $tree['department'], 'appointment_kind' => 'acting',
            'starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->addDays(3)->toDateString(),
        ]);
        SystemNotifications::remindActing();
        SystemNotifications::remindActing();
        $publisher = app(SystemNotifications::class);
        $publisher->publish();
        $this->assertSame(['asset_import'], $publisher->inbox($importer)->pluck('event.kind')->all());
        $this->assertSame(['acting_expiry'], $publisher->inbox($root)->pluck('event.kind')->all());
        $acting->update(['ends_on' => today()->addDays(20)->toDateString()]);
        $this->assertSame(0, $publisher->inbox($root)->count());

        $before = now()->subMinutes(10)->timestamp;
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => $before, 'created_at' => $before]);
        QueueHealth::check();
        $this->assertSame(1, Cache::get(QueueHealth::KEY)['overdue']);
        $this->as($root)->get('/admin/settings')->assertOk()->assertSee(__('system.queue_overdue'));
        $this->assertSame(0, Artisan::call('schedule:list'));
    }

    public function test_settings_are_super_admin_only_and_audit_view_masks_legacy_payloads(): void
    {
        $local = Fixture::tree();
        $remote = Fixture::tree();
        $root = Fixture::actor($local)->assignRole(IdentityAccess::SUPER_ADMIN);
        $reviewer = Fixture::actor($local, 'branch', [Assets::VIEW, 'audit.view', 'settings.manage'])->assignRole('asset-admin');
        $normal = Fixture::actor($local, 'branch', [Assets::VIEW]);
        $asset = $this->asset($root, $local);
        $elsewhere = $this->asset($root, $remote);
        $label = DB::table('asset_label_templates')->where('name', 'standard')->value('id');
        $printer = app(PrintAssetLabels::class);
        $job = $printer->prepare($root, [$asset->id], $label, ['name']);
        $this->as($reviewer)->get('/admin/settings')->assertForbidden();
        $this->put('/admin/settings/locale', ['locale' => 'en'])->assertForbidden();
        $this->put('/admin/settings/labels/'.$label, ['width_mm' => 90, 'height_mm' => 45])->assertForbidden();
        $this->as($normal)->get('/audit')->assertForbidden();
        $this->as($root)->put('/admin/settings/locale', ['locale' => 'zh-CN'])->assertRedirect('/admin/settings');
        $this->assertSame('zh-CN', SystemSetting::defaultLocale());
        $this->assertSame('zh-CN', Fixture::actor($local)->locale);
        $this->assertSame('id', $root->fresh()->locale);
        $this->put('/admin/settings/labels/'.$label, ['width_mm' => 90, 'height_mm' => 45, 'allowed_fields' => ['public_token']])
            ->assertSessionHasErrors('allowed_fields.0');
        $this->put('/admin/settings/labels/'.$label, ['width_mm' => 90, 'height_mm' => 45, 'allowed_fields' => ['category']])->assertRedirect();
        $this->assertSame([], $printer->preview($root, $job)['fields']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.locale_changed', 'actor_id' => $root->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.label_changed', 'actor_id' => $root->id]);

        $id = IdentityAudit::record($root->id, $asset, 'asset.qa', 'password=synthetic-secret',
            new: ['password' => 'synthetic-secret', 'two_factor_secret' => 'test-factor', 'nik' => 'private-nik', 'status_id' => 5]);
        $this->assertSame('[redacted]', DB::table('audit_logs')->where('id', $id)->value('reason'));
        $this->assertSame(['status_id' => 5], json_decode(DB::table('audit_logs')->where('id', $id)->value('new_values'), true));
        DB::table('audit_logs')->where('id', $id)->update([
            'new_values' => '{"public_token":"legacy-value","status_id":5,"role_ids":[1,"legacy-value"],"name":{"password":"legacy-value"}}',
            'reason' => 'token=legacy-value',
        ]);
        $this->as($reviewer)->get('/audit')->assertOk()->assertSee($asset->asset_code)->assertDontSee($elsewhere->asset_code)->assertDontSee('settings.locale_changed');
        $this->get('/audit?event=asset.qa&asset='.$asset->asset_code.'&actor='.$root->id.'&from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()->assertSee(__('system.redacted'))->assertSee('status_id')->assertDontSee('legacy-value')->assertDontSee('public_token');
        $this->put('/audit/'.$id, ['reason' => 'overwrite'])->assertNotFound();
    }

    private function asset(User $actor, array $tree, array $assignment = []): Asset
    {
        $type = AssignmentFixture::type();

        return app(RegisterAsset::class)($actor, ($assignment ?: ['location_id' => $tree['location']]) + [
            'name' => 'M11 fixture', 'physical_location_id' => $tree['location'], 'asset_type_id' => $type->id,
            'category_id' => $type->category_id, 'purchase_date' => '2026-01-15', 'acquisition_cost' => '0',
        ]);
    }

    private function as(User $actor): static
    {
        return $this->actingAs($actor)->withSession(['auth.version' => $actor->auth_version]);
    }
}
