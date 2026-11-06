<?php

namespace App\Support;

use App\Models\User;
use App\Queries\OrganizationMasters;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class IdentityAccess
{
    public const SUPER_ADMIN = 'super-admin';

    public const ROLES = [self::SUPER_ADMIN, 'asset-admin', 'asset-officer', 'leader', 'employee'];

    public const PERMISSIONS = ['users.view', 'users.manage', 'users.reset', 'users.access', 'roles.manage', 'settings.manage', OrganizationMasters::VIEW, OrganizationMasters::MANAGE];

    public static function superAdminCan(User $user, string $permission): bool
    {
        return $user->is_active && $user->hasRole(self::SUPER_ADMIN) && $user->checkPermissionTo($permission);
    }

    public static function lockAdministrator(User $actor): User
    {
        Role::query()->where('name', self::SUPER_ADMIN)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
        app(PermissionRegistrar::class)->clearPermissionsCollection();
        $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
        abort_unless($fresh->is_active && $fresh->auth_version === $actor->auth_version, 403);

        return $fresh;
    }
}
