<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Queries\AssetReports;
use App\Queries\Assets;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class WorkspaceController
{
    public function __invoke(Request $request): View
    {
        if (Gate::denies('viewAny', Asset::class)) {
            return view('auth.workspace');
        }

        $actor = $request->user();
        $filters = array_filter($request->validate(['branch' => ['nullable', 'integer', 'min:1']],
            attributes: __('reports.filters')), fn ($value) => $value !== null);
        $branches = AssetReports::branches($actor);
        $totals = AssetReports::totals(AssetReports::current($actor, $filters));
        $locations = AssetReports::locations($actor, $filters);
        $recent = $branches->isEmpty() ? collect() : AssetReports::events($actor, $filters + ['from' => today()->subDays(29)->toDateString()])->limit(5)->get();
        $personal = Assets::personal($actor)->with('location.owner')->orderBy('asset_code')
            ->paginate(8, ['id', 'asset_code', 'name', 'physical_location_id'], 'personal_page')->withQueryString();

        return view('reports.workspace', compact('branches', 'filters', 'totals', 'locations', 'recent', 'personal'));
    }
}
