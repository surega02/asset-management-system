<?php

namespace App\Actions\Assets;

use App\Models\AssetType;
use App\Models\AttributeDefinition;
use App\Models\User;
use App\Support\AssetAttributeType;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ManageAssetAttributes
{
    public function save(User $actor, AssetType $type, ?int $previousId, array $input): AttributeDefinition
    {
        return DB::transaction(function () use ($actor, $type, $previousId, $input): AttributeDefinition {
            $actor = IdentityAccess::lockScopedActor($actor);
            $type = AssetType::query()->lockForUpdate()->findOrFail($type->id);
            Gate::forUser($actor)->authorize('update', $type);
            if (! $type->is_active) {
                throw ValidationException::withMessages(['label' => __('asset_catalog.inactive_type')]);
            }

            $previous = $previousId === null ? null : $this->definition($type, $previousId);
            $data = Validator::make($input, [
                'key' => $previous ? ['missing'] : ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_]*\z/'],
                'label' => ['required', 'string', 'max:255'],
                'data_type' => ['required', Rule::enum(AssetAttributeType::class)],
                'options' => ['nullable', 'string', 'max:6000'],
                'min' => ['nullable', 'string', 'max:20'],
                'max' => ['nullable', 'string', 'max:20'],
                'is_required' => ['required', 'boolean'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ], attributes: __('asset_catalog.fields'))->validate();
            $dataType = AssetAttributeType::from($data['data_type']);
            $key = $previous?->key ?? $data['key'];
            $latest = AttributeDefinition::query()->where('asset_type_id', $type->id)->where('key', $key)
                ->orderByDesc('version')->lockForUpdate()->first();
            if ($previous === null && $latest?->is_active) {
                throw ValidationException::withMessages(['key' => __('asset_catalog.duplicate_key')]);
            }

            $options = $this->options($dataType, $data['options'] ?? '');
            $bounds = $this->bounds($dataType, array_filter(array_intersect_key($data, ['min' => true, 'max' => true]), fn ($value) => $value !== null && $value !== ''));
            if ($previous !== null) {
                $this->archiveDefinition($actor, $previous);
            }

            $definition = AttributeDefinition::query()->create([
                'asset_type_id' => $type->id, 'key' => $key, 'version' => ($latest?->version ?? 0) + 1,
                'label' => $data['label'], 'data_type' => $dataType->value,
                'allowed_options' => $options, 'validation_rules' => $bounds, 'is_active' => true,
            ]);
            $configuration = $definition->configuration()->create([
                'asset_type_id' => $type->id, 'is_required' => (bool) $data['is_required'], 'sort_order' => $data['sort_order'],
            ]);
            IdentityAudit::record($actor->id, $definition, 'asset-attribute.version_created', 'asset-catalog',
                new: $definition->toArray() + $configuration->toArray());

            return $definition;
        }, 3);
    }

    public function archive(User $actor, AssetType $type, int $definitionId): void
    {
        DB::transaction(function () use ($actor, $type, $definitionId): void {
            $actor = IdentityAccess::lockScopedActor($actor);
            $type = AssetType::query()->lockForUpdate()->findOrFail($type->id);
            Gate::forUser($actor)->authorize('update', $type);
            $this->archiveDefinition($actor, $this->definition($type, $definitionId));
        }, 3);
    }

    private function definition(AssetType $type, int $id): AttributeDefinition
    {
        $definition = AttributeDefinition::query()->where('asset_type_id', $type->id)->lockForUpdate()->findOrFail($id);
        if (! $definition->is_active) {
            throw ValidationException::withMessages(['label' => __('asset_catalog.stale_version')]);
        }

        return $definition;
    }

    private function archiveDefinition(User $actor, AttributeDefinition $definition): void
    {
        $before = $definition->toArray();
        $definition->update(['is_active' => false]);
        IdentityAudit::record($actor->id, $definition, 'asset-attribute.archived', 'asset-catalog', $before, $definition->toArray());
    }

    private function options(AssetAttributeType $type, string $input): ?array
    {
        $options = array_values(array_filter(array_map('trim', preg_split('/\R/u', $input) ?: []), fn ($option) => $option !== ''));
        if ($type !== AssetAttributeType::Select && $options === []) {
            return null;
        }
        if ($type !== AssetAttributeType::Select || $options === [] || count($options) > 50 || count(array_unique($options)) !== count($options)
            || array_filter($options, fn ($option) => mb_strlen($option) > 100 || str_contains($option, "\0"))) {
            throw ValidationException::withMessages(['options' => __('asset_catalog.invalid_options')]);
        }

        return $options;
    }

    private function bounds(AssetAttributeType $type, array $input): ?array
    {
        if ($input === []) {
            return null;
        }

        try {
            if (! $type->hasBounds()) {
                throw new InvalidArgumentException;
            }
            $values = array_map($type->bound(...), $input);
            if (isset($values['min'], $values['max']) && $values['min'] > $values['max']) {
                throw new InvalidArgumentException;
            }
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['min' => __('asset_catalog.invalid_bounds')]);
        }

        return $input;
    }
}
