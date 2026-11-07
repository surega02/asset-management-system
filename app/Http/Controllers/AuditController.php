<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AuditController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('view-audit');
        $date = ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];
        $filters = $request->validate([
            'actor' => ['nullable', 'integer', 'min:1'], 'event' => ['nullable', 'string', 'max:80'],
            'asset' => ['nullable', 'string', 'regex:/\AAST-[0-9]{6,19}\z/i'],
            'from' => $date, 'to' => $request->filled('from') ? [...$date, 'after_or_equal:from'] : $date,
        ], attributes: __('system.audit_fields'));
        $viewer = $request->user();
        $query = DB::table('audit_logs as audit')->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
            ->leftJoin('employees as person', 'person.id', '=', 'actor.employee_id')
            ->leftJoin('assets as asset', 'asset.id', '=', 'audit.asset_id');
        if (! IdentityAccess::superAdminCan($viewer, 'audit.view')) {
            $query->whereIn('audit.asset_id', Assets::operational($viewer)->select('assets.id'));
        }
        $events = (clone $query)->distinct()->orderBy('audit.event')->pluck('audit.event');
        $actors = (clone $query)->whereNotNull('person.id')->distinct()->orderBy('person.name')->pluck('person.name', 'actor.id');
        $rows = $query->when(isset($filters['actor']), fn ($q) => $q->where('audit.actor_id', $filters['actor']))
            ->when(isset($filters['event']), fn ($q) => $q->where('audit.event', $filters['event']))
            ->when(isset($filters['asset']), fn ($q) => $q->where('asset.asset_code', strtoupper($filters['asset'])))
            ->when(isset($filters['from']), fn ($q) => $q->where('audit.created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->where('audit.created_at', '<=', $filters['to'].' 23:59:59.999999'))
            ->orderByDesc('audit.id')->simplePaginate(20, [
                'audit.created_at', 'audit.event', 'audit.subject_type', 'audit.reason', 'audit.old_values', 'audit.new_values',
                'person.name as actor_name', 'asset.asset_code',
            ])->withQueryString()->through(function (object $row): object {
                $row->reason = IdentityAudit::safeText($row->reason);
                foreach (['old_values', 'new_values'] as $field) {
                    $values = json_decode($row->{$field} ?? 'null', true);
                    $values = is_array($values) ? $values : [];
                    unset($values['allowed_options'], $values['validation_rules']);
                    if (in_array($row->subject_type, [Employee::class, User::class], true)) {
                        unset($values['name'], $values['joined_at']);
                    }
                    $row->{$field} = IdentityAudit::safeValues($values);
                }

                return $row;
            });

        return view('system.audit', compact('rows', 'events', 'actors', 'filters'));
    }
}
