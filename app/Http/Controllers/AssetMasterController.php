<?php

namespace App\Http\Controllers;

use App\Actions\Assets\SaveAssetMaster;
use App\Models\AssetCatalogDefault;
use App\Models\AssetCategory;
use App\Models\AssetMaster;
use App\Support\AssetMasterType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AssetMasterController
{
    public function index(Request $request, AssetMasterType $section): View
    {
        Gate::authorize('viewAny', $section->model());
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,archived,all']],
            attributes: ['q' => __('masters.search'), 'status' => __('masters.status')]);
        $search = $filters['q'] ?? '';
        $status = $filters['status'] ?? 'active';
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $relation = match ($section) {
            AssetMasterType::Category => ['parent'],
            AssetMasterType::Type => ['category'],
            default => [],
        };
        $rows = $section->model()::query()->with($relation)
            ->when($search !== '', fn ($query) => $query->where(fn ($filter) => $filter
                ->whereRaw("code LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("name LIKE ? ESCAPE '!'", [$pattern])))
            ->when($status !== 'all', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')->orderBy('id')->simplePaginate(20)->withQueryString();
        $defaultId = $this->defaultId($section);

        return view('asset-catalog.index', compact('section', 'rows', 'search', 'status', 'defaultId'));
    }

    public function create(AssetMasterType $section): View
    {
        Gate::authorize('create', $section->model());

        return $this->form($section, null);
    }

    public function show(AssetMasterType $section, int $master): View
    {
        Gate::authorize('viewAny', $section->model());
        Gate::authorize('view', $record = $section->model()::query()->findOrFail($master));

        return $this->form($section, $record);
    }

    public function store(Request $request, AssetMasterType $section, SaveAssetMaster $save): RedirectResponse
    {
        $record = $save->save($request->user(), $section, null, $this->input($request));

        return to_route('asset-catalog.show', [$section, $record])->with('status', __('masters.saved'));
    }

    public function update(Request $request, AssetMasterType $section, int $master, SaveAssetMaster $save): RedirectResponse
    {
        $save->save($request->user(), $section, $master, $this->input($request));

        return to_route('asset-catalog.show', [$section, $master])->with('status', __('masters.saved'));
    }

    public function setDefault(Request $request, AssetMasterType $section, int $master, SaveAssetMaster $save): RedirectResponse
    {
        $save->setDefault($request->user(), $section, $master);

        return to_route('asset-catalog.show', [$section, $master])->with('status', __('masters.saved'));
    }

    private function form(AssetMasterType $section, ?AssetMaster $record): View
    {
        $defaultId = $this->defaultId($section);
        $canWrite = $record === null || Gate::allows('update', $record);
        $categories = $canWrite && ($section === AssetMasterType::Category || ($section === AssetMasterType::Type && $record === null))
            ? AssetCategory::query()->where(fn ($query) => $query->where('is_active', true)
                ->when($record?->parent_id !== null, fn ($query) => $query->orWhereKey($record->parent_id)))
                ->when($record !== null, fn ($query) => $query->whereKeyNot($record->id))->orderBy('name')->get()
            : collect();

        return view('asset-catalog.form', compact('section', 'record', 'categories', 'defaultId', 'canWrite'));
    }

    private function input(Request $request): array
    {
        return $request->only('master_code', 'name', 'is_active', 'category_id', 'parent_id');
    }

    private function defaultId(AssetMasterType $section): ?int
    {
        $column = $section->defaultColumn();

        return $column === null ? null : AssetCatalogDefault::query()->whereKey(AssetCatalogDefault::ID)->value($column);
    }
}
