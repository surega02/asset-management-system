<?php

namespace App\Policies;

use App\Models\AssetMaster;
use App\Models\User;
use App\Support\IdentityAccess;
use Illuminate\Support\Facades\DB;

final class AssetMasterPolicy
{
    public const VIEW = 'asset-catalog.view';

    public const MANAGE = 'asset-catalog.manage';

    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->checkPermissionTo(self::VIEW)
            && (IdentityAccess::superAdminCan($actor, self::VIEW)
                || DB::table('user_data_scopes')->where('user_id', $actor->id)->exists());
    }

    public function view(User $actor, AssetMaster $master): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor) && $actor->checkPermissionTo(self::MANAGE);
    }

    public function update(User $actor, AssetMaster $master): bool
    {
        return $this->create($actor);
    }

    public function setDefault(User $actor, AssetMaster $master): bool
    {
        return $this->update($actor, $master) && IdentityAccess::superAdminCan($actor, self::MANAGE);
    }
}
