<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class OrganizationScope
{
    public const TABLES = ['branch' => 'branches', 'division' => 'divisions', 'department' => 'departments'];

    public static function branches(User $actor): Builder
    {
        return DB::table('branches')->select('id')->whereIn('id', self::grants($actor, 'branch'));
    }

    public static function divisions(User $actor): Builder
    {
        return DB::table('divisions')->select('id')->where(function (Builder $query) use ($actor): void {
            $query->whereIn('id', self::grants($actor, 'division'))
                ->orWhereIn('branch_id', self::branches($actor));
        });
    }

    public static function departments(User $actor): Builder
    {
        return DB::table('departments')->select('id')->where(function (Builder $query) use ($actor): void {
            $query->whereIn('id', self::grants($actor, 'department'))
                ->orWhereIn('division_id', self::divisions($actor));
        });
    }

    private static function grants(User $actor, string $type): Builder
    {
        return DB::table('user_data_scopes')
            ->select($type.'_id')
            ->where('user_id', $actor->getKey())
            ->where('scope_type', $type);
    }
}
