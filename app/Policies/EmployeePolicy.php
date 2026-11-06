<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Queries\PeopleScope;

final class EmployeePolicy
{
    public function viewAny(User $actor): bool
    {
        return PeopleScope::allowed($actor, 'employees.view');
    }

    public function view(User $actor, Employee $employee): bool
    {
        return PeopleScope::employees($actor)->whereKey($employee->id)->exists();
    }

    public function create(User $actor, ?int $departmentId = null): bool
    {
        return $this->viewAny($actor) && PeopleScope::departments($actor, 'employees.manage')
            ->when($departmentId !== null, fn ($query) => $query->whereKey($departmentId))->exists();
    }

    public function update(User $actor, Employee $employee): bool
    {
        return $this->view($actor, $employee) && $this->create($actor, $employee->department_id);
    }

    public function import(User $actor): bool
    {
        return $this->create($actor) && PeopleScope::allowed($actor, 'employees.import');
    }
}
