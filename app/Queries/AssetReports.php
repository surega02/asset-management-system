<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AssetReports
{
    public const DATES = ['purchase' => 'assets.purchase_date', 'registration' => 'assets.registered_at', 'change' => 'assets.updated_at'];

    public const GROUPS = [
        'branch' => ['branch.id', 'branch.name'],
        'department' => ['department.id', 'department.name'],
        'employee' => ['employee.id', 'employee.name'],
        'category' => ['category.id', 'category.name'],
        'condition' => ['condition.id', 'condition.name'],
    ];

    public const EVENTS = ['location', 'assignment', 'status', 'condition'];

    public static function branches(User $actor): Collection
    {
        return DB::table('branches')->whereIn('id', Assets::locations($actor, Assets::VIEW)->select('branch_id'))
            ->orderBy('name')->orderBy('id')->pluck('name', 'id');
    }

    private static function scoped(User $actor, array $filters): Builder
    {
        return DB::table('assets')->join('locations as physical', 'physical.id', '=', 'assets.physical_location_id')
            ->join('branches as branch', 'branch.id', '=', 'physical.branch_id')
            ->whereIn('assets.id', Assets::operational($actor)->select('assets.id'))
            ->when(isset($filters['branch']), fn (Builder $query) => $query->where('branch.id', $filters['branch']))
            ->when(($filters['q'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
                $query->where(fn (Builder $filter) => $filter->whereRaw("assets.asset_code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("assets.name LIKE ? ESCAPE '!'", [$pattern]));
            });
    }

    public static function current(User $actor, array $filters): Builder
    {
        $query = self::scoped($actor, $filters)
            ->join('asset_types as type', 'type.id', '=', 'assets.asset_type_id')
            ->join('asset_categories as category', 'category.id', '=', 'type.category_id')
            ->join('asset_statuses as status', 'status.id', '=', 'assets.status_id')
            ->join('asset_conditions as condition', 'condition.id', '=', 'assets.condition_id')
            ->leftJoin('asset_assignments as assignment', 'assignment.active_asset_id', '=', 'assets.id')
            ->leftJoin('employees as employee', 'employee.id', '=', 'assignment.employee_id')
            ->leftJoin('departments as department', DB::raw('COALESCE(assignment.department_id, employee.department_id)'), '=', 'department.id')
            ->leftJoin('locations as responsible_location', 'responsible_location.id', '=', 'assignment.location_id')
            ->when(isset($filters['state']), fn (Builder $query) => $query->where('assets.is_active', $filters['state'] === 'active'));
        $date = $filters['date'] ?? 'registration';

        return self::period($query, self::DATES[$date], $filters, $date === 'purchase');
    }

    public static function units(Builder $current): Builder
    {
        return $current->select([
            'assets.id', 'assets.asset_code as code', 'assets.name', 'branch.name as branch', 'physical.name as location',
            'employee.name as employee', 'department.name as department', 'responsible_location.name as responsible_location',
            'category.name as category', 'status.name as status', 'condition.name as condition', 'assets.is_active',
            'assets.purchase_date as purchase', 'assets.registered_at as registration', 'assets.updated_at as change',
        ])->orderBy('assets.asset_code');
    }

    public static function groups(Builder $current, string $group): Builder
    {
        [$id, $name] = self::GROUPS[$group];
        $label = $current->getGrammar()->wrap($name);

        return $current->selectRaw("$label as name, COUNT(*) as total, SUM(assets.is_active = 1) as active, SUM(assets.is_active = 0) as inactive")
            ->groupBy($id, $name)->orderByRaw("$label IS NULL")->orderBy($name)->orderBy($id);
    }

    public static function totals(Builder $current): object
    {
        return $current->selectRaw('COUNT(*) as total, COALESCE(SUM(assets.is_active = 1), 0) as active, COALESCE(SUM(assets.is_active = 0), 0) as inactive')->first();
    }

    public static function locations(User $actor, array $filters): Collection
    {
        return self::scoped($actor, $filters)->selectRaw('physical.name as name, COUNT(*) as total')
            ->groupBy('physical.id', 'physical.name')->orderByDesc('total')->orderBy('physical.name')->orderBy('physical.id')->limit(6)->get();
    }

    public static function events(User $actor, array $filters): Builder
    {
        $scoped = self::period(self::scoped($actor, $filters)->join('audit_logs as audit', 'audit.asset_id', '=', 'assets.id')
            ->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
            ->leftJoin('employees as operator', 'operator.id', '=', 'actor.employee_id'), 'audit.created_at', $filters);

        $queries = [];
        foreach (isset($filters['kind']) ? [$filters['kind']] : self::EVENTS as $kind) {
            $query = (clone $scoped)->select([
                'audit.id as event_id', 'assets.id', 'assets.asset_code as code', 'assets.name',
                'operator.name as actor', 'audit.reason', 'audit.created_at as occurred',
            ])->selectRaw('? as kind', [$kind]);
            [$before, $after] = match ($kind) {
                'location' => self::eventLocation($query),
                'assignment' => self::eventAssignment($query),
                'status', 'condition' => self::eventState($query, $kind),
            };
            $queries[] = $query->selectRaw("$before as previous_value, $after as next_value");
        }
        $events = array_shift($queries);
        foreach ($queries as $query) {
            $events->unionAll($query);
        }

        return DB::query()->fromSub($events, 'events')->orderByDesc('occurred')->orderByDesc('event_id')->orderBy('kind');
    }

    private static function eventLocation(Builder $query): array
    {
        $query->where('audit.event', 'asset.location_moved');

        return [
            self::reference('locations', 'physical_location_id', 'old'),
            self::reference('locations', 'physical_location_id', 'new'),
        ];
    }

    private static function eventAssignment(Builder $query): array
    {
        $query->whereIn('audit.event', ['asset.reassigned', 'asset.deactivated', 'asset.reactivated']);
        $label = fn (string $side) => 'COALESCE('.implode(', ', [
            self::reference('employees', 'employee_id', $side), self::reference('departments', 'department_id', $side),
            self::reference('locations', 'location_id', $side),
        ]).')';

        return [$label('old'), $label('new')];
    }

    private static function eventState(Builder $query, string $kind): array
    {
        $key = $kind.'_id';
        $table = $kind === 'status' ? 'asset_statuses' : 'asset_conditions';
        $old = self::auditValue($key, 'old');
        $new = self::auditValue($key, 'new');
        $query->whereIn('audit.event', ['asset.state_changed', 'asset.reactivated'])
            ->whereRaw("NOT ($old <=> $new)");

        return [self::reference($table, $key, 'old'), self::reference($table, $key, 'new')];
    }

    private static function reference(string $table, string $key, string $side): string
    {
        $value = self::auditValue($key, $side);

        return "(SELECT reference_item.name FROM `$table` as reference_item WHERE reference_item.id = CAST($value AS UNSIGNED))";
    }

    private static function auditValue(string $key, string $side): string
    {
        return "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(audit.{$side}_values, '$.$key')), 'null')";
    }

    private static function period(Builder $query, string $column, array $filters, bool $dateOnly = false): Builder
    {
        return $query
            ->when(isset($filters['from']), fn (Builder $query) => $query->where($column, '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->where($column, '<=', $filters['to'].($dateOnly ? '' : ' 23:59:59.999999')));
    }
}
