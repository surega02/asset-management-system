<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Queries\AssetReports;
use App\Support\IdentityAudit;
use App\Support\SpreadsheetDownload;
use Generator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AssetReportController
{
    public function show(Request $request, string $mode = 'units'): View
    {
        [$query, $filters, $columns] = $this->report($request, $mode);
        $rows = $query->simplePaginate(20)->withQueryString()->through(fn (object $row) => [
            'asset' => $row->id ?? null, 'cells' => $this->cells($row, $mode),
        ]);
        $branches = AssetReports::branches($request->user());

        return view('reports.index', compact('rows', 'filters', 'columns', 'branches', 'mode'));
    }

    public function export(Request $request, string $mode, string $format): BinaryFileResponse|StreamedResponse
    {
        [$query, , $columns] = $this->report($request, $mode);
        $rows = function () use ($query, $columns, $mode): Generator {
            yield $columns;
            foreach ($query->cursor() as $row) {
                yield $this->cells($row, $mode);
            }
        };
        $name = 'ams-'.$mode;

        return $format === 'csv' ? SpreadsheetDownload::csv($name, $rows()) : SpreadsheetDownload::xlsx($name, $rows());
    }

    private function report(Request $request, string $mode): array
    {
        Gate::authorize('viewAny', Asset::class);
        $date = ['nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];
        $rules = [
            'branch' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:120'],
            'from' => $date, 'to' => $request->filled('from') ? [...$date, 'after_or_equal:from'] : $date,
        ];
        if ($mode === 'events') {
            $rules['kind'] = ['nullable', Rule::in(AssetReports::EVENTS)];
        } else {
            $rules['state'] = ['nullable', 'in:active,inactive'];
            $rules['date'] = ['nullable', Rule::in(array_keys(AssetReports::DATES))];
            if ($mode === 'groups') {
                $rules['group'] = ['nullable', Rule::in(array_keys(AssetReports::GROUPS))];
            }
        }
        $filters = array_filter($request->validate($rules, attributes: __('reports.filters')), fn ($value) => $value !== null);

        if ($mode === 'events') {
            return [AssetReports::events($request->user(), $filters), $filters, $this->columns([
                'code', 'name', 'occurred', 'kind', 'previous_value', 'next_value', 'actor', 'reason',
            ])];
        }
        $current = AssetReports::current($request->user(), $filters);
        if ($mode === 'groups') {
            $group = $filters['group'] ?? 'branch';

            return [AssetReports::groups($current, $group), $filters, $this->columns([$group, 'total', 'active', 'inactive'])];
        }

        return [AssetReports::units($current), $filters, $this->columns([
            'code', 'name', 'branch', 'location', 'department', 'employee', 'responsible_location',
            'category', 'status', 'condition', 'state', 'purchase', 'registration', 'change',
        ])];
    }

    private function columns(array $keys): array
    {
        return array_map(fn (string $key) => __('reports.columns.'.$key), $keys);
    }

    private function cells(object $row, string $mode): array
    {
        $none = __('reports.none');
        $reason = isset($row->reason) ? IdentityAudit::safeText($row->reason) : $none;

        return match ($mode) {
            'events' => [$row->code, $row->name, $row->occurred, __('reports.events.'.$row->kind),
                $row->previous_value ?? $none, $row->next_value ?? $none, $row->actor ?? $none, $reason === '[redacted]' ? __('system.redacted') : $reason],
            'groups' => [$row->name ?? $none, (int) $row->total, (int) $row->active, (int) $row->inactive],
            default => [$row->code, $row->name, $row->branch, $row->location, $row->department ?? $none,
                $row->employee ?? $none, $row->responsible_location ?? $none, $row->category, $row->status,
                $row->condition, __($row->is_active ? 'assets.active' : 'assets.inactive'), $row->purchase, $row->registration, $row->change],
        };
    }
}
