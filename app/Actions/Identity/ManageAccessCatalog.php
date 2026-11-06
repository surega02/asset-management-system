<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ManageAccessCatalog
{
    public function saveRole(User $actor, ?Role $role, string $name, array $permissionIds): void
    {
        $this->change($actor, function (User $actor) use ($role, $name, $permissionIds): void {
            $record = $role ? Role::query()->findOrFail($role->id) : new Role(['guard_name' => 'web']);
            $this->assertEditable($record, $name, $record->name === IdentityAccess::SUPER_ADMIN);
            $this->assertUnique($record, $name);

            $permissions = Permission::query()->where('guard_name', 'web')->whereKey($permissionIds)->get();
            if ($permissions->count() !== count($permissionIds)) {
                throw ValidationException::withMessages(['permissions' => __('iam.invalid_access')]);
            }

            $old = $record->exists ? $this->roleSnapshot($record) : null;
            $record->name = $name;
            $record->save();
            $record->syncPermissions($permissions);
            $new = $this->roleSnapshot($record);
            if ($old !== $new) {
                IdentityAudit::record($actor->id, $record, 'role.saved', 'administrative_access', $old, $new);
            }
        });
    }

    public function savePermission(User $actor, ?Permission $permission, string $name): void
    {
        $this->change($actor, function (User $actor) use ($permission, $name): void {
            $record = $permission ? Permission::query()->findOrFail($permission->id) : new Permission(['guard_name' => 'web']);
            $this->assertEditable($record, $name, in_array($record->name, IdentityAccess::PERMISSIONS, true));
            $this->assertUnique($record, $name);

            $old = $record->exists ? ['name' => $record->name] : null;
            $record->name = $name;
            $record->save();
            if ($old !== ['name' => $name]) {
                IdentityAudit::record($actor->id, $record, 'permission.saved', 'administrative_access', $old, ['name' => $name]);
            }
        });
    }

    public function delete(User $actor, Role|Permission $record): void
    {
        $this->change($actor, function (User $actor) use ($record): void {
            $record = $record->newQuery()->findOrFail($record->id);
            $reserved = $record instanceof Role ? IdentityAccess::ROLES : IdentityAccess::PERMISSIONS;
            $inUse = $record->users()->exists() || ($record instanceof Permission && $record->roles()->exists());
            if (in_array($record->name, $reserved, true) || $inUse || $record->guard_name !== 'web') {
                throw ValidationException::withMessages(['name' => __('iam.access_protected')]);
            }

            $old = $record instanceof Role ? $this->roleSnapshot($record) : ['name' => $record->name];
            IdentityAudit::record($actor->id, $record, $record instanceof Role ? 'role.deleted' : 'permission.deleted', 'administrative_access', $old);
            $record->delete();
        });
    }

    private function change(User $actor, callable $callback): void
    {
        DB::transaction(function () use ($actor, $callback): void {
            $actor = IdentityAccess::lockAdministrator($actor);
            Gate::forUser($actor)->authorize('manage-access-catalog');
            $callback($actor);
            DB::afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        }, 3);
    }

    private function assertEditable(Role|Permission $record, string $name, bool $protected): void
    {
        if ($protected || $record->guard_name !== 'web' || (
            $record instanceof Role && in_array($record->name, IdentityAccess::ROLES, true) && $record->name !== $name
        )) {
            throw ValidationException::withMessages(['name' => __('iam.access_protected')]);
        }
    }

    private function assertUnique(Role|Permission $record, string $name): void
    {
        if ($record->newQuery()->where('guard_name', 'web')->where('name', $name)
            ->when($record->exists, fn ($query) => $query->whereKeyNot($record->id))->exists()) {
            throw ValidationException::withMessages(['name' => __('iam.name_in_use')]);
        }
    }

    private function roleSnapshot(Role $role): array
    {
        return ['name' => $role->name, 'permission_ids' => $role->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all()];
    }
}
