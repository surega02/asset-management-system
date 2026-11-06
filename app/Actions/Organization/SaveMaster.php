<?php

namespace App\Actions\Organization;

use App\Models\Branch;
use App\Models\Division;
use App\Models\Location;
use App\Models\OrganizationMaster;
use App\Models\User;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use App\Support\MasterType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class SaveMaster
{
    public function __invoke(User $actor, MasterType $type, ?int $id, array $input): OrganizationMaster
    {
        try {
            return DB::transaction(fn () => $this->save($actor, $type, $id, $input), 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['master_code' => __('masters.duplicate_code')]);
        }
    }

    private function save(User $actor, MasterType $type, ?int $id, array $input): OrganizationMaster
    {
        // Coordinate with IAM catalog edits while allowing masters in separate branches to proceed.
        Role::query()->where('name', IdentityAccess::SUPER_ADMIN)->where('guard_name', 'web')->sharedLock()->firstOrFail();
        app(PermissionRegistrar::class)->clearPermissionsCollection();
        $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
        abort_unless($fresh->is_active && $fresh->auth_version === $actor->auth_version, 403);
        $gate = Gate::forUser($fresh);
        $model = $type->model();
        $master = $id === null ? null : $model::query()->findOrFail($id);
        $ownerKey = $type->ownerKey();

        if ($master === null) {
            $gate->authorize('create', [$model, $type]);
        } else {
            $gate->authorize('update', $master);
        }

        $rules = [
            'master_code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
        if ($ownerKey !== null && $master === null) {
            $rules['owner_id'] = ['required', 'integer', 'min:1'];
        } elseif (array_key_exists('owner_id', $input)) {
            throw ValidationException::withMessages(['owner_id' => __('masters.fixed_owner')]);
        }

        if ($type === MasterType::Location) {
            $rules['parent_id'] = ['nullable', 'integer', 'min:1'];
        }

        $data = Validator::make($input, $rules, attributes: [
            'master_code' => __('masters.code'),
            'name' => __('masters.name'),
            'is_active' => __('masters.status'),
            'owner_id' => $type->owner() ? __('masters.owner_'.$type->owner()->value) : __('masters.owner'),
            'parent_id' => __('masters.parent'),
        ])->validate();

        $ownerId = $ownerKey === null ? null : (int) ($master?->{$ownerKey} ?? $data['owner_id']);
        if ($master === null && $ownerId !== null) {
            $gate->authorize('create', [$model, $type, $ownerId]);
        }

        $branchId = match ($type) {
            MasterType::Branch => $id,
            MasterType::Division, MasterType::Location => $ownerId,
            MasterType::Department => Division::query()->findOrFail($ownerId)->branch_id,
            MasterType::Position => null,
        };
        // All writes affecting a branch's hierarchy take the same lock before locking any master rows.
        $branch = $branchId === null ? null : Branch::query()->lockForUpdate()->findOrFail($branchId);

        if ($id !== null) {
            $master = $type === MasterType::Branch ? $branch : $model::query()->lockForUpdate()->findOrFail($id);
        } else {
            if ($ownerId !== null && (! $branch->is_active || ($type === MasterType::Department && ! Division::query()->lockForUpdate()->findOrFail($ownerId)->is_active))) {
                throw ValidationException::withMessages(['owner_id' => __('masters.inactive_owner')]);
            }
            $master = new $model;
            if ($ownerKey !== null) {
                $master->{$ownerKey} = $ownerId;
            }
        }

        $before = $id === null ? null : $master->getAttributes();
        if ($master instanceof Location && ($id === null || array_key_exists('parent_id', $data))) {
            $parent = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
            if ($parent !== $master->parent_id) {
                $this->checkParent($master, $parent);
                $master->parent_id = $parent;
            }
        }

        $master->fill(['code' => $data['master_code'], 'name' => $data['name'], 'is_active' => (bool) $data['is_active']]);
        if ($id === null || $master->isDirty()) {
            $event = match (true) {
                $id === null => 'created',
                $master->isDirty('is_active') => $master->is_active ? 'reactivated' : 'archived',
                default => 'updated',
            };
            $master->save();
            IdentityAudit::record($fresh->id, $master, 'master.'.$event, 'organization-master', $before, $master->getAttributes());
        }

        return $master;
    }

    private function checkParent(Location $location, ?int $parent): void
    {
        $seen = $location->exists ? [$location->id => true] : [];

        while ($parent !== null) {
            if (isset($seen[$parent])) {
                throw ValidationException::withMessages(['parent_id' => __('masters.cycle')]);
            }
            $seen[$parent] = true;

            $ancestor = Location::query()->where('branch_id', $location->branch_id)->lockForUpdate()->find($parent);
            if ($ancestor === null || ! $ancestor->is_active) {
                throw ValidationException::withMessages(['parent_id' => __('masters.invalid_parent')]);
            }

            $parent = $ancestor->parent_id;
        }
    }
}
