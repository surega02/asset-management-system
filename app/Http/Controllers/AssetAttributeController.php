<?php

namespace App\Http\Controllers;

use App\Actions\Assets\ManageAssetAttributes;
use App\Models\AssetType;
use App\Models\AttributeDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AssetAttributeController
{
    public function index(AssetType $assetType): View
    {
        Gate::authorize('view', $assetType);
        $rows = AttributeDefinition::query()->where('asset_type_id', $assetType->id)
            ->with('configuration')->orderByDesc('is_active')->orderBy('key')->orderByDesc('version')->simplePaginate(20);

        return view('asset-catalog.attributes', compact('assetType', 'rows'));
    }

    public function create(AssetType $assetType): View
    {
        Gate::authorize('update', $assetType);

        return view('asset-catalog.attribute-form', ['assetType' => $assetType, 'definition' => null]);
    }

    public function edit(AssetType $assetType, int $version): View
    {
        Gate::authorize('view', $assetType);
        $definition = AttributeDefinition::query()->where('asset_type_id', $assetType->id)->with('configuration')->findOrFail($version);

        return view('asset-catalog.attribute-form', compact('assetType', 'definition'));
    }

    public function store(Request $request, AssetType $assetType, ManageAssetAttributes $manage): RedirectResponse
    {
        $manage->save($request->user(), $assetType, null, $this->input($request));

        return to_route('asset-attributes.index', $assetType)->with('status', __('masters.saved'));
    }

    public function update(Request $request, AssetType $assetType, int $version, ManageAssetAttributes $manage): RedirectResponse
    {
        $manage->save($request->user(), $assetType, $version, $this->input($request));

        return to_route('asset-attributes.index', $assetType)->with('status', __('masters.saved'));
    }

    public function archive(Request $request, AssetType $assetType, int $version, ManageAssetAttributes $manage): RedirectResponse
    {
        $manage->archive($request->user(), $assetType, $version);

        return to_route('asset-attributes.index', $assetType)->with('status', __('masters.saved'));
    }

    private function input(Request $request): array
    {
        return $request->only('key', 'label', 'data_type', 'options', 'min', 'max', 'is_required', 'sort_order');
    }
}
