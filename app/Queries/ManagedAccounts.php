<?php

namespace App\Queries;

use App\Models\Employee;
use App\Models\User;
use App\Support\IdentityAccess;
use Illuminate\Database\Eloquent\Builder;

final class ManagedAccounts
{
    public static function employees(User $actor): Builder
    {
        $query = Employee::query();
        if (! $actor->is_active || ! $actor->checkPermissionTo('users.view')) {
            return $query->whereRaw('1 = 0');
        }

        return IdentityAccess::superAdminCan($actor, 'users.view')
            ? $query
            : $query->whereIn('department_id', OrganizationScope::departments($actor));
    }

    public static function users(User $actor): Builder
    {
        return User::query()->whereIn('employee_id', self::employees($actor)->select('id'));
    }
}
