<?php

namespace App\Http\Controllers;

use App\Actions\Organization\AssignStructuralOfficer;
use App\Models\StructuralAppointment;
use App\Queries\OrganizationScope;
use App\Queries\PeopleScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class StructuralAppointmentController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StructuralAppointment::class);
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']], attributes: ['date' => __('people.reference_date')])['date'] ?? today()->toDateString();
        $effective = StructuralAppointment::query()->from('structural_appointments as candidate')->select('candidate.id')
            ->whereColumn('candidate.role_kind', 'structural_appointments.role_kind')
            ->whereColumn('candidate.scope_type', 'structural_appointments.scope_type')
            ->whereRaw('candidate.branch_id <=> structural_appointments.branch_id')
            ->whereRaw('candidate.division_id <=> structural_appointments.division_id')
            ->whereRaw('candidate.department_id <=> structural_appointments.department_id')
            ->effectiveOn($date)->orderByRaw("candidate.appointment_kind = 'acting' DESC")->limit(1);
        $rows = PeopleScope::appointments($request->user())->select('structural_appointments.*')
            ->addSelect(['effective_id' => $effective])
            ->with('employee:id,name', 'branch:id,name', 'division:id,name', 'department:id,name')
            ->orderByDesc('starts_on')->orderByDesc('id')->simplePaginate(20)->withQueryString();

        return view('people.appointments', compact('date', 'rows'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', StructuralAppointment::class);
        $actor = $request->user();
        $employees = PeopleScope::employees($actor, 'appointments.manage')->where('employment_status', 'active')->orderBy('name')->get(['id', 'name']);
        $units = [];
        foreach (OrganizationScope::TABLES as $type => $table) {
            $units[$type] = DB::table($table)->whereIn('id', PeopleScope::units($actor, $type, 'appointments.manage'))
                ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        }

        return view('people.appointment-form', compact('employees', 'units'));
    }

    public function store(Request $request, AssignStructuralOfficer $assign): RedirectResponse
    {
        $input = $request->only('employee_id', 'role_kind', 'appointment_kind', 'starts_on', 'ends_on');
        $key = $request->validate(['scope_key' => ['required', 'string', 'regex:/\A(company|branch:[1-9][0-9]*|division:[1-9][0-9]*|department:[1-9][0-9]*)\z/']], attributes: ['scope_key' => __('people.fields.scope_type')])['scope_key'];
        [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
        $assign->create($request->user(), $input + ['scope_type' => $type, 'scope_id' => $id]);

        return to_route('appointments.index')->with('status', __('people.saved'));
    }

    public function update(Request $request, StructuralAppointment $appointment, AssignStructuralOfficer $assign): RedirectResponse
    {
        $assign->end($request->user(), $appointment, $request->only('ends_on', 'reason'));

        return to_route('appointments.index')->with('status', __('people.saved'));
    }

    public function edit(StructuralAppointment $appointment): View
    {
        Gate::authorize('update', $appointment);
        $appointment->load('employee:id,name');

        return view('people.appointment-edit', compact('appointment'));
    }
}
