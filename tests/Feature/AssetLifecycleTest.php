<?php

namespace Tests\Feature;

use App\Actions\Assets\AssetFiles;
use App\Actions\Assets\AssetLifecycle;
use App\Actions\Assets\ManageAssetAttributes;
use App\Actions\Assets\RegisterAsset;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetCondition;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\OrganizationFixtures as Fixture;

class AssetLifecycleTest extends TestCase
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

    public function test_t015_defaults_are_selected_and_registration_commits_asset_assignment_history_and_audit(): void
    {
        [$actor, $tree, $type] = $this->setupAsset();
        $defaults = ['status_id' => AssetStatus::where('code', 'AVAILABLE')->sole()->id, 'condition_id' => AssetCondition::where('code', 'GOOD')->sole()->id];
        $input = $this->data($tree, $type);
        $sequence = DB::table('asset_number_sequences')->where('key', 'AST')->value('next_number');
        $this->as($actor)->get('/assets/new?type='.$type->id)->assertOk()->assertSee('Tersedia')->assertSee('Baik');
        $this->assertSame($sequence, DB::table('asset_number_sequences')->where('key', 'AST')->value('next_number'));
        $this->post('/assets', ['assignee' => 'department:'.$tree['department']] + $input)->assertRedirect();
        $asset = Asset::query()->where('name', $input['name'])->sole();
        $this->assertSame(sprintf('AST-%06d', $sequence), $asset->asset_code);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $asset->public_token);
        $this->assertSame($defaults, $asset->only(['status_id', 'condition_id']));
        $this->assertSame('0.10', $asset->acquisition_cost);
        $this->assertDatabaseHas('asset_assignments', ['asset_id' => $asset->id, 'department_id' => $tree['department'], 'ended_at' => null]);
        $this->assertDatabaseHas('asset_location_histories', ['asset_id' => $asset->id, 'from_location_id' => null, 'to_location_id' => $tree['location']]);
        $audit = DB::table('audit_logs')->where('asset_id', $asset->id)->sole();
        $this->assertSame('asset.registered', $audit->event);
        $this->assertStringNotContainsString($asset->public_token, $audit->new_values);
        $this->get('/assets/'.$asset->id)->assertOk()->assertSee($asset->asset_code)->assertDontSee($asset->public_token);

        $repair = AssetStatus::query()->create(['code' => 'REPAIR', 'name' => 'Repair']);
        $this->post('/assets', ['assignee' => 'location:'.$tree['location'], 'name' => 'Override status', 'status_id' => $repair->id] + $input)->assertRedirect();
        $this->assertDatabaseHas('assets', ['name' => 'Override status', 'status_id' => $repair->id, 'is_active' => 1]);
        $assignmentId = $asset->activeAssignment()->sole()->id;
        $this->put('/assets/'.$asset->id.'/state', ['status_id' => $repair->id, 'condition_id' => $asset->condition_id, 'reason' => 'Diagnostics'])->assertRedirect();
        $this->assertTrue($asset->fresh()->is_active);
        $this->assertSame($assignmentId, $asset->activeAssignment()->sole()->id);
        $this->assertDatabaseHas('audit_logs', ['asset_id' => $asset->id, 'event' => 'asset.state_changed', 'reason' => 'Diagnostics']);
    }

    public function test_registration_persists_validated_attribute_version_and_keeps_it_visible_after_archival(): void
    {
        [$actor, $tree, $type] = $this->setupAsset();
        $attributes = app(ManageAssetAttributes::class);
        $definition = $attributes->save($actor, $type, null, [
            'key' => 'finish', 'label' => 'Original finish', 'data_type' => 'select', 'options' => "Silver\nBlack",
            'is_required' => 1, 'sort_order' => 1,
        ]);
        $this->as($actor);
        $input = ['assignee' => 'department:'.$tree['department']] + $this->data($tree, $type);
        $this->post('/assets', $input)->assertSessionHasErrors('attributes.finish');
        $this->post('/assets', ['attributes' => ['finish' => 'Silver']] + $input)->assertRedirect();
        $asset = Asset::query()->where('asset_type_id', $type->id)->sole();
        $attributes->archive($actor, $type, $definition->id);
        $stored = DB::table('asset_attribute_values')->where('asset_id', $asset->id)->sole();
        $this->assertSame($definition->id, $stored->attribute_definition_id);
        $this->assertSame('Silver', json_decode($stored->value_json, true));
        $this->get('/assets/'.$asset->id)->assertOk()->assertSee('Original finish')->assertSee('Silver');
    }

    public function test_t002_missing_assignment_invalid_cost_and_assignment_insert_failure_leave_no_asset_or_sequence_gap(): void
    {
        [$actor, $tree, $type] = $this->setupAsset();
        $action = app(RegisterAsset::class);
        $data = $this->data($tree, $type);
        $before = DB::table('asset_number_sequences')->where('key', 'AST')->value('next_number');
        $this->invalid(fn () => $action($actor, $data), 'assignee');
        $this->invalid(fn () => $action($actor, ['employee_id' => $actor->employee_id, 'location_id' => $tree['location']] + $data), 'assignee');
        $this->invalid(fn () => $action($actor, ['acquisition_cost' => '-0.10'] + $data), 'acquisition_cost');
        $this->invalid(fn () => $action($actor, ['purchase_date' => null] + $data), 'purchase_date');

        Storage::fake(AssetFiles::DISK);
        $this->failWrite('asset_assignments', fn () => $action($actor, ['department_id' => $tree['department']] + $data,
            ['photo' => UploadedFile::fake()->image('picture.png', 10, 10)]));

        $this->assertDatabaseMissing('assets', ['name' => $data['name']]);
        $this->assertSame($before, DB::table('asset_number_sequences')->where('key', 'AST')->value('next_number'));
        $this->assertSame([], Storage::disk(AssetFiles::DISK)->allFiles());
    }

    public function test_t004_t005_lifecycle_rolls_back_on_audit_failure_and_requires_new_assignment(): void
    {
        [$actor, $tree, $type] = $this->setupAsset();
        $asset = app(RegisterAsset::class)($actor, ['department_id' => $tree['department']] + $this->data($tree, $type));
        $original = $asset->activeAssignment()->sole();
        $lifecycle = app(AssetLifecycle::class);
        $this->invalid(fn () => $lifecycle->deactivate($actor, $asset, ['reason' => '   ']), 'reason');
        $this->failWrite('audit_logs', fn () => $lifecycle->deactivate($actor, $asset, ['reason' => 'Retired']));
        $this->assertTrue($asset->fresh()->is_active);
        $this->assertNull($original->fresh()->ended_at);
        $this->assertDatabaseMissing('asset_deactivations', ['asset_id' => $asset->id]);

        $this->as($actor)->post('/assets/'.$asset->id.'/deactivate', ['reason' => 'Retired'])->assertRedirect();
        $this->assertFalse($asset->fresh()->is_active);
        $this->assertNotNull($original->fresh()->ended_at);
        $this->assertSame($asset->status_id, $asset->fresh()->status_id);
        $qr = '/q/'.$asset->public_token;
        $this->get($qr)->assertOk()->assertViewHas('fields', fn (array $fields): bool => $fields['status'] === __('assets.inactive'));
        $this->invalid(fn () => $lifecycle->updateState($actor, $asset, []), 'is_active');
        $state = $asset->only(['status_id', 'condition_id']) + ['reason' => 'Returned'];
        $this->invalid(fn () => $lifecycle->reactivate($actor, $asset, $state), 'assignee');
        $this->assertFalse($asset->fresh()->is_active);
        $this->assertNull($asset->activeAssignment()->first());

        $this->post('/assets/'.$asset->id.'/reactivate', ['assignee' => 'location:'.$tree['location']] + $state)->assertRedirect();
        $fresh = $asset->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertSame($asset->asset_code, $fresh->asset_code);
        $this->assertSame($asset->public_token, $fresh->public_token);
        $this->get($qr)->assertOk()->assertViewHas('fields', fn (array $fields): bool => $fields['status'] === $fresh->status->name);
        $this->assertNotSame($original->id, $fresh->activeAssignment()->sole()->id);
        $this->assertSame($tree['location'], $fresh->activeAssignment()->sole()->location_id);
        $this->assertNotNull($original->fresh()->ended_at);
    }

    public function test_mysql_rejects_duplicate_active_assignment_multiple_targets_and_negative_cost(): void
    {
        [$actor, $tree, $type] = $this->setupAsset();
        $asset = app(RegisterAsset::class)($actor, ['department_id' => $tree['department']] + $this->data($tree, $type));
        $base = ['asset_id' => $asset->id, 'actor_id' => $actor->id, 'started_at' => now(), 'reason' => 'Constraint test'];
        $this->dbRejects(fn () => DB::table('asset_assignments')->insert($base + ['employee_id' => $actor->employee_id]), 1062);
        $this->dbRejects(fn () => DB::table('asset_assignments')->insert($base + [
            'department_id' => $tree['department'], 'employee_id' => $actor->employee_id, 'ended_at' => now()->addDay(),
        ]), 3819);
        $this->dbRejects(fn () => DB::table('assets')->where('id', $asset->id)->update(['acquisition_cost' => '-0.01']), 3819);
    }

    public function test_private_attachments_validate_content_and_asset_scope_for_download(): void
    {
        [$actor, $tree, $type] = $this->setupAsset();
        $other = Fixture::tree();
        $holder = Fixture::actor($other, 'branch', [Assets::VIEW, Assets::LIFECYCLE]);
        $unrelated = Fixture::actor($other, 'branch', [Assets::VIEW, Assets::CREATE]);
        $inputs = ['employee_id' => $holder->employee_id] + $this->data($tree, $type);
        $register = app(RegisterAsset::class);
        Storage::fake(AssetFiles::DISK);

        $this->invalid(fn () => $register($actor, $inputs, ['photo' => UploadedFile::fake()->createWithContent('bad.png', 'plain text')]), 'photo');
        $this->invalid(fn () => $register($actor, $inputs, ['invoice' => UploadedFile::fake()->createWithContent('bad.pdf', "%PDF-1.7\nbroken\n%%EOF")]), 'invoice');
        $this->invalid(fn () => $register($actor, $inputs, ['photo' => UploadedFile::fake()->image('big.png')->size(5121)]), 'photo');
        $asset = $register($actor, $inputs, [
            'photo' => UploadedFile::fake()->image('user-name.png', 10, 10),
            'invoice' => UploadedFile::fake()->createWithContent('secret-name.pdf', $this->pdf()),
        ]);
        $invoice = $asset->attachments()->where('kind', 'invoice')->sole();
        $this->assertStringNotContainsString('secret-name', $invoice->storage_path);
        $this->assertSame('application/pdf', $invoice->mime_type);
        Storage::disk(AssetFiles::DISK)->assertExists($invoice->storage_path);
        $path = '/assets/'.$asset->id.'/attachments/'.$invoice->id;
        $this->get($path)->assertRedirect('/login');
        $this->as($unrelated)->get('/assets')->assertOk()->assertDontSee($asset->asset_code);
        $this->get($path)->assertForbidden();
        $this->post('/assets', ['assignee' => 'employee:'.$holder->employee_id] + $this->data($tree, $type))->assertForbidden();
        $this->as($holder)->get('/assets/'.$asset->id)->assertOk();
        $this->get($path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertDownload($asset->asset_code.'-invoice.pdf');
        $this->post('/assets/'.$asset->id.'/deactivate', ['reason' => 'Not my operational scope'])->assertForbidden();
        $this->as($actor)->post('/assets/'.$asset->id.'/deactivate', [
            'reason' => 'Archived', 'deactivation' => UploadedFile::fake()->image('deactivation.png', 10, 10),
        ])->assertRedirect();
        $this->assertNotNull(DB::table('asset_deactivations')->where('asset_id', $asset->id)->sole()->attachment_id);
        $this->as($holder)->get($path)->assertForbidden();
    }

    private function setupAsset(): array
    {
        $tree = Fixture::tree();
        $actor = Fixture::actor($tree)->assignRole(IdentityAccess::SUPER_ADMIN);
        $category = AssetCategory::query()->create(['code' => 'DEVICES', 'name' => 'Devices']);
        $type = AssetType::query()->create(['code' => 'MONITOR', 'name' => 'Monitor', 'category_id' => $category->id]);

        return [$actor, $tree, $type];
    }

    private function data(array $tree, AssetType $type): array
    {
        return ['name' => 'Monitor QA', 'asset_type_id' => $type->id, 'category_id' => $type->category_id,
            'physical_location_id' => $tree['location'], 'purchase_date' => '2026-01-15', 'acquisition_cost' => '0.10'];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.version' => $user->auth_version]);
    }

    private function invalid(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Invalid input was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function dbRejects(callable $operation, int $code): void
    {
        try {
            $operation();
            $this->fail('MySQL accepted an invalid write.');
        } catch (QueryException $exception) {
            $this->assertSame($code, (int) $exception->errorInfo[1]);
        }
    }

    private function failWrite(string $table, callable $operation): void
    {
        $enabled = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$enabled, $table): void {
            if ($enabled && str_starts_with($sql, 'insert into '.chr(96).$table.chr(96))) {
                throw new RuntimeException('Injected write failure.');
            }
        });

        try {
            $operation();
            $this->fail('The injected write failure was not triggered.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected write failure.', $exception->getMessage());
        } finally {
            $enabled = false;
        }
    }

    private function pdf(): string
    {
        $body = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pages = strlen($body);
        $body .= "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n";
        $xref = strlen($body);

        return $body."xref\n0 3\n0000000000 65535 f \n0000000009 00000 n \n".sprintf("%010d 00000 n \n", $pages)
            ."trailer\n<< /Size 3 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
