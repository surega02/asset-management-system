<?php

namespace App\Actions\Assets;

use App\Models\AssetCatalogDefault;
use App\Models\AssetCondition;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\AttributeDefinition;
use App\Support\AssetAttributeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

final class ResolveAssetCatalog
{
    /**
     * Preview uses normal reads. Registration and import execution call this again
     * inside their authorized asset write transaction with forWrite enabled.
     *
     * @return array{asset_type_id: int, status_id: int, condition_id: int, attributes: array<int, string|int|bool>}
     */
    public function resolve(int $categoryId, int $typeId, array $values, ?int $statusId = null, ?int $conditionId = null, bool $forWrite = false): array
    {
        if ($forWrite && DB::transactionLevel() === 0) {
            throw new LogicException('Catalog write validation requires a transaction.');
        }

        $defaults = AssetCatalogDefault::query()->when($forWrite, fn ($query) => $query->sharedLock())->findOrFail(AssetCatalogDefault::ID);
        $type = $this->type($categoryId, $typeId, $forWrite);

        $statusId ??= $defaults->status_id;
        $conditionId ??= $defaults->condition_id;
        if (! AssetStatus::query()->whereKey($statusId)->where('is_active', true)->when($forWrite, fn ($query) => $query->sharedLock())->first(['id'])) {
            throw ValidationException::withMessages(['status_id' => __('asset_catalog.invalid_status')]);
        }
        if (! AssetCondition::query()->whereKey($conditionId)->where('is_active', true)->when($forWrite, fn ($query) => $query->sharedLock())->first(['id'])) {
            throw ValidationException::withMessages(['condition_id' => __('asset_catalog.invalid_condition')]);
        }

        return [
            'asset_type_id' => $type->id, 'status_id' => $statusId, 'condition_id' => $conditionId,
            'attributes' => $this->attributes($type->id, $values, $forWrite),
        ];
    }

    public function type(int $categoryId, int $typeId, bool $forWrite): AssetType
    {
        $type = AssetType::query()->where('category_id', $categoryId)->where('is_active', true)
            ->when($forWrite, fn ($query) => $query->sharedLock())->find($typeId);
        if (! $type || ! $type->category()->where('is_active', true)->when($forWrite, fn ($query) => $query->sharedLock())->first(['id'])) {
            throw ValidationException::withMessages(['asset_type_id' => __('asset_catalog.invalid_type')]);
        }

        return $type;
    }

    public function attributes(int $typeId, array $values, bool $forWrite): array
    {
        $definitions = $this->definitionQuery($typeId)
            ->when($forWrite, fn ($query) => $query->sharedLock())
            ->get()->keyBy('key');
        $errors = [];
        $result = [];
        if (array_diff_key($values, $definitions->all()) !== []) {
            $errors['attributes'] = __('asset_catalog.unknown_attributes');
        }

        foreach ($definitions as $key => $definition) {
            $value = $values[$key] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                if ($definition->is_required) {
                    $errors['attributes.'.$key] = __('asset_catalog.required_attribute', ['label' => $definition->label]);
                }

                continue;
            }

            try {
                $result[$definition->id] = AssetAttributeType::from($definition->data_type)->normalize(
                    $value, $definition->validation_rules ?? [], $definition->allowed_options ?? []);
            } catch (InvalidArgumentException) {
                $errors['attributes.'.$key] = __('asset_catalog.invalid_attribute', ['label' => $definition->label]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $result;
    }

    /** Display fields for a type already authorized by the caller. */
    public function fields(int $typeId): Collection
    {
        return $this->definitionQuery($typeId)->get();
    }

    private function definitionQuery(int $typeId): Builder
    {
        return AttributeDefinition::query()
            ->join('asset_type_attributes as configuration', 'configuration.attribute_definition_id', '=', 'attribute_definitions.id')
            ->where('attribute_definitions.asset_type_id', $typeId)->where('attribute_definitions.is_active', true)
            ->select('attribute_definitions.*', 'configuration.is_required', 'configuration.sort_order')
            ->orderBy('configuration.sort_order')->orderBy('attribute_definitions.id');
    }
}
