<?php

namespace App\Actions\Assets;

use App\Actions\Employees\DirectEmployeeAssignments;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use App\Queries\Assets;
use App\Queries\OrganizationScope;
use App\Support\IdentityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AssetTargets
{
    public const KEYS = ['employee_id', 'department_id', 'location_id'];

    public static function decodeSelection(mixed $value): array
    {
        $target = Validator::make(['assignee' => $value], [
            'assignee' => ['required', 'string', 'regex:/\A(employee|department|location):[1-9][0-9]{0,18}\z/'],
        ], attributes: ['assignee' => __('assets.assignee')])->validate()['assignee'];
        [$kind, $id] = explode(':', $target);

        return [$kind.'_id' => $id];
    }

    public function physical(User $actor, int $location, string $permission): Location
    {
        return Assets::locations($actor, $permission)->where('is_active', true)->sharedLock()->find($location)
            ?? throw ValidationException::withMessages(['physical_location_id' => __('assets.invalid_location')]);
    }

    public function validate(User $actor, string $permission, array $input): array
    {
        $data = Validator::make($input, array_fill_keys(self::KEYS, ['nullable', 'integer', 'min:1']),
            attributes: array_fill_keys(self::KEYS, __('assets.assignee')))->validate();
        $targets = array_filter($data, fn ($id) => $id !== null);
        if (count($targets) !== 1) {
            throw ValidationException::withMessages(['assignee' => __('assets.exactly_one')]);
        }

        $kind = array_key_first($targets);
        $id = (int) $targets[$kind];
        $eligible = match ($kind) {
            'employee_id' => $this->eligibleEmployee($actor, $permission, $id),
            'department_id' => $this->departments($actor, $permission)->where('is_active', true)->sharedLock()->find($id) !== null,
            'location_id' => Assets::locations($actor, $permission)->where('is_active', true)->sharedLock()->find($id) !== null,
        };

        if (! $eligible) {
            throw ValidationException::withMessages(['assignee' => __('assets.invalid_assignee')]);
        }

        return [$kind => $id];
    }

    public function options(User $actor, string $permission): array
    {
        return [
            'employee' => $this->employees($actor, $permission)->where('employment_status', 'active')->orderBy('name')->get(['id', 'name']),
            'department' => $this->departments($actor, $permission)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'location' => Assets::locations($actor, $permission)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function eligibleEmployee(User $actor, string $permission, int $id): bool
    {
        if (! $this->employees($actor, $permission)->whereKey($id)->exists()) {
            return false;
        }

        $employee = DirectEmployeeAssignments::lockEligibleAssignee($id);

        // Transfer also locks the employee. Recheck the current department after waiting.
        return $this->departments($actor, $permission)->whereKey($employee->department_id)->exists();
    }

    private function employees(User $actor, string $permission): Builder
    {
        return Employee::query()->whereIn('department_id', $this->departments($actor, $permission)->select('id'));
    }

    private function departments(User $actor, string $permission): Builder
    {
        $query = Department::query();
        if (! $actor->is_active || ! $actor->checkPermissionTo($permission)) {
            return $query->whereRaw('1 = 0');
        }

        return IdentityAccess::superAdminCan($actor, $permission)
            ? $query
            : $query->whereIn('id', OrganizationScope::departments($actor));
    }
}
