<?php

namespace App\Policies;

use App\Models\OrganizationMaster;
use App\Models\User;
use App\Queries\OrganizationMasters;
use App\Support\IdentityAccess;
use App\Support\MasterType;

final class OrganizationMasterPolicy
{
    public function viewAny(User $actor): bool
    {
        return OrganizationMasters::canBrowse($actor);
    }

    public function view(User $actor, OrganizationMaster $master): bool
    {
        return OrganizationMasters::visible($actor, $master->type())->whereKey($master->id)->exists();
    }

    public function create(User $actor, MasterType $type, ?int $ownerId = null): bool
    {
        if (! $this->viewAny($actor) || ! $actor->checkPermissionTo(OrganizationMasters::MANAGE)) {
            return false;
        }

        $owner = $type->owner();

        if ($owner === null) {
            return IdentityAccess::superAdminCan($actor, OrganizationMasters::MANAGE);
        }

        $owners = OrganizationMasters::visible($actor, $owner);
        if ($ownerId !== null) {
            $owners->whereKey($ownerId);
        }

        return $owners->exists();
    }

    public function update(User $actor, OrganizationMaster $master): bool
    {
        return $this->view($actor, $master) && $actor->checkPermissionTo(OrganizationMasters::MANAGE)
            && ($master->type() !== MasterType::Position || IdentityAccess::superAdminCan($actor, OrganizationMasters::MANAGE));
    }
}
