<?php

namespace App\Queries;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use App\Support\IdentityAccess;
use Illuminate\Database\Eloquent\Builder;

final class Assets
{
    public const VIEW = 'assets.view';

    public const CREATE = 'assets.create';

    public const LIFECYCLE = 'assets.lifecycle';

    public const UPDATE_STATE = 'assets.update-state';

    public const REASSIGN = 'assets.reassign';

    public const MOVE = 'assets.move';

    public const PRINT_LABEL = 'assets.print-label';

    public static function locations(User $actor, string $permission): Builder
    {
        $query = Location::query();
        if (! $actor->is_active || ! $actor->checkPermissionTo($permission)) {
            return $query->whereRaw('1 = 0');
        }

        return IdentityAccess::superAdminCan($actor, $permission)
            ? $query
            : $query->whereIn('branch_id', OrganizationScope::branches($actor));
    }

    public static function visible(User $actor): Builder
    {
        $query = Asset::query();
        if (! $actor->is_active || ! $actor->checkPermissionTo(self::VIEW)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $scope) => $scope
            ->whereIn('physical_location_id', self::locations($actor, self::VIEW)->select('id'))
            ->orWhereHas('activeAssignment', fn (Builder $assignment) => $assignment->where('employee_id', $actor->employee_id)));
    }

    /** Scope for operational reports and exports. Personal access is read-only on the asset. */
    public static function operational(User $actor, string $permission = self::VIEW): Builder
    {
        return Asset::query()->whereIn('physical_location_id', self::locations($actor, $permission)->select('id'));
    }
}
