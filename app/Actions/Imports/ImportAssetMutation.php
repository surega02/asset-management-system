<?php

namespace App\Actions\Imports;

use App\Actions\Assets\AssetInput;
use App\Actions\Assets\AssetLifecycle;
use App\Actions\Assets\AssetTargets;
use App\Actions\Assets\MoveAssetLocation;
use App\Actions\Assets\ReassignAsset;
use App\Actions\Assets\RegisterAsset;
use App\Actions\Assets\ResolveAssetCatalog;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetStatus;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ImportAssetMutation
{
    public function __construct(
        private AssetTargets $targets,
        private ResolveAssetCatalog $catalog,
        private RegisterAsset $register,
        private MoveAssetLocation $move,
        private ReassignAsset $reassign,
        private AssetLifecycle $lifecycle,
    ) {}

    public function candidate(User $actor, ?array $payload): string
    {
        if ($payload === null) {
            return 'skip';
        }
        $code = $this->code($payload);
        if ($code !== '') {
            $asset = $this->matchedAsset($actor, $code);

            return $asset && Gate::forUser($actor)->allows('update', $asset) ? 'update' : 'skip';
        }

        $location = $payload['physical_location_id'] ?? '';
        $locationId = filter_var($location, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return Gate::forUser($actor)->allows('create', [Asset::class, $locationId ?: null]) ? 'create' : 'skip';
    }

    public function preview(User $actor, array $payload, string $action): void
    {
        if ($action === 'create') {
            $data = $this->createData($actor, $payload);
            AssetInput::validateReferences($data, forWrite: false);
            if (! Assets::locations($actor, Assets::CREATE)->whereKey($data['physical_location_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['physical_location_id' => __('assets.invalid_location')]);
            }
            $this->targets->validate($actor, Assets::CREATE, $data, forWrite: false);
            $this->catalog->resolve((int) $data['category_id'], (int) $data['asset_type_id'], $data['attributes'],
                isset($data['status_id']) ? (int) $data['status_id'] : null,
                isset($data['condition_id']) ? (int) $data['condition_id'] : null);
        } elseif ($action === 'update') {
            $this->updatePlan($actor, $payload, forWrite: false);
        }
    }

    public function apply(User $actor, array $payload, string $action): Asset
    {
        Gate::forUser($actor)->authorize('import', Asset::class);
        if ($action === 'create') {
            return ($this->register)($actor, $this->createData($actor, $payload));
        }
        abort_unless($action === 'update', 422);
        [$asset, $metadata, $attributes, $state, $target, $destination, $reason] = $this->updatePlan($actor, $payload, forWrite: true);

        if ($target !== []) {
            ($this->reassign)($actor, $asset, $target + ['assignment_id' => $asset->activeAssignment->id, 'reason' => $reason]);
        }
        if ($state !== []) {
            $this->lifecycle->updateState($actor, $asset, $state + ['reason' => $reason]);
        }
        if ($destination !== null) {
            $historyId = DB::table('asset_location_histories')->where('asset_id', $asset->id)
                ->orderByDesc('changed_at')->orderByDesc('id')->value('id');
            ($this->move)($actor, $asset, ['physical_location_id' => $destination, 'location_history_id' => $historyId, 'reason' => $reason]);
        }
        $asset->refresh();
        $before = $asset->getAttributes();
        $asset->fill($metadata);
        if ($asset->isDirty() || $attributes !== []) {
            $asset->save();
            foreach ($attributes as $id => $value) {
                DB::table('asset_attribute_values')->updateOrInsert(
                    ['asset_id' => $asset->id, 'attribute_definition_id' => $id],
                    ['value_json' => json_encode($value, JSON_THROW_ON_ERROR)],
                );
            }
            IdentityAudit::record($actor->id, $asset, 'asset.updated', $reason, $before,
                $asset->getAttributes() + ['attribute_definition_ids' => array_keys($attributes)]);
        }

        return $asset;
    }

    private function createData(User $actor, array $payload): array
    {
        if ($this->code($payload) !== '') {
            throw ValidationException::withMessages(['asset_code' => __('asset_import.create_requires_blank')]);
        }
        $data = AssetInput::validate($this->fields($payload));
        Gate::forUser($actor)->authorize('create', [Asset::class, (int) $data['physical_location_id']]);

        return $data + AssetTargets::decodeSelection($payload['assignee'] ?? null);
    }

    private function updatePlan(User $actor, array $payload, bool $forWrite): array
    {
        $asset = $this->matchedAsset($actor, $this->code($payload), $forWrite);
        if ($asset === null) {
            throw new AuthorizationException;
        }
        $gate = Gate::forUser($actor);
        $gate->authorize('update', $asset);
        $reason = Validator::make($payload, ['reason' => ['required', 'string', 'max:100']],
            attributes: ['reason' => __('people.fields.reason')])->validate()['reason'];
        $data = AssetInput::validate($this->fields($payload), partial: true);
        AssetInput::validateReferences($data, $forWrite);
        $target = [];
        $asset->load('activeAssignment');
        if (($payload['assignee'] ?? '') !== '') {
            $requested = AssetTargets::decodeSelection($payload['assignee']);
            $key = array_key_first($requested);
            if ($asset->activeAssignment?->{$key} !== (int) $requested[$key]) {
                $gate->authorize('reassign', $asset);
                $target = $this->targets->validate($actor, Assets::REASSIGN, $requested, $forWrite);
            }
        }

        $destination = null;
        if (isset($data['physical_location_id']) && (int) $data['physical_location_id'] !== $asset->physical_location_id) {
            $destination = (int) $data['physical_location_id'];
            $gate->authorize('move', [$asset, $destination]);
            if (! Assets::locations($actor, Assets::MOVE)->whereKey($destination)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['physical_location_id' => __('assets.invalid_location')]);
            }
        }

        $state = $this->stateChanges($actor, $asset, $data);
        $attributes = $this->attributeChanges($asset, $data, $forWrite);
        $metadata = Arr::except($data, ['category_id', 'physical_location_id', 'attributes', 'status_id', 'condition_id']);

        return [$asset, $metadata, $attributes, $state, $target, $destination, $reason];
    }

    private function stateChanges(User $actor, Asset $asset, array $data): array
    {
        $state = [];
        foreach (['status_id', 'condition_id'] as $field) {
            if (isset($data[$field]) && (int) $data[$field] !== $asset->{$field}) {
                $state[$field] = (int) $data[$field];
            }
        }
        if ($state !== []) {
            Gate::forUser($actor)->authorize('updateState', $asset);
            $state += $asset->only(['status_id', 'condition_id']);
            foreach (['status_id' => AssetStatus::class, 'condition_id' => AssetCondition::class] as $field => $model) {
                if (! $model::query()->where('is_active', true)->whereKey($state[$field])->exists()) {
                    throw ValidationException::withMessages([$field => __('assets.invalid_reference')]);
                }
            }
        }

        return $state;
    }

    private function attributeChanges(Asset $asset, array $data, bool $forWrite): array
    {
        if (! isset($data['category_id']) && ! isset($data['asset_type_id']) && $data['attributes'] === []) {
            return [];
        }
        $typeId = (int) ($data['asset_type_id'] ?? $asset->asset_type_id);
        $categoryId = (int) ($data['category_id'] ?? $asset->type->category_id);
        $this->catalog->type($categoryId, $typeId, $forWrite);
        $previous = DB::table('asset_attribute_values as value')->where('value.asset_id', $asset->id)
            ->join('attribute_definitions as definition', 'definition.id', '=', 'value.attribute_definition_id')
            ->join('asset_type_attributes as config', 'config.attribute_definition_id', '=', 'definition.id')
            ->where('definition.asset_type_id', $typeId)->where('definition.is_active', true)
            ->get(['definition.id', 'definition.key', 'value.value_json']);
        $byId = [];
        $byKey = [];
        foreach ($previous as $value) {
            $byId[$value->id] = $byKey[$value->key] = json_decode($value->value_json, true, flags: JSON_THROW_ON_ERROR);
        }
        $resolved = $this->catalog->attributes($typeId, $data['attributes'] + $byKey, $forWrite);

        return array_filter($resolved, fn ($value, $id) => ! array_key_exists($id, $byId) || $byId[$id] !== $value, ARRAY_FILTER_USE_BOTH);
    }

    private function matchedAsset(User $actor, string $code, bool $lock = false): ?Asset
    {
        if (! preg_match('/\AAST-[0-9]{6,19}\z/', $code)) {
            return null;
        }

        return Assets::operational($actor, Assets::UPDATE)->where('asset_code', $code)
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();
    }

    private function code(array $payload): string
    {
        return strtoupper($payload['asset_code'] ?? '');
    }

    private function fields(array $payload): array
    {
        $data = array_filter($payload, fn (string $value) => $value !== '');
        $data['attributes'] = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'attr.')) {
                $data['attributes'][substr($key, 5)] = $value;
            }
        }

        return $data;
    }
}
