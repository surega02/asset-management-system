<?php

namespace App\Http\Controllers;

use App\Actions\Assets\PrintAssetLabels;
use App\Models\Asset;
use App\Queries\Assets;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class AssetLabelController
{
    public function create(Request $request): View
    {
        Gate::authorize('printAny', Asset::class);
        $search = $request->validate(['q' => ['nullable', 'string', 'max:255']], attributes: ['q' => __('masters.search')])['q'] ?? '';
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $rows = Assets::operational($request->user(), Assets::PRINT_LABEL)
            ->when($search !== '', fn ($query) => $query->where(fn ($filter) => $filter
                ->whereRaw("asset_code LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("name LIKE ? ESCAPE '!'", [$pattern])))
            ->orderBy('asset_code')->simplePaginate(25, ['id', 'asset_code', 'name'])->withQueryString();

        return $this->form($rows, $search, null);
    }

    public function single(Asset $asset): View
    {
        Gate::authorize('printLabel', $asset);

        return $this->form(collect([$asset]), '', $asset);
    }

    public function store(Request $request, PrintAssetLabels $print): RedirectResponse
    {
        Gate::authorize('printAny', Asset::class);
        $data = $request->validate([
            'asset_ids' => ['required', 'array', 'min:1', 'max:'.PrintAssetLabels::LIMIT],
            'asset_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'template_id' => ['required', 'integer', 'min:1'],
            'fields' => ['sometimes', 'array', 'max:3'],
            'fields.*' => ['string', 'distinct', Rule::in(PrintAssetLabels::OPTIONAL_FIELDS)],
        ], attributes: [
            'asset_ids' => __('qr.selection'), 'asset_ids.*' => __('qr.selection'),
            'template_id' => __('qr.template'), 'fields' => __('qr.fields'), 'fields.*' => __('qr.fields'),
        ]);
        $job = $print->prepare($request->user(), array_map('intval', $data['asset_ids']), (int) $data['template_id'], array_values($data['fields'] ?? []));

        return to_route('assets.labels.preview', $job);
    }

    public function preview(Request $request, int $job, PrintAssetLabels $print): Response
    {
        return response()->view('assets.label-preview', $print->preview($request->user(), $job), headers: [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
        ]);
    }

    private function form(Collection|Paginator $rows, string $search, ?Asset $single): View
    {
        $templates = DB::table('asset_label_templates')->where('is_active', true)->orderBy('width_mm')->orderBy('id')->get();
        $fields = PrintAssetLabels::OPTIONAL_FIELDS;

        return view('assets.labels', compact('rows', 'search', 'single', 'templates', 'fields'));
    }
}
