<?php

namespace App\Actions\Organization;

use App\Models\Department;
use App\Models\Employee;
use App\Models\StructuralAppointment;
use App\Models\User;
use App\Queries\OrganizationScope;
use App\Queries\PeopleScope;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AssignStructuralOfficer
{
    public function create(User $actor, array $input): StructuralAppointment
    {
        return DB::transaction(function () use ($actor, $input): StructuralAppointment {
            $actor = IdentityAccess::lockScopedActor($actor);
            $gate = Gate::forUser($actor);
            $gate->authorize('create', StructuralAppointment::class);
            $data = Validator::make($input, [
                'employee_id' => ['required', 'integer', 'min:1'],
                'role_kind' => ['required', 'in:hod,director'],
                'scope_type' => ['required', 'in:branch,division,department,company'],
                'scope_id' => ['nullable', 'integer', 'min:1'],
                'appointment_kind' => ['required', 'in:definitive,acting'],
                'starts_on' => $this->dateRules(),
                'ends_on' => ['nullable', ...array_slice($this->dateRules(), 1), 'after_or_equal:starts_on'],
            ], attributes: __('people.fields'))->validate();

            $type = $data['scope_type'];
            $role = $data['role_kind'];
            $unit = isset($data['scope_id']) ? (int) $data['scope_id'] : null;
            if (($role === 'hod') !== ($type === 'department') || ($type === 'company') !== ($unit === null)) {
                throw ValidationException::withMessages(['scope_type' => __('people.invalid_scope')]);
            }

            $gate->authorize('create', [StructuralAppointment::class, $type, $unit]);
            $employee = Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);
            // Evaluate the locked employee's current department, not an earlier consistent-read snapshot.
            abort_unless(PeopleScope::departments($actor, 'appointments.manage')->whereKey($employee->department_id)->exists(), 403);
            if ($employee->employment_status !== 'active') {
                throw ValidationException::withMessages(['employee_id' => __('people.inactive_employee')]);
            }

            $this->lockScope($role, $type, $unit);
            $this->checkUnit($type, $unit);
            $end = $data['ends_on'] ?? null;
            $this->assertAvailable($role, $type, $unit, $data['appointment_kind'], $data['starts_on'], $end);

            $appointment = StructuralAppointment::query()->create([
                'employee_id' => $employee->id,
                'position_id' => $employee->position_id,
                'role_kind' => $role,
                'scope_type' => $type,
                'appointment_kind' => $data['appointment_kind'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $end,
            ] + ($unit === null ? [] : [$type.'_id' => $unit]));
            IdentityAudit::record($actor->id, $appointment, 'appointment.created', 'structural-appointment', new: $appointment->getAttributes());

            return $appointment;
        }, 3);
    }

    public function end(User $actor, StructuralAppointment $appointment, array $input): void
    {
        DB::transaction(function () use ($actor, $appointment, $input): void {
            $actor = IdentityAccess::lockScopedActor($actor);
            $gate = Gate::forUser($actor);
            $gate->authorize('update', $appointment);
            $type = $appointment->scope_type;
            $unit = $type === 'company' ? null : $appointment->{$type.'_id'};
            $this->lockScope($appointment->role_kind, $type, $unit);
            $appointment = StructuralAppointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $data = Validator::make($input, [
                'ends_on' => [...$this->dateRules(), 'after_or_equal:'.$appointment->starts_on],
                'reason' => ['required', 'string', 'max:100'],
            ], attributes: __('people.fields'))->validate();
            $before = $appointment->getAttributes();
            $this->assertAvailable($appointment->role_kind, $type, $unit, $appointment->appointment_kind, $appointment->starts_on, $data['ends_on'], $appointment->id);
            $appointment->ends_on = $data['ends_on'];
            if ($appointment->isDirty('ends_on')) {
                $appointment->save();
                IdentityAudit::record($actor->id, $appointment, 'appointment.period_changed', $data['reason'], $before, $appointment->getAttributes());
            }
        }, 3);
    }

    private function lockScope(string $role, string $type, ?int $unit): void
    {
        $key = ['role_kind' => $role, 'scope_type' => $type, 'scope_id' => $unit ?? 0];
        // A concurrent insert on the same natural key waits for the first writer.
        DB::table('structural_appointment_scope_locks')->upsert([$key], array_keys($key), ['scope_id']);
        DB::table('structural_appointment_scope_locks')->where($key)->lockForUpdate()->first();
    }

    private function checkUnit(string $type, ?int $id): void
    {
        if ($id === null) {
            return;
        }
        $record = DB::table(OrganizationScope::TABLES[$type])->where('id', $id)->sharedLock()->first();
        if (! $record?->is_active) {
            throw ValidationException::withMessages(['scope_id' => __('people.invalid_unit')]);
        }
    }

    private function assertAvailable(string $role, string $type, ?int $unit, string $kind, string $start, ?string $end, ?int $except = null): void
    {
        $overlap = StructuralAppointment::query()->forUnit($role, $type, $unit)
            ->where('appointment_kind', $kind)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->when($end !== null, fn ($query) => $query->where('starts_on', '<=', $end))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $start))
            ->lockForUpdate()->first(['id']);

        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => __('people.overlap')]);
        }
    }

    private function dateRules(): array
    {
        return ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];
    }
}
