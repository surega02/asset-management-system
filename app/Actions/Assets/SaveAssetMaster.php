<?php

namespace App\Actions\Assets;

use App\Models\AssetCatalogDefault;
use App\Models\AssetCategory;
use App\Models\AssetMaster;
use App\Models\AssetType;
use App\Models\User;
use App\Support\AssetMasterType;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveAssetMaster
{
    public function save(User $actor, AssetMasterType $type, ?int $id, array $input): AssetMaster
    {
        try {
            return DB::transaction(function () use ($actor, $type, $id, $input): AssetMaster {
                $actor = IdentityAccess::lockScopedActor($actor);
                $model = $type->model();
                $gate = Gate::forUser($actor);
                $gate->authorize('create', $model);

                // A single stable row serializes writes to the shared tree and default pointers.
                $defaults = AssetCatalogDefault::query()->lockForUpdate()->findOrFail(AssetCatalogDefault::ID);
                $master = $id === null ? new $model : $model::query()->lockForUpdate()->findOrFail($id);
                if ($master->exists) {
                    $gate->authorize('update', $master);
                }

                $rules = [
                    'master_code' => $master->exists ? ['missing'] : ['required', 'string', 'max:64', 'regex:/\A[A-Z0-9][A-Z0-9_-]*\z/i'],
                    'name' => ['required', 'string', 'max:255'],
                    'is_active' => ['required', 'boolean'],
                ];
                if ($type === AssetMasterType::Type) {
                    $rules['category_id'] = $master->exists ? ['missing'] : ['required', 'integer', 'min:1'];
                }
                if ($type === AssetMasterType::Category) {
                    $rules['parent_id'] = ['nullable', 'integer', 'min:1'];
                }
                $data = Validator::make($input, $rules, attributes: [
                    'master_code' => __('masters.code'), 'name' => __('masters.name'), 'is_active' => __('masters.status'),
                    'category_id' => __('asset_catalog.category'), 'parent_id' => __('asset_catalog.parent'),
                ])->validate();

                $before = $master->exists ? $master->getAttributes() : null;
                if ($before === null) {
                    $master->code = strtoupper($data['master_code']);
                }

                $active = (bool) $data['is_active'];
                if ($master instanceof AssetCategory) {
                    $parent = array_key_exists('parent_id', $data) ? (isset($data['parent_id']) ? (int) $data['parent_id'] : null) : $master->parent_id;
                    $this->checkParent($master->id, $parent, $active || $parent !== $master->parent_id);
                    if (! $active && $master->is_active && $master->exists
                        && (AssetCategory::query()->where('parent_id', $master->id)->where('is_active', true)->lockForUpdate()->first(['id'])
                            || AssetType::query()->where('category_id', $master->id)->where('is_active', true)->lockForUpdate()->first(['id']))) {
                        throw ValidationException::withMessages(['is_active' => __('asset_catalog.active_children')]);
                    }
                    $master->parent_id = $parent;
                }

                if ($master instanceof AssetType && ($before === null || $active)) {
                    $categoryId = $master->category_id ?? (int) $data['category_id'];
                    $category = AssetCategory::query()->lockForUpdate()->find($categoryId);
                    if (! $category?->is_active) {
                        throw ValidationException::withMessages(['category_id' => __('asset_catalog.inactive_category')]);
                    }
                    $master->category_id = $categoryId;
                }

                $defaultColumn = $type->defaultColumn();
                if (! $active && $defaultColumn !== null && $defaults->{$defaultColumn} === $master->id) {
                    throw ValidationException::withMessages(['is_active' => __('asset_catalog.replace_default')]);
                }

                $master->fill(['name' => $data['name'], 'is_active' => $active]);
                if ($before === null || $master->isDirty()) {
                    $master->save();
                    IdentityAudit::record($actor->id, $master, $before === null ? 'asset-master.created' : 'asset-master.updated',
                        'asset-catalog', $before, $master->getAttributes());
                }

                return $master;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['master_code' => __('asset_catalog.duplicate_code')]);
        }
    }

    public function setDefault(User $actor, AssetMasterType $type, int $id): void
    {
        DB::transaction(function () use ($actor, $type, $id): void {
            $actor = IdentityAccess::lockScopedActor($actor);
            $model = $type->model();
            Gate::forUser($actor)->authorize('create', $model);
            $column = $type->defaultColumn();
            abort_if($column === null, 404);
            $defaults = AssetCatalogDefault::query()->lockForUpdate()->findOrFail(AssetCatalogDefault::ID);
            $master = $model::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('setDefault', $master);
            if (! $master->is_active) {
                throw ValidationException::withMessages(['is_active' => __('asset_catalog.inactive_default')]);
            }

            if ($defaults->{$column} !== $master->id) {
                $before = $defaults->getAttributes();
                $defaults->update([$column => $master->id]);
                IdentityAudit::record($actor->id, $defaults, 'asset-master.default_changed', 'asset-catalog', $before, $defaults->getAttributes());
            }
        }, 3);
    }

    private function checkParent(?int $categoryId, ?int $parent, bool $mustBeActive): void
    {
        $seen = $categoryId === null ? [] : [$categoryId => true];
        while ($parent !== null) {
            if (isset($seen[$parent])) {
                throw ValidationException::withMessages(['parent_id' => __('asset_catalog.category_cycle')]);
            }
            $seen[$parent] = true;
            $ancestor = AssetCategory::query()->lockForUpdate()->find($parent);
            if ($ancestor === null || ($mustBeActive && ! $ancestor->is_active)) {
                throw ValidationException::withMessages(['parent_id' => __('asset_catalog.invalid_parent')]);
            }
            $parent = $ancestor->parent_id;
        }
    }
}
