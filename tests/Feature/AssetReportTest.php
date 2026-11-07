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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssignmentFixture;
use Tests\Support\OrganizationFixtures as Fixture;
use ZipArchive;

class AssetReportTest extends TestCase
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

    public function test_t012_operational_dashboard_reports_and_both_exports_recheck_physical_scope(): void
    {
        $local = Fixture::tree();
        $remote = Fixture::tree();
        $admin = Fixture::actor($local)->assignRole(IdentityAccess::SUPER_ADMIN);
        $officer = Fixture::actor($local, 'branch', [Assets::VIEW]);
        $employee = Fixture::actor($local, null, [Assets::VIEW]);
        $withoutPermission = Fixture::actor($local);
        $asset = $this->register($admin, $local, '=QA asset', ['department_id' => $local['department']]);
        $personal = $this->register($admin, $remote, 'Direct outside branch', ['employee_id' => $employee->employee_id]);
        $department = $this->register($admin, $remote, 'Remote department unit', ['department_id' => $local['department']]);

        $this->get('/reports')->assertRedirect('/login');
        $this->as($withoutPermission)->get('/reports/units/export/csv')->assertForbidden();
        $this->as($employee)->get('/workspace')->assertOk()->assertSee($personal->name)->assertDontSee($department->name)
            ->assertDontSee(__('reports.operational'));
        $this->get('/reports/groups?group=department')->assertOk()->assertSee(__('reports.empty'));
        $this->assertCount(1, $this->csvRows($this->get('/reports/units/export/csv')->assertOk()->streamedContent()));
        $this->get('/assets/'.$department->id)->assertForbidden();
        $this->get('/assets/'.$personal->id)->assertOk();

        $this->as($officer)->get('/workspace')->assertOk()->assertViewHas('totals', fn ($totals) => (int) $totals->total === 1);
        $screen = $this->get('/reports/units')->assertOk()->assertSee($asset->asset_code)->assertDontSee($personal->asset_code)
            ->assertDontSee($department->asset_code);
        $row = $screen->viewData('rows')->items()[0]['cells'];
        $headers = $screen->viewData('columns');
        $csv = $this->csvRows($this->get('/reports/units/export/csv')->assertOk()->streamedContent());
        $this->assertSame([$headers, array_replace($row, [1 => "'=QA asset"])], $csv);
        $this->assertSame([$headers, $row], $this->xlsxRows('/reports/units/export/xlsx'));
        $this->get('/reports/units?branch='.$remote['branch'])->assertOk()->assertSee(__('reports.empty'));
        $group = $this->get('/reports/groups?group=department')->assertOk()->viewData('rows')->items();
        $this->assertCount(1, $group);
        $this->assertSame([1, 1, 0], array_slice($group[0]['cells'], 1));
        foreach (['branch', 'employee', 'category', 'condition'] as $dimension) {
            $rows = $this->get('/reports/groups?group='.$dimension)->assertOk()->viewData('rows')->items();
            $this->assertCount(1, $rows);
            $this->assertSame([1, 1, 0], array_slice($rows[0]['cells'], 1));
        }

        $this->move($admin, $asset, $remote['location'], 'Moved out of scope');
        $this->get('/assets/'.$asset->id)->assertForbidden();
        $this->get('/reports/events')->assertOk()->assertDontSee('Moved out of scope');
        $this->assertCount(1, $this->csvRows($this->get('/reports/units/export/csv')->assertOk()->streamedContent()));
        $this->assertCount(1, $this->xlsxRows('/reports/units/export/xlsx'));
    }

    public function test_t013_period_returns_changes_at_event_time_and_current_filters_use_the_selected_date(): void
    {
        $local = Fixture::tree();
        $admin = Fixture::actor($local)->assignRole(IdentityAccess::SUPER_ADMIN);
        $office = Fixture::insert('locations', 'MAR-'.$local['code'], 'March office', ['branch_id' => $local['branch']]);
        $warehouse = Fixture::insert('locations', 'JUN-'.$local['code'], 'June warehouse', ['branch_id' => $local['branch']]);
        $this->travelTo(Carbon::parse('2026-02-01 10:00:00'));
        try {
            $asset = $this->register($admin, $local, 'Historical fixture', ['location_id' => $local['location']]);
            $newStatus = Fixture::insert('asset_statuses', 'S-'.$local['code'], 'Status after');
            $newCondition = Fixture::insert('asset_conditions', 'C-'.$local['code'], 'Condition after');
            $this->travelTo(Carbon::parse('2026-03-31 23:59:59'));
            $this->move($admin, $asset, $office, 'March move');
            $assignment = $asset->activeAssignment()->sole();
            app(ReassignAsset::class)($admin, $asset, [
                'assignment_id' => $assignment->id, 'department_id' => $local['department'], 'reason' => 'March assignee',
            ]);
            app(AssetLifecycle::class)->updateState($admin, $asset, [
                'status_id' => $newStatus, 'condition_id' => $newCondition, 'reason' => 'March state',
            ]);
            $this->travelTo(Carbon::parse('2026-06-15 11:00:00'));
            $this->move($admin, $asset, $warehouse, 'June move');

            $dates = 'from=2026-03-01&to=2026-03-31';
            $events = $this->as($admin)->get('/reports/events?'.$dates)->assertOk()
                ->assertSee('March move')->assertSee('March assignee')->assertSee('Status after')->assertSee('Condition after')
                ->assertSee($admin->employee->name)->assertDontSee('June move')->assertDontSee('June warehouse');
            $rows = array_column($events->viewData('rows')->items(), 'cells');
            $this->assertCount(4, $rows);
            $this->assertContains(['March office', 'March move'], array_map(fn ($row) => [$row[5], $row[7]], $rows));
            $this->assertSame([$events->viewData('columns'), ...$rows], $this->csvRows($this->get('/reports/events/export/csv?'.$dates)->assertOk()->streamedContent()));
            $this->assertSame([$events->viewData('columns'), ...$rows], $this->xlsxRows('/reports/events/export/xlsx?'.$dates));
            $this->get('/reports/events?kind=condition&'.$dates)->assertOk()->assertSee('Condition after')->assertDontSee('Status after');
            $this->get('/reports/units?date=purchase&from=2026-01-15&to=2026-01-15')->assertOk()->assertSee('June warehouse');
            $this->get('/reports/units?date=registration&from=2026-02-01&to=2026-02-01')->assertOk()->assertSee('June warehouse');
            $this->get('/reports/units?date=change&'.$dates)->assertOk()->assertSee(__('reports.empty'));
            $this->get('/reports/units?date=change&from=2026-06-15&to=2026-06-15')->assertOk()->assertSee('June warehouse');
            $this->from('/reports/events')->get('/reports/events?from=2026-06-01&to=2026-03-31')->assertSessionHasErrors('to');
        } finally {
            $this->travelBack();
        }
    }

    public function test_pagination_keeps_the_filter_and_download_includes_all_matching_pages(): void
    {
        $local = Fixture::tree();
        $admin = Fixture::actor($local)->assignRole(IdentityAccess::SUPER_ADMIN);
        $type = AssignmentFixture::type();
        for ($number = 1; $number <= 21; $number++) {
            app(RegisterAsset::class)($admin, [
                'name' => 'Report page fixture '.$number, 'physical_location_id' => $local['location'], 'location_id' => $local['location'],
                'asset_type_id' => $type->id, 'category_id' => $type->category_id, 'purchase_date' => '2026-01-15', 'acquisition_cost' => '0',
            ]);
        }
        $filters = 'q=Report+page+fixture&branch='.$local['branch'].'&state=active';
        $first = $this->as($admin)->get('/reports/units?'.$filters)->assertOk()->viewData('rows');
        $this->assertCount(20, $first);
        $this->assertStringContainsString('state=active', $first->nextPageUrl());
        $second = $this->get($first->nextPageUrl())->assertOk()->viewData('rows');
        $this->assertCount(1, $second);
        $download = $this->csvRows($this->get('/reports/units/export/csv?'.$filters.'&page=2')->assertOk()->streamedContent());
        $this->assertSame(array_column([...$first->items(), ...$second->items()], 'cells'), array_slice($download, 1));
    }

    private function register(User $actor, array $tree, string $name, array $assignment): Asset
    {
        $type = AssignmentFixture::type();

        return app(RegisterAsset::class)($actor, $assignment + [
            'name' => $name, 'physical_location_id' => $tree['location'],
            'asset_type_id' => $type->id, 'category_id' => $type->category_id, 'purchase_date' => '2026-01-15', 'acquisition_cost' => '0',
        ]);
    }

    private function move(User $actor, Asset $asset, int $destination, string $reason): void
    {
        app(MoveAssetLocation::class)($actor, $asset, [
            'location_history_id' => DB::table('asset_location_histories')->where('asset_id', $asset->id)->max('id'),
            'physical_location_id' => $destination, 'reason' => $reason,
        ]);
    }

    private function csvRows(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function xlsxRows(string $url): array
    {
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path, ZipArchive::RDONLY));
            $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->close();
            $this->assertSame([], $sheet->xpath('//*[local-name()="f"]'));

            return array_map(fn ($row) => array_map(fn ($cell) => (string) $cell->is->t, iterator_to_array($row->c, false)),
                iterator_to_array($sheet->sheetData->row, false));
        } finally {
            unlink($path);
        }
    }

    private function as(User $actor): static
    {
        return $this->actingAs($actor)->withSession(['auth.version' => $actor->auth_version]);
    }
}
