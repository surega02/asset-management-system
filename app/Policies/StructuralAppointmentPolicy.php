<?php

namespace App\Policies;

use App\Models\StructuralAppointment;
use App\Models\User;
use App\Queries\PeopleScope;
use App\Support\IdentityAccess;

final class StructuralAppointmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return PeopleScope::allowed($actor, 'appointments.view');
    }

    public function create(User $actor, ?string $type = null, ?int $id = null): bool
    {
        if (! $this->viewAny($actor) || ! PeopleScope::allowed($actor, 'appointments.manage')) {
            return false;
        }

        return match ($type) {
            null => true,
            'company' => IdentityAccess::superAdminCan($actor, 'appointments.manage'),
            'branch', 'division', 'department' => PeopleScope::units($actor, $type, 'appointments.manage')->where('id', $id)->exists(),
            default => false,
        };
    }

    public function update(User $actor, StructuralAppointment $appointment): bool
    {
        $type = $appointment->scope_type;

        return PeopleScope::appointments($actor)->whereKey($appointment->id)->exists()
            && $this->create($actor, $type, $type === 'company' ? null : $appointment->{$type.'_id'});
    }
}
