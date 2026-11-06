<?php

namespace App\Queries;

use App\Models\User;
use App\Support\IdentityAccess;
use App\Support\MasterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class OrganizationMasters
{
    public const VIEW = 'organization.view';

    public const MANAGE = 'organization.manage';

    public static function canBrowse(User $actor): bool
    {
        return $actor->is_active && $actor->checkPermissionTo(self::VIEW)
            && (IdentityAccess::superAdminCan($actor, self::VIEW)
                || DB::table('user_data_scopes')->where('user_id', $actor->id)->exists());
    }

    public static function visible(User $actor, MasterType $type): Builder
    {
        $query = $type->model()::query();

        if (! self::canBrowse($actor)) {
            return $query->whereRaw('1 = 0');
        }

        if (IdentityAccess::superAdminCan($actor, self::VIEW)) {
            return $query;
        }

        return match ($type) {
            MasterType::Branch => $query->whereIn('id', OrganizationScope::branches($actor)),
            MasterType::Division => $query->whereIn('id', OrganizationScope::divisions($actor)),
            MasterType::Department => $query->whereIn('id', OrganizationScope::departments($actor)),
            MasterType::Location => $query->whereIn('branch_id', OrganizationScope::branches($actor)),
            MasterType::Position => $query,
        };
    }
}
