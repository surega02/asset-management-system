<?php

namespace App\Queries;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\StructuralAppointment;
use App\Models\User;
use App\Support\IdentityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PeopleScope
{
    public const PERMISSIONS = ['employees.view', 'employees.manage', 'employees.import', 'appointments.view', 'appointments.manage'];

    public static function allowed(User $actor, string $permission): bool
    {
        return $actor->is_active && $actor->checkPermissionTo($permission)
            && (IdentityAccess::superAdminCan($actor, $permission)
                || DB::table('user_data_scopes')->where('user_id', $actor->id)->exists());
    }

    public static function departments(User $actor, string $permission): Builder
    {
        $query = Department::query();
        if (! self::allowed($actor, $permission)) {
            return $query->whereRaw('1 = 0');
        }

        return IdentityAccess::superAdminCan($actor, $permission)
            ? $query
            : $query->whereIn('id', OrganizationScope::departments($actor));
    }

    public static function employees(User $actor, string $permission = 'employees.view'): Builder
    {
        return Employee::query()->whereIn('department_id', self::departments($actor, $permission)->select('id'));
    }

    public static function homeLocations(User $actor): Builder
    {
        return Location::query()->where('is_active', true)->whereIn('branch_id',
            DB::table('divisions')->select('branch_id')->whereIn('id',
                self::departments($actor, 'employees.manage')->select('division_id')));
    }

    public static function units(User $actor, string $type, string $permission): \Illuminate\Database\Query\Builder
    {
        $query = DB::table(OrganizationScope::TABLES[$type])->select('id');
        if (! self::allowed($actor, $permission)) {
            return $query->whereRaw('1 = 0');
        }

        if (IdentityAccess::superAdminCan($actor, $permission)) {
            return $query;
        }

        return match ($type) {
            'branch' => OrganizationScope::branches($actor),
            'division' => OrganizationScope::divisions($actor),
            'department' => OrganizationScope::departments($actor),
        };
    }

    public static function appointments(User $actor): Builder
    {
        $query = StructuralAppointment::query();
        if (! self::allowed($actor, 'appointments.view')) {
            return $query->whereRaw('1 = 0');
        }

        if (IdentityAccess::superAdminCan($actor, 'appointments.view')) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->whereIn('branch_id', self::units($actor, 'branch', 'appointments.view'))
            ->orWhereIn('division_id', self::units($actor, 'division', 'appointments.view'))
            ->orWhereIn('department_id', self::units($actor, 'department', 'appointments.view')));
    }

    public static function effectiveOfficer(User $actor, string $role, string $scope, ?int $unit, string $date): ?StructuralAppointment
    {
        return self::appointments($actor)->forUnit($role, $scope, $unit)->effectiveOn($date)
            ->orderByRaw("appointment_kind = 'acting' DESC")->first();
    }
}
