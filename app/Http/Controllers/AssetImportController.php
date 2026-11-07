<?php

namespace App\Http\Controllers;

use App\Actions\Assets\AssetTargets;
use App\Actions\Imports\AssetImportBatches;
use App\Actions\Imports\AssetImportSheet;
use App\Models\Asset;
use App\Models\ImportRow;
use App\Queries\Assets;
use App\Support\SpreadsheetDownload;
use Generator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AssetImportController
{
    public function create(Request $request): View
    {
        Gate::authorize('import', Asset::class);
        $batches = AssetImportBatches::recent()->where('user_id', $request->user()->id)->orderByDesc('id')->simplePaginate(15);

        return view('assets.import.create', compact('batches'));
    }

    public function template(string $format): Response|StreamedResponse
    {
        Gate::authorize('import', Asset::class);

        return $format === 'xlsx'
            ? SpreadsheetDownload::xlsxTemplate('asset-import', AssetImportSheet::COLUMNS)
            : SpreadsheetDownload::csv('asset-import', [AssetImportSheet::COLUMNS]);
    }

    public function references(Request $request, AssetTargets $targets): StreamedResponse
    {
        Gate::authorize('import', Asset::class);
        $actor = $request->user();
        $rows = [['column', 'value', 'name', 'category_id']];
        foreach (['asset_categories' => 'category_id', 'asset_types' => 'asset_type_id', 'asset_statuses' => 'status_id',
            'asset_conditions' => 'condition_id', 'brands' => 'brand_id', 'vendors' => 'vendor_id'] as $table => $field) {
            $query = DB::table($table)->where('is_active', true)->orderBy('name');
            if ($table === 'asset_types') {
                $query->whereIn('category_id', DB::table('asset_categories')->where('is_active', true)->select('id'));
            }
            foreach ($query->get() as $record) {
                $rows[] = [$field, $record->id, $record->name, $record->category_id ?? ''];
            }
        }
        $locations = Assets::locations($actor, Assets::CREATE)->where('is_active', true)->get(['id', 'name'])
            ->merge(Assets::locations($actor, Assets::MOVE)->where('is_active', true)->get(['id', 'name']));
        foreach ($locations as $location) {
            $rows[] = ['physical_location_id', $location->id, $location->name, ''];
        }
        $create = $targets->options($actor, Assets::CREATE);
        $reassign = $targets->options($actor, Assets::REASSIGN);
        foreach ($create as $kind => $options) {
            foreach ($options->merge($reassign[$kind]) as $record) {
                $rows[] = ['assignee', $kind.':'.$record->id, $record->name, ''];
            }
        }
        foreach (DB::table('asset_type_attributes as config')->join('attribute_definitions as definition', 'definition.id', '=', 'config.attribute_definition_id')
            ->where('definition.is_active', true)->orderBy('definition.asset_type_id')->orderBy('config.sort_order')
            ->get(['definition.key', 'definition.label', 'definition.asset_type_id', 'definition.data_type']) as $attribute) {
            $rows[] = ['attr.'.$attribute->key, $attribute->asset_type_id, $attribute->label.' ('.$attribute->data_type.')', ''];
        }

        return SpreadsheetDownload::csv('asset-import-references', $rows);
    }

    public function store(Request $request, AssetImportBatches $imports): RedirectResponse
    {
        Gate::authorize('import', Asset::class);
        $file = $request->validate(['file' => ['required', 'file', 'max:10240']],
            ['file.max' => __('people.import_file_invalid')], ['file' => __('people.file')])['file'];

        return to_route('assets.import.show', $imports->upload($request->user(), $file));
    }

    public function show(Request $request, int $batch, AssetImportBatches $imports): View
    {
        $actor = $request->user();
        $record = $imports->owned($actor, $batch);
        $query = ImportRow::query()->where('import_batch_id', $batch);
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $rows = $query->orderBy('row_number')->simplePaginate(20);
        $preview = $record->status === 'preview' ? $rows->getCollection()->mapWithKeys(fn (ImportRow $row) => [$row->id => $imports->describe($actor, $row)]) : collect();
        $visible = Assets::visible($actor)->whereKey($rows->getCollection()->pluck('asset_id')->filter()->all())->pluck('asset_code', 'id');
        $attempts = DB::table('import_row_attempts')->whereIn('import_row_id', $rows->getCollection()->modelKeys())
            ->orderBy('attempt_number')->get(['import_row_id', 'attempt_number', 'outcome', 'attempted_at'])->groupBy('import_row_id');

        return view('assets.import.show', compact('record', 'counts', 'rows', 'preview', 'visible', 'attempts'));
    }

    public function decide(Request $request, int $batch, AssetImportBatches $imports): RedirectResponse
    {
        $data = $request->validate(['choices' => ['sometimes', 'array', 'max:20'], 'choices.*' => ['in:create,update,skip'],
            'intent' => ['required', 'in:save,confirm']], attributes: ['choices.*' => __('asset_import.action')]);
        $choices = $data['choices'] ?? [];
        foreach (array_keys($choices) as $id) {
            if (! is_int($id) || $id < 1) {
                throw ValidationException::withMessages(['choices' => __('asset_import.invalid_row')]);
            }
        }
        $confirm = $data['intent'] === 'confirm';
        $imports->decide($request->user(), $batch, $choices, $confirm);

        return to_route('assets.import.show', ['batch' => $batch, 'page' => $confirm ? 1 : $request->integer('page', 1)])
            ->with('status', __($confirm ? 'asset_import.queued' : 'asset_import.saved'));
    }

    public function retry(Request $request, int $batch, int $row, AssetImportBatches $imports): RedirectResponse
    {
        $data = $request->validate(['attempt' => ['required', 'integer', 'min:1'], 'values' => ['sometimes', 'array', 'max:80'],
            'values.*' => ['nullable', 'string', 'max:2000']], attributes: ['values.*' => __('asset_import.value')]);
        $values = $data['values'] ?? [];
        unset($values['asset_code']);
        $imports->retry($request->user(), $batch, $row, (int) $data['attempt'], $values);

        return to_route('assets.import.show', $batch)->with('status', __('asset_import.queued'));
    }

    public function errors(Request $request, int $batch, AssetImportBatches $imports): StreamedResponse
    {
        $imports->owned($request->user(), $batch);
        $rows = ImportRow::query()->where('import_batch_id', $batch)->where('status', 'failed')->orderBy('row_number')->get();
        $columns = AssetImportSheet::COLUMNS;
        foreach ($rows as $row) {
            $columns = array_values(array_unique([...$columns, ...array_keys($row->payload ?? [])]));
        }
        $lines = function () use ($rows, $columns): Generator {
            yield ['row_number', 'action', 'error', 'fields', ...$columns];
            foreach ($rows as $row) {
                yield [
                    $row->row_number, $row->action, __('asset_import.'.$row->errors['key']), implode(', ', $row->errors['fields'] ?? []),
                    ...array_map(fn ($column) => $row->payload[$column] ?? '', $columns),
                ];
            }
        };

        return SpreadsheetDownload::csv('asset-import-errors-'.$batch, $lines());
    }
}
