<?php

namespace App\Actions\Employees;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Position;
use App\Models\User;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ManageEmployee
{
    public function create(User $actor, array $input, bool $importing = false): Employee
    {
        try {
            return DB::transaction(function () use ($actor, $input, $importing): Employee {
                $actor = IdentityAccess::lockScopedActor($actor);
                $gate = Gate::forUser($actor);
                $gate->authorize($importing ? 'import' : 'create', Employee::class);
                $data = $this->validate($input, [
                    'nik' => ['required', 'string', 'max:64', 'regex:/\A[^@\p{C}]+\z/u'],
                    'department_id' => ['required', 'integer', 'min:1'],
                    ...$this->profileRules(),
                ]);
                $gate->authorize('create', [Employee::class, (int) $data['department_id']]);
                $this->lockPlacement((int) $data['department_id'], (int) $data['home_location_id']);
                $this->checkPosition((int) $data['position_id']);
                $employee = Employee::query()->create($data);
                IdentityAudit::record($actor->id, $employee, 'employee.created', $importing ? 'employee-import' : 'employee-registration', new: $employee->getAttributes());

                return $employee;
            }, $importing ? 1 : 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nik' => __('people.duplicate_nik')]);
        }
    }

    public function update(User $actor, Employee $employee, array $input): void
    {
        DB::transaction(function () use ($actor, $employee, $input): void {
            $actor = IdentityAccess::lockScopedActor($actor);
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            Gate::forUser($actor)->authorize('update', $employee);
            $data = $this->validate($input, [
                ...$this->profileRules(),
                'nik' => ['missing'],
                'department_id' => ['missing'],
            ]);
            if ($employee->home_location_id !== (int) $data['home_location_id']) {
                $this->lockPlacement($employee->department_id, (int) $data['home_location_id']);
            }
            if ($employee->position_id !== (int) $data['position_id']) {
                $this->checkPosition((int) $data['position_id']);
            }

            $before = $employee->getAttributes();
            $employee->fill($data);
            if ($employee->isDirty('employment_status') && $employee->employment_status === 'inactive'
                && DirectEmployeeAssignments::activeAssetIdsForUpdate($employee) !== []) {
                throw ValidationException::withMessages(['employment_status' => __('people.has_assets')]);
            }

            if ($employee->isDirty()) {
                $employee->save();
                IdentityAudit::record($actor->id, $employee, 'employee.updated', 'employee-profile', $before, $employee->getAttributes());
            }
        }, 3);
    }

    public function transfer(User $actor, Employee $employee, array $input): int
    {
        return DB::transaction(function () use ($actor, $employee, $input): int {
            $actor = IdentityAccess::lockScopedActor($actor);
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $gate = Gate::forUser($actor);
            $gate->authorize('update', $employee);
            $data = $this->validate($input, [
                'department_id' => ['required', 'integer', 'min:1', 'not_in:'.$employee->department_id],
                'home_location_id' => ['required', 'integer', 'min:1'],
                'reason' => ['required', 'string', 'max:100'],
            ]);
            $gate->authorize('create', [Employee::class, (int) $data['department_id']]);
            $before = $this->placement($employee->department_id, $employee->home_location_id);
            $after = $this->lockPlacement((int) $data['department_id'], (int) $data['home_location_id']);
            $count = count(DirectEmployeeAssignments::activeAssetIdsForUpdate($employee));
            $employee->fill([
                'department_id' => $after['department_id'],
                'home_location_id' => $after['home_location_id'],
            ])->save();

            $history = ['employee_id' => $employee->id, 'actor_id' => $actor->id, 'reason' => $data['reason'], 'changed_at' => now()];
            foreach (['from' => $before, 'to' => $after] as $side => $placement) {
                foreach ($placement as $key => $value) {
                    $history[$side.'_'.$key] = $value;
                }
            }
            DB::table('employee_movements')->insert($history);
            IdentityAudit::record($actor->id, $employee, 'employee.transferred', $data['reason'], $before, $after);

            return $count;
        }, 3);
    }

    private function placement(int $departmentId, int $locationId): array
    {
        $department = Department::query()->with('owner')->findOrFail($departmentId);

        return [
            'branch_id' => $department->owner->branch_id,
            'division_id' => $department->division_id,
            'department_id' => $departmentId,
            'home_location_id' => $locationId,
        ];
    }

    private function lockPlacement(int $departmentId, int $locationId): array
    {
        $placement = $this->placement($departmentId, $locationId);
        $branch = Branch::query()->sharedLock()->findOrFail($placement['branch_id']);
        $division = Division::query()->sharedLock()->findOrFail($placement['division_id']);
        $department = Department::query()->sharedLock()->findOrFail($departmentId);
        if (! $branch->is_active || ! $division->is_active || ! $department->is_active) {
            throw ValidationException::withMessages(['department_id' => __('people.invalid_unit')]);
        }
        if (! Location::query()->where('branch_id', $branch->id)->where('is_active', true)->sharedLock()->find($locationId)) {
            throw ValidationException::withMessages(['home_location_id' => __('people.invalid_location')]);
        }

        return $placement;
    }

    private function checkPosition(int $id): void
    {
        if (! Position::query()->where('is_active', true)->sharedLock()->find($id)) {
            throw ValidationException::withMessages(['position_id' => __('people.invalid_unit')]);
        }
    }

    private function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'joined_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'position_id' => ['required', 'integer', 'min:1'],
            'home_location_id' => ['required', 'integer', 'min:1'],
            'employment_status' => ['required', 'in:active,inactive'],
        ];
    }

    private function validate(array $input, array $rules): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value);
            }
        }

        return Validator::make($input, $rules, attributes: __('people.fields'))->validate();
    }
}
