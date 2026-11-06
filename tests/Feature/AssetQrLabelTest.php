<?php

namespace Tests\Feature;

use App\Actions\Assets\RegisterAsset;
use App\Models\Asset;
use App\Models\User;
use App\Queries\Assets;
use App\Support\AssetQr;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssignmentFixture;
use Tests\Support\OrganizationFixtures as Fixture;

class AssetQrLabelTest extends TestCase
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

    public function test_t011_qr_001_public_scan_has_exactly_four_fields_and_detail_requires_scope(): void
    {
        $source = Fixture::tree();
        $other = Fixture::tree();
        $admin = Fixture::actor($source)->assignRole(IdentityAccess::SUPER_ADMIN);
        $holder = Fixture::actor($other, 'branch', [Assets::VIEW, Assets::PRINT_LABEL]);
        $outsider = Fixture::actor($other, 'branch', [Assets::VIEW, Assets::PRINT_LABEL]);
        $asset = $this->register($admin, $source, $holder->employee_id);
        $qr = '/q/'.$asset->public_token;
        $detail = '/assets/'.$asset->id;
        $employee = DB::table('employees')->find($holder->employee_id);
        $location = DB::table('locations')->find($source['location']);

        $this->get($qr)->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertViewHas('fields', fn (array $fields): bool => array_keys($fields) === ['code', 'name', 'category', 'status'])
            ->assertSee($asset->asset_code)->assertSee($asset->name)
            ->assertDontSee($employee->name)->assertDontSee($employee->nik)->assertDontSee($location->name)
            ->assertDontSee('PRIVATE-SERIAL-QR')->assertDontSee('PRIVATE-INVOICE-QR')->assertDontSee('99129.55');
        $this->get('/q/not-a-token')->assertNotFound();
        $this->get('/q/'.str_repeat('f', 64))->assertNotFound();
        $this->get($qr.'/detail')->assertRedirect(route('login'));
        $this->get('/assets/find?asset_code='.$asset->asset_code)->assertRedirect(route('login'));

        $this->as($outsider)->get($qr.'/detail')->assertForbidden();
        $this->get($detail.'/tracking')->assertForbidden();
        $this->get('/assets/find?asset_code='.$asset->asset_code)->assertRedirect(route('assets.scan'))
            ->assertSessionHasErrors(['asset_code' => __('qr.code_unavailable')]);
        $this->get('/assets/find?asset_code=AST-999999999999')->assertRedirect(route('assets.scan'))
            ->assertSessionHasErrors(['asset_code' => __('qr.code_unavailable')]);
        $this->as($holder)->get($qr.'/detail')->assertRedirect($detail);
        $this->get('/assets/find?asset_code='.strtolower($asset->asset_code))->assertRedirect($detail);
        $this->get($detail.'/tracking')->assertOk();
        $this->get($detail.'/label')->assertForbidden();
    }

    public function test_qr_002_single_and_bulk_label_previews_fail_closed_when_scope_changes(): void
    {
        config(['app.url' => 'https://ams.example.test']);
        $source = Fixture::tree();
        $other = Fixture::tree();
        $admin = Fixture::actor($source)->assignRole(IdentityAccess::SUPER_ADMIN);
        $operator = Fixture::actor($source, 'branch', [Assets::VIEW, Assets::PRINT_LABEL]);
        $colleague = Fixture::actor($source, 'branch', [Assets::VIEW, Assets::PRINT_LABEL]);
        $asset = $this->register($admin, $source);
        $second = $this->register($admin, $source);
        $foreign = $this->register($admin, $other);
        $template = DB::table('asset_label_templates')->where('width_mm', 60)->value('id');
        $form = ['asset_ids' => [$asset->id], 'template_id' => $template, 'fields' => ['name', 'category']];

        $this->get('/assets/labels')->assertRedirect(route('login'));
        $this->as($operator)->get('/assets/'.$asset->id.'/label')->assertOk();
        $this->get('/assets/labels')->assertOk()->assertSee($asset->asset_code)->assertDontSee($foreign->asset_code);
        $single = $this->post('/assets/labels', $form)->assertRedirect()->headers->get('Location');
        $this->get($single)->assertOk()->assertSee('60.00mm 36.00mm', false)
            ->assertSee(AssetQr::dataImage($asset->public_token), false)
            ->assertSee($asset->name)->assertDontSee('PRIVATE-SERIAL-QR')->assertDontSee('PRIVATE-INVOICE-QR')
            ->assertDontSee($asset->public_token);

        $count = DB::table('asset_label_print_jobs')->count();
        $this->from('/assets/labels')->post('/assets/labels', array_replace($form, ['fields' => ['serial_number']]))
            ->assertSessionHasErrors('fields.0');
        $this->post('/assets/labels', array_replace($form, ['asset_ids' => [$asset->id, $foreign->id]]))->assertForbidden();
        $this->assertSame($count, DB::table('asset_label_print_jobs')->count());
        $bulk = $this->post('/assets/labels', array_replace($form, ['asset_ids' => [$asset->id, $second->id], 'fields' => []]))
            ->assertRedirect()->headers->get('Location');
        $this->get($bulk)->assertOk()->assertSee($asset->asset_code)->assertSee($second->asset_code)->assertDontSee($asset->name);
        $this->as($colleague)->get($bulk)->assertNotFound();

        $version = DB::table('asset_location_histories')->where('asset_id', $asset->id)->max('id');
        $this->as($admin)->post('/assets/'.$asset->id.'/move', [
            'location_history_id' => $version, 'physical_location_id' => $other['location'], 'reason' => 'QR scope change',
        ])->assertRedirect('/assets/'.$asset->id.'/tracking');
        $this->as($operator)->get($bulk)->assertForbidden();
        $this->get($single)->assertForbidden();
    }

    private function register(User $actor, array $tree, ?int $holder = null): Asset
    {
        $type = AssignmentFixture::type();

        return app(RegisterAsset::class)($actor, ($holder ? ['employee_id' => $holder] : ['location_id' => $tree['location']]) + [
            'name' => 'QR label fixture', 'physical_location_id' => $tree['location'], 'asset_type_id' => $type->id,
            'category_id' => $type->category_id, 'purchase_date' => '2026-01-15', 'acquisition_cost' => '99129.55',
            'serial_number' => 'PRIVATE-SERIAL-QR', 'invoice_number' => 'PRIVATE-INVOICE-QR',
        ]);
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);
    }
}
