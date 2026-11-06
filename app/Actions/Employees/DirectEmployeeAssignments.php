<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\User;
use App\Queries\Assets;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
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
        return self::active($lockedEmployee)->lockForUpdate()->pluck('asset_id')->all();
    }

    public static function warning(User $actor, Employee $employee): array
    {
        $query = self::active($employee);
        $count = (clone $query)->count();
        $assets = collect();

        if ($count) {
            $assets = Assets::visible($actor)->whereIn('id', $query->select('asset_id'))
                ->orderBy('asset_code')->get(['asset_code', 'name']);
        }

        return compact('count', 'assets');
    }

    private static function active(Employee $employee): Builder
    {
        return DB::table('asset_assignments')->where('employee_id', $employee->id)->whereNull('ended_at');
    }
}
