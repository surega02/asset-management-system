<?php

namespace Tests\Feature;

use App\Actions\Assets\RegisterAsset;
use App\Actions\Imports\AssetImportBatches;
use App\Actions\Imports\AssetImportSheet;
use App\Actions\Imports\ProcessImportRow;
use App\Jobs\ProcessAssetImport;
use App\Models\Asset;
use App\Models\ImportRow;
use App\Models\User;
use App\Queries\Assets;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\AssignmentFixture;
use Tests\Support\OrganizationFixtures as Fixture;
use ZipArchive;

class AssetImportTest extends TestCase
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
        Queue::fake([ProcessAssetImport::class]);
    }

    public function test_t010_preview_is_read_only_and_92_successes_survive_five_failures_and_three_skips(): void
    {
        [$actor, $tree, $data] = $this->fixture();
        $other = Fixture::actor($tree, 'branch', [Assets::VIEW, Assets::IMPORT, Assets::CREATE]);
        $definition = DB::table('attribute_definitions')->insertGetId([
            'asset_type_id' => $data['asset_type_id'], 'key' => 'capacity', 'version' => 1,
            'label' => 'Capacity', 'data_type' => 'integer',
        ]);
        DB::table('asset_type_attributes')->insert([
            'asset_type_id' => $data['asset_type_id'], 'attribute_definition_id' => $definition, 'is_required' => true,
        ]);
        $sequence = DB::table('asset_number_sequences')->where('key', 'AST')->value('next_number');
        $rows = [];
        for ($i = 1; $i <= 100; $i++) {
            $rows[] = array_replace($data, [
                'name' => $i <= 2 ? 'Identical model, separate units' : ($i === 93 ? '=1+2' : 'Imported unit '.$i),
                'acquisition_cost' => $i >= 93 && $i <= 97 ? '-10' : $data['acquisition_cost'],
                'attr.capacity' => (string) $i,
            ]);
        }
        $this->as($actor)->post('/assets/import', ['file' => $this->csv($rows)])->assertRedirect();
        $batch = DB::table('import_batches')->where('user_id', $actor->id)->sole();
        $base = '/assets/import/'.$batch->id;
        $this->get($base)->assertOk()->assertSee(__('asset_import.similar'))->assertDontSee(sprintf('AST-%06d', $sequence));
        $this->assertSame($sequence, DB::table('asset_number_sequences')->where('key', 'AST')->value('next_number'));
        $this->as($other)->get($base)->assertNotFound();
        $this->get($base.'/errors')->assertNotFound();
        $this->as($actor);

        $skip = $this->rows($batch->id)->where('row_number', '>=', 99)->pluck('id')->mapWithKeys(fn ($id) => [$id => 'skip']);
        $this->post($base.'/choices', ['choices' => $skip->all(), 'intent' => 'confirm'])->assertRedirect();
        Queue::assertPushed(ProcessAssetImport::class, fn (ProcessAssetImport $job): bool => $job->connection === 'database');
        $this->post($base.'/choices', ['intent' => 'confirm'])->assertConflict();
        $this->drain($batch->id);
        $counts = $this->rows($batch->id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();
        ksort($counts);
        $this->assertSame(['failed' => 5, 'skipped' => 3, 'succeeded' => 92], $counts);
        $this->assertSame(92, Asset::query()->where('physical_location_id', $tree['location'])->count());
        $this->assertSame(92, DB::table('asset_attribute_values')->where('attribute_definition_id', $definition)->count());
        $this->assertSame(92, DB::table('audit_logs')->where('actor_id', $actor->id)->where('event', 'asset.imported')->count());
        $this->assertSame($sequence + 92, DB::table('asset_number_sequences')->where('key', 'AST')->value('next_number'));
        $this->get($base)->assertOk()->assertSee(__('asset_import.finished'));
        $this->assertStringContainsString("'=1+2", $this->get($base.'/errors')->assertOk()->streamedContent());

        $failed = $this->rows($batch->id)->where('row_number', 94)->sole();
        $retry = $base.'/rows/'.$failed->id.'/retry';
        $this->post($retry, ['attempt' => 1, 'values' => ['acquisition_cost' => '10.00']])->assertRedirect();
        $this->post($retry, ['attempt' => 1])->assertConflict();
        $this->drain($batch->id);
        $this->assertSame(['failed', 'succeeded'], DB::table('import_row_attempts')->where('import_row_id', $failed->id)
            ->orderBy('attempt_number')->pluck('outcome')->all());
        $this->get($base.'?page=5')->assertOk()->assertSee(__('asset_import.attempt_history'));
        $succeeded = $this->rows($batch->id)->where('row_number', 2)->sole();
        $this->post($base.'/rows/'.$succeeded->id.'/retry', ['attempt' => 1])->assertConflict();
    }

    public function test_t009_rolls_back_before_success_and_fences_expired_and_duplicate_deliveries(): void
    {
        [$actor, $tree, $data] = $this->fixture();
        $imports = app(AssetImportBatches::class);
        $batch = $imports->upload($actor, $this->csv([$data, array_replace($data, ['name' => 'Second unit'])]));
        $imports->decide($actor, $batch, [], true);
        $processor = app(ProcessImportRow::class);
        $oldClaim = $processor->claim($batch);
        $this->assertNotNull($oldClaim);
        $this->assertNull($processor->claim($batch));

        $armed = true;
        DB::listen(function (QueryExecuted $query) use (&$armed): void {
            if ($armed && str_starts_with($query->sql, 'update `import_rows`') && in_array('succeeded', $query->bindings, true)) {
                $armed = false;
                throw new RuntimeException('Simulated worker interruption before commit');
            }
        });
        try {
            $processor->execute($oldClaim);
            $this->fail('Simulated interruption did not occur');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated worker interruption before commit', $exception->getMessage());
        }
        $this->assertSame(0, Asset::query()->where('physical_location_id', $tree['location'])->count());
        $this->assertSame(0, DB::table('audit_logs')->where('actor_id', $actor->id)->count());

        $this->travel(ProcessImportRow::LEASE_SECONDS + 1)->seconds();
        $imports->resumeDue();
        $reclaimed = $processor->claim($batch);
        $this->assertSame($oldClaim->id, $reclaimed->id);
        $this->assertSame(2, $reclaimed->attempt_count);
        $processor->execute($oldClaim);
        $this->assertSame(0, Asset::query()->where('physical_location_id', $tree['location'])->count());
        $processor->execute($reclaimed);
        $processor->execute($reclaimed);
        $this->drain($batch);
        $this->drain($batch);
        $this->assertSame(2, Asset::query()->where('physical_location_id', $tree['location'])->count());
        $this->assertSame(2, DB::table('audit_logs')->where('actor_id', $actor->id)->where('event', 'asset.imported')->count());
        $this->assertSame(['interrupted', 'succeeded'], DB::table('import_row_attempts')->where('import_row_id', $oldClaim->id)
            ->orderBy('attempt_number')->pluck('outcome')->all());
        $this->travelBack();
    }

    public function test_worker_rechecks_scope_and_uses_exact_code_and_domain_actions_for_update(): void
    {
        [$actor, $tree, $data] = $this->fixture();
        $elsewhere = Fixture::tree();
        $destination = Fixture::insert('locations', 'IMP-'.$tree['code'], 'Import move', ['branch_id' => $tree['branch']]);
        $asset = app(RegisterAsset::class)($actor, $data + ['location_id' => $tree['location']]);
        $originalToken = $asset->public_token;
        $imports = app(AssetImportBatches::class);
        $first = $imports->upload($actor, $this->csv([[
            'asset_code' => $asset->asset_code, 'name' => 'Scoped update', 'physical_location_id' => (string) $destination,
            'assignee' => 'location:'.$destination, 'reason' => 'Spreadsheet correction',
        ], [
            'asset_code' => $asset->asset_code, 'name' => 'Forbidden move', 'physical_location_id' => (string) $elsewhere['location'],
            'reason' => 'Out of scope',
        ], [
            'asset_code' => 'AST-999999999999999999', 'name' => 'Unknown code',
        ]]));
        $imports->decide($actor, $first, [], true);
        $this->drain($first);
        $this->assertSame(['succeeded', 'failed', 'skipped'], $this->rows($first)->orderBy('row_number')->pluck('status')->all());
        $this->assertSame('Scoped update', $asset->fresh()->name);
        $this->assertSame($destination, $asset->fresh()->physical_location_id);
        $this->assertSame($destination, $asset->activeAssignment()->sole()->location_id);
        $this->assertSame($originalToken, $asset->fresh()->public_token);
        $this->assertDatabaseHas('audit_logs', ['asset_id' => $asset->id, 'event' => 'asset.location_moved', 'reason' => 'Spreadsheet correction']);
        $this->assertDatabaseHas('audit_logs', ['asset_id' => $asset->id, 'event' => 'asset.reassigned', 'reason' => 'Spreadsheet correction']);

        $second = $imports->upload($actor, $this->csv([['asset_code' => $asset->asset_code, 'name' => 'Scope revoked', 'reason' => 'Test']]));
        $imports->decide($actor, $second, [], true);
        DB::table('user_data_scopes')->where('user_id', $actor->id)->delete();
        $this->drain($second);
        $this->assertSame('failed', $this->rows($second)->sole()->status);
        $this->assertSame('Scoped update', $asset->fresh()->name);
        $this->as($actor->fresh())->get('/assets/import/'.$second.'/errors')->assertForbidden();
    }

    public function test_file_limits_and_xlsx_template_support_plain_cells_but_reject_formulas(): void
    {
        [$actor, , $data] = $this->fixture();
        $this->as($actor)->post('/assets/import', ['file' => UploadedFile::fake()->create('too-large.csv', 10241)])
            ->assertSessionHasErrors('file');
        $this->post('/assets/import', ['file' => $this->csv(array_fill(0, 1001, $data))])->assertSessionHasErrors('file');
        $this->post('/assets/import', ['file' => UploadedFile::fake()->createWithContent('bad.xlsx', 'not an archive')])
            ->assertSessionHasErrors('file');
        $template = $this->get('/assets/import/template/xlsx')->assertOk()->getContent();
        $path = tempnam(storage_path('framework/cache'), 'ams-xlsx-test-');
        try {
            file_put_contents($path, $template);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('</sheetData>',
                '<row r="2"><c r="B2" t="inlineStr"><is><t>XLSX unit</t></is></c><c r="I2"><v>46037</v></c></row>'
                .'<row r="3"><c r="B3"><f>1+2</f><v>3</v></c></row></sheetData>', $sheet));
            $this->assertTrue($zip->close());
            $rows = app(AssetImportSheet::class)->read(new UploadedFile($path, 'template.xlsx', test: true));
        } finally {
            unlink($path);
        }
        $this->assertSame('XLSX unit', $rows[2]['name']);
        $this->assertSame('2026-01-15', $rows[2]['purchase_date']);
        $this->assertNull($rows[3]);
    }

    private function drain(int $batch): void
    {
        $worker = new ProcessAssetImport($batch);
        for ($i = 0; $i < 5; $i++) {
            $worker->handle(app(ProcessImportRow::class));
        }
        $this->assertSame('finished', DB::table('import_batches')->where('id', $batch)->value('status'));
    }

    private function fixture(): array
    {
        $tree = Fixture::tree();
        $type = AssignmentFixture::type();
        $actor = Fixture::actor($tree, 'branch', [Assets::VIEW, Assets::IMPORT, Assets::CREATE, Assets::UPDATE, Assets::MOVE, Assets::REASSIGN]);

        return [$actor, $tree, [
            'asset_code' => '', 'name' => 'Import test unit', 'category_id' => (string) $type->category_id,
            'asset_type_id' => (string) $type->id, 'physical_location_id' => (string) $tree['location'],
            'assignee' => 'location:'.$tree['location'], 'purchase_date' => '2026-01-15', 'acquisition_cost' => '100.00',
        ]];
    }

    private function rows(int $batch): Builder
    {
        return ImportRow::query()->where('import_batch_id', $batch);
    }

    private function csv(array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'w+');
        $columns = array_keys($rows[0]);
        fputcsv($stream, $columns, escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($column) => $row[$column] ?? '', $columns), escape: '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('assets.csv', $content);
    }

    private function as(User $actor): static
    {
        return $this->actingAs($actor)->withSession(['auth.version' => $actor->auth_version]);
    }
}
