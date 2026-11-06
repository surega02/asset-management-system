<?php

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\User;
use App\Models\Vendor;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RegisterAsset
{
    public function __construct(private ResolveAssetCatalog $catalog, private AssetTargets $targets, private AssetFiles $files) {}

    public function __invoke(User $actor, array $input, array $uploads = []): Asset
    {
        Gate::forUser($actor)->authorize('create', Asset::class);
        $id = ['required', 'integer', 'min:1'];
        $optionalId = ['nullable', 'integer', 'min:1'];
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => $id,
            'asset_type_id' => $id,
            'physical_location_id' => $id,
            'status_id' => $optionalId,
            'condition_id' => $optionalId,
            'purchase_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'acquisition_cost' => ['required', 'string', 'regex:/\A[0-9]{1,16}(?:\.[0-9]{1,2})?\z/'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'brand_id' => $optionalId,
            'vendor_id' => $optionalId,
            'warranty_ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'attributes' => ['nullable', 'array'],
        ], attributes: __('assets.fields'))->validate();

        Gate::forUser($actor)->authorize('create', [Asset::class, (int) $data['physical_location_id']]);

        return $this->files->run(Arr::only($uploads, ['photo', 'invoice']), fn (array $files) => DB::transaction(
            fn () => $this->register($actor, $data, Arr::only($input, AssetTargets::KEYS), $files), 3));
    }

    private function register(User $actor, array $data, array $assignment, array $files): Asset
    {
        $actor = IdentityAccess::lockScopedActor($actor);
        Gate::forUser($actor)->authorize('create', [Asset::class, (int) $data['physical_location_id']]);
        $this->targets->physical($actor, (int) $data['physical_location_id'], Assets::CREATE);
        $resolved = $this->catalog->resolve((int) $data['category_id'], (int) $data['asset_type_id'], $data['attributes'] ?? [],
            isset($data['status_id']) ? (int) $data['status_id'] : null, isset($data['condition_id']) ? (int) $data['condition_id'] : null, forWrite: true);
        $target = $this->targets->validate($actor, Assets::CREATE, $assignment);
        foreach (['brand_id' => Brand::class, 'vendor_id' => Vendor::class] as $key => $model) {
            if (isset($data[$key]) && ! $model::query()->where('is_active', true)->sharedLock()->find($data[$key])) {
                throw ValidationException::withMessages([$key => __('assets.invalid_reference')]);
            }
        }

        $number = DB::table('asset_number_sequences')->where('key', 'AST')->lockForUpdate()->sole()->next_number;
        DB::table('asset_number_sequences')->where('key', 'AST')->increment('next_number');
        $asset = Asset::query()->create(Arr::except($data, ['category_id', 'attributes', 'status_id', 'condition_id']) + [
            'asset_code' => sprintf('AST-%06d', $number),
            'public_token' => bin2hex(random_bytes(32)),
            'is_active' => true,
            'status_id' => $resolved['status_id'],
            'condition_id' => $resolved['condition_id'],
        ]);
        $assignment = $asset->activeAssignment()->create($target + [
            'actor_id' => $actor->id, 'started_at' => now(), 'reason' => 'asset-registration',
        ]);
        foreach ($resolved['attributes'] as $definitionId => $value) {
            DB::table('asset_attribute_values')->insert([
                'asset_id' => $asset->id, 'attribute_definition_id' => $definitionId, 'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
            ]);
        }
        DB::table('asset_location_histories')->insert([
            'asset_id' => $asset->id, 'to_location_id' => $asset->physical_location_id,
            'actor_id' => $actor->id, 'reason' => 'asset-registration', 'changed_at' => $asset->registered_at->format('Y-m-d H:i:s.u'),
        ]);
        $ids = $this->files->attach($asset, $actor, $files);
        IdentityAudit::record($actor->id, $asset, 'asset.registered', 'asset-registration', new: $asset->getAttributes() + $target + [
            'assignment_id' => $assignment->id, 'attachment_ids' => $ids, 'attribute_definition_ids' => array_keys($resolved['attributes']),
        ]);

        return $asset;
    }
}
