<?php

namespace Tests\Release;

use App\Actions\Imports\AssetImportBatches;
use App\Actions\Imports\ProcessImportRow;
use App\Jobs\ProcessAssetImport;
use App\Queries\AssetReports;
use App\Queries\Assets;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AssignmentFixture;
use Tests\Support\OrganizationFixtures as Fixture;

class ReleaseLoadTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private startMysqlTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        $this->assertSame('1', getenv('AMS_TEST_MYSQL'));
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
        $this->startMysqlTransaction();
        (new IdentitySeeder)->run();
        Queue::fake([ProcessAssetImport::class]);
    }

    public function test_nine_thousand_units_scoped_reads_and_thousand_row_import(): void
    {
        $tree = Fixture::tree();
        $other = Fixture::tree();
        $type = AssignmentFixture::type();
        $secondType = AssignmentFixture::type();
        $actor = Fixture::actor($tree, 'branch', [Assets::VIEW, Assets::CREATE, Assets::IMPORT]);
        $defaults = DB::table('asset_catalog_defaults')->sole();
        $usedStatus = Fixture::insert('asset_statuses', 'RELEASE-USED', 'Synthetic in use');
        $wornCondition = Fixture::insert('asset_conditions', 'RELEASE-WORN', 'Synthetic worn');
        $this->assertSame(0, DB::table('assets')->count(), 'Release probe requires an empty synthetic test database.');
        for ($start = 1; $start <= 8000; $start += 250) {
            $units = [];
            for ($number = $start; $number < $start + 250; $number++) {
                $units[] = [
                    'asset_code' => sprintf('AST-%06d', $number), 'public_token' => hash('sha256', 'synthetic-'.$number),
                    'asset_type_id' => $number % 3 ? $type->id : $secondType->id,
                    'physical_location_id' => $number % 2 ? $tree['location'] : $other['location'],
                    'status_id' => $number % 3 ? $defaults->status_id : $usedStatus,
                    'condition_id' => $number % 5 ? $defaults->condition_id : $wornCondition,
                    'name' => 'Synthetic unit '.$number, 'purchase_date' => sprintf('2025-%02d-15', $number % 12 + 1), 'acquisition_cost' => '1250.00',
                    'is_active' => true, 'registered_at' => now(), 'updated_at' => now(),
                ];
            }
            DB::table('assets')->insert($units);
            $ids = DB::table('assets')->whereIn('asset_code', array_column($units, 'asset_code'))->get(['id', 'physical_location_id']);
            DB::table('asset_assignments')->insert($ids->map(fn ($unit, $index) => [
                'asset_id' => $unit->id,
                'employee_id' => $index % 3 === 0 ? $actor->employee_id : null,
                'department_id' => $index % 3 === 1 ? $tree['department'] : null,
                'location_id' => $index % 3 === 2 ? $unit->physical_location_id : null,
                'started_at' => now(), 'actor_id' => $actor->id, 'reason' => 'Synthetic release probe',
            ])->all());
        }
        DB::table('asset_number_sequences')->where('key', 'AST')->update(['next_number' => 8001]);
        $this->actingAs($actor)->withSession(['auth.version' => $actor->auth_version]);
        $this->assertSame(4000, Assets::operational($actor)->count());
        $metrics = [];
        foreach (['/workspace', '/assets', '/reports/units', '/reports/groups', '/reports/events'] as $url) {
            $this->get($url)->assertOk();
            $samples = [];
            $counts = [];
            for ($sample = 0; $sample < 3; $sample++) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $started = hrtime(true);
                $this->get($url)->assertOk();
                $samples[] = round((hrtime(true) - $started) / 1_000_000, 2);
                $counts[] = count(DB::getQueryLog());
                DB::disableQueryLog();
            }
            $this->assertLessThan(60, max($counts), $url.' query budget');
            sort($samples);
            $metrics[$url] = ['median_ms' => $samples[1], 'max_ms' => $samples[2], 'query_count' => max($counts)];
        }
        $query = AssetReports::current($actor, []);
        $metrics['explain'] = DB::select('EXPLAIN '.$query->toSql(), $query->getBindings());
        $stream = fopen('php://temp', 'w+');
        $columns = ['asset_code', 'name', 'category_id', 'asset_type_id', 'physical_location_id', 'assignee', 'purchase_date', 'acquisition_cost'];
        fputcsv($stream, $columns, escape: '');
        for ($row = 1; $row <= 1000; $row++) {
            fputcsv($stream, ['', 'Imported probe '.$row, $type->category_id, $type->id, $tree['location'],
                'location:'.$tree['location'], '2026-01-15', '1250.00'], escape: '');
        }
        rewind($stream);
        $file = UploadedFile::fake()->createWithContent('release.csv', stream_get_contents($stream));
        fclose($stream);
        $started = hrtime(true);
        $imports = app(AssetImportBatches::class);
        $batch = $imports->upload($actor, $file);
        $imports->decide($actor, $batch, [], true);
        $worker = new ProcessAssetImport($batch);
        for ($delivery = 0; $delivery < 41; $delivery++) {
            $worker->handle(app(ProcessImportRow::class));
        }
        $metrics['import_1000_seconds'] = round((hrtime(true) - $started) / 1_000_000_000, 2);
        $this->assertSame('finished', DB::table('import_batches')->where('id', $batch)->value('status'));
        $this->assertSame(1000, DB::table('import_rows')->where('import_batch_id', $batch)->where('status', 'succeeded')->count());
        $this->assertSame(9000, DB::table('assets')->count());
        $this->assertSame(9000, DB::table('asset_assignments')->whereNull('ended_at')->count());
        $this->assertSame(5000, Assets::operational($actor)->count());
        $this->assertSame(1000, DB::table('audit_logs')->where('event', 'asset.imported')->count());
        fwrite(STDERR, "\nRELEASE_PROBE ".json_encode($metrics, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    }
}
