<?php

namespace App\Http\Controllers;

use App\Actions\Employees\DirectEmployeeAssignments;
use App\Actions\Employees\ManageEmployee;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Queries\PeopleScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class EmployeeController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);
        $search = $request->validate(['q' => ['nullable', 'string', 'max:255']], attributes: ['q' => __('people.search')])['q'] ?? '';
        $status = $request->validate(['status' => ['nullable', 'in:active,inactive,all']], attributes: ['status' => __('people.fields.employment_status')])['status'] ?? 'active';
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $rows = PeopleScope::employees($request->user())->with('department.owner.owner', 'position')
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->whereRaw("nik LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("name LIKE ? ESCAPE '!'", [$pattern])))
            ->when($status !== 'all', fn ($query) => $query->where('employment_status', $status))
            ->orderBy('name')->orderBy('id')->simplePaginate(20)->withQueryString();

        return view('people.employees', compact('rows', 'search', 'status'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Employee::class);

        return view('people.employee-form', $this->options($request->user()) + ['employee' => null]);
    }

    public function store(Request $request, ManageEmployee $manage): RedirectResponse
    {
        $employee = $manage->create($request->user(), $request->only('nik', 'name', 'department_id', 'position_id', 'home_location_id', 'joined_at', 'employment_status'));

        return to_route('employees.show', $employee)->with('status', __('people.saved'));
    }

    public function show(Request $request, Employee $employee): View
    {
        Gate::authorize('view', $employee);
        $employee->load('department.owner.owner', 'position', 'homeLocation');
        $canWrite = Gate::allows('update', $employee);
        $warning = $canWrite ? DirectEmployeeAssignments::warning($request->user(), $employee) : null;
        $options = $canWrite ? $this->options($request->user(), $employee) : [];
        $history = DB::table('employee_movements as movement')->where('movement.employee_id', $employee->id)
            ->join('users', 'users.id', '=', 'movement.actor_id')
            ->join('employees as actors', 'actors.id', '=', 'users.employee_id')->select('movement.*', 'actors.name as actor_name');
        foreach (['from', 'to'] as $side) {
            foreach (['branch' => 'branches', 'division' => 'divisions', 'department' => 'departments'] as $kind => $table) {
                $alias = $side.'_'.$kind;
                $history->join($table.' as '.$alias, $alias.'.id', '=', 'movement.'.$alias.'_id')->addSelect($alias.'.name as '.$alias);
            }
        }
        $movements = $history->orderByDesc('movement.changed_at')->orderByDesc('movement.id')->simplePaginate(10);

        return view('people.employee-detail', compact('employee', 'canWrite', 'warning', 'movements') + $options);
    }

    public function update(Request $request, Employee $employee, ManageEmployee $manage): RedirectResponse
    {
        $manage->update($request->user(), $employee, $request->only('nik', 'department_id', 'name', 'position_id', 'home_location_id', 'joined_at', 'employment_status'));

        return to_route('employees.show', $employee)->with('status', __('people.saved'));
    }

    public function transfer(Request $request, Employee $employee, ManageEmployee $manage): RedirectResponse
    {
        try {
            $count = $manage->transfer($request->user(), $employee, $request->only('department_id', 'home_location_id', 'reason'));
        } catch (ValidationException $exception) {
            throw $exception->errorBag('transfer');
        }

        return to_route('employees.show', $employee)->with('status', $count
            ? __('people.transferred_with_assets', ['count' => $count])
            : __('people.transferred'));
    }

    private function options(User $actor, ?Employee $employee = null): array
    {
        $departments = PeopleScope::departments($actor, 'employees.manage')
            ->where('is_active', true)->whereHas('owner', fn ($division) => $division->where('is_active', true)
            ->whereHas('owner', fn ($branch) => $branch->where('is_active', true)))
            ->with('owner.owner')->orderBy('name')->get();
        $locations = PeopleScope::homeLocations($actor)->orderBy('name')->get();
        $positions = Position::query()->where(fn ($query) => $query->where('is_active', true)
            ->when($employee !== null, fn ($query) => $query->orWhere('id', $employee->position_id)))->orderBy('name')->get();

        return compact('departments', 'locations', 'positions');
    }
}
