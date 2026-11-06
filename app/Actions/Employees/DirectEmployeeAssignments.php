<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\User;
use App\Queries\OrganizationScope;
use App\Support\IdentityAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;

final class DirectEmployeeAssignments
{
    /**
     * Register/reassign/reactivate must call this inside their write transaction
     * before inserting a new direct assignment. Hold the lock until commit.
     */
    public static function lockEligibleAssignee(int $id): Employee
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The employee assignment lock requires a transaction.');
        }

        $employee = Employee::query()->lockForUpdate()->findOrFail($id);
        if ($employee->employment_status !== 'active') {
            throw ValidationException::withMessages(['employee_id' => __('people.inactive_employee')]);
        }

        return $employee;
    }

    public static function activeAssetIdsForUpdate(Employee $lockedEmployee): array
    {
        return self::active($lockedEmployee)?->lockForUpdate()->pluck('asset_id')->all() ?? [];
    }

    public static function warning(User $actor, Employee $employee): array
    {
        $query = self::active($employee);
        $count = $query?->count() ?? 0;
        $assets = collect();

        if ($count && $actor->checkPermissionTo('assets.view')) {
            $assets = $query->join('assets', 'assets.id', '=', 'asset_assignments.asset_id')
                ->join('locations', 'locations.id', '=', 'assets.physical_location_id')
                ->where(function (Builder $scope) use ($actor, $employee): void {
                    if (! IdentityAccess::superAdminCan($actor, 'assets.view')) {
                        $scope->whereIn('locations.branch_id', OrganizationScope::branches($actor))
                            ->orWhere('asset_assignments.employee_id', $actor->employee_id === $employee->id ? $employee->id : 0);
                    }
                })->orderBy('assets.asset_code')->get(['assets.asset_code', 'assets.name']);
        }

        return compact('count', 'assets');
    }

    private static function active(Employee $employee): ?Builder
    {
        // M07 owns asset migrations. An incomplete asset schema must fail closed.
        if (! Schema::hasTable('asset_assignments')) {
            if (Schema::hasTable('assets')) {
                throw new LogicException('Asset assignment schema is incomplete.');
            }

            return null;
        }

        return DB::table('asset_assignments')->where('employee_id', $employee->id)->whereNull('ended_at');
    }
}
