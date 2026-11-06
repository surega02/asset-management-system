<?php

namespace App\Http\Controllers;

use App\Actions\Assets\AssetLifecycle;
use App\Actions\Assets\AssetTargets;
use App\Actions\Assets\RegisterAsset;
use App\Actions\Assets\ResolveAssetCatalog;
use App\Models\Asset;
use App\Models\AssetCatalogDefault;
use App\Models\AssetCondition;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Vendor;
use App\Queries\Assets;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AssetController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Asset::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'state' => ['nullable', 'in:all,active,inactive']],
            attributes: ['q' => __('masters.search'), 'state' => __('assets.lifecycle')]);
        $search = $filters['q'] ?? '';
        $state = $filters['state'] ?? 'all';
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $rows = Assets::visible($request->user())->with('location', 'status', 'condition')
            ->when($search !== '', fn ($query) => $query->where(fn ($filter) => $filter
                ->whereRaw("asset_code LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("name LIKE ? ESCAPE '!'", [$pattern])))
            ->when($state !== 'all', fn ($query) => $query->where('is_active', $state === 'active'))
            ->orderByDesc('registered_at')->orderByDesc('id')->simplePaginate(20)->withQueryString();

        return view('assets.index', compact('rows', 'search', 'state'));
    }

    public function create(Request $request, AssetTargets $targets, ResolveAssetCatalog $catalog): View
    {
        Gate::authorize('create', Asset::class);
        $id = $request->validate(['type' => ['nullable', 'integer', 'min:1']], attributes: ['type' => __('assets.fields.asset_type_id')])['type'] ?? null;
        $types = AssetType::query()->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with('category')->orderBy('name')->orderBy('id')->get();
        $type = $id === null ? null : $types->find((int) $id);
        abort_if($id !== null && $type === null, 404);
        $defaults = AssetCatalogDefault::query()->findOrFail(AssetCatalogDefault::ID);

        return view('assets.create', [
            'types' => $types, 'type' => $type,
            'fields' => $type ? $catalog->fields($type->id) : collect(),
            'targets' => $type ? $targets->options($request->user(), Assets::CREATE) : [],
            'statuses' => $this->options(AssetStatus::class), 'conditions' => $this->options(AssetCondition::class),
            'brands' => $this->options(Brand::class), 'vendors' => $this->options(Vendor::class),
            'statusId' => $defaults->status_id, 'conditionId' => $defaults->condition_id,
            'locations' => Assets::locations($request->user(), Assets::CREATE)->where('is_active', true)->with('owner')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, RegisterAsset $register): RedirectResponse
    {
        Gate::authorize('create', Asset::class);
        $asset = $register($request->user(), $request->only('name', 'category_id', 'asset_type_id', 'physical_location_id', 'status_id', 'condition_id',
            'purchase_date', 'acquisition_cost', 'serial_number', 'invoice_number', 'brand_id', 'vendor_id', 'warranty_ends_on', 'attributes')
            + AssetTargets::decodeSelection($request->input('assignee')), $request->only('photo', 'invoice'));

        return to_route('assets.show', $asset)->with('status', __('assets.registered', ['code' => $asset->asset_code]));
    }

    public function show(Request $request, Asset $asset, AssetTargets $targets): View
    {
        Gate::authorize('view', $asset);
        $asset->load('type.category', 'location.owner', 'status', 'condition', 'brand', 'vendor',
            'activeAssignment.employee', 'activeAssignment.department', 'activeAssignment.location', 'attachments');
        $attributes = DB::table('asset_attribute_values as value')->where('value.asset_id', $asset->id)
            ->join('attribute_definitions as definition', 'definition.id', '=', 'value.attribute_definition_id')
            ->orderBy('definition.label')->get(['definition.label', 'definition.data_type', 'definition.version', 'value.value_json']);
        $canLifecycle = Gate::allows('lifecycle', $asset);
        $canUpdateState = $asset->is_active && Gate::allows('updateState', $asset);
        $defaults = AssetCatalogDefault::query()->findOrFail(AssetCatalogDefault::ID);
        $lastDeactivation = DB::table('asset_deactivations')->where('asset_id', $asset->id)->latest('deactivated_at')->first();

        return view('assets.show', [
            'asset' => $asset, 'attributes' => $attributes, 'lastDeactivation' => $lastDeactivation,
            'canLifecycle' => $canLifecycle, 'canUpdateState' => $canUpdateState,
            'targets' => $canLifecycle && ! $asset->is_active ? $targets->options($request->user(), Assets::LIFECYCLE) : [],
            'statuses' => $this->options(AssetStatus::class), 'conditions' => $this->options(AssetCondition::class),
            'statusId' => $asset->status->is_active ? $asset->status_id : $defaults->status_id,
            'conditionId' => $asset->condition->is_active ? $asset->condition_id : $defaults->condition_id,
        ]);
    }

    public function deactivate(Request $request, Asset $asset, AssetLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('lifecycle', $asset);
        $file = $request->validate(['deactivation' => ['nullable', 'file']], attributes: ['deactivation' => __('assets.deactivation_file')])['deactivation'] ?? null;
        $lifecycle->deactivate($request->user(), $asset, $request->only('reason'), $file);

        return to_route('assets.show', $asset)->with('status', __('assets.deactivated'));
    }

    public function reactivate(Request $request, Asset $asset, AssetLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('lifecycle', $asset);
        $lifecycle->reactivate($request->user(), $asset,
            $request->only('reason', 'status_id', 'condition_id') + AssetTargets::decodeSelection($request->input('assignee')));

        return to_route('assets.show', $asset)->with('status', __('assets.reactivated'));
    }

    public function updateState(Request $request, Asset $asset, AssetLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->updateState($request->user(), $asset, $request->only('reason', 'status_id', 'condition_id'));

        return to_route('assets.show', $asset)->with('status', __('masters.saved'));
    }

    private function options(string $model): Collection
    {
        return $model::query()->where('is_active', true)->orderBy('name')->orderBy('id')->get();
    }
}
