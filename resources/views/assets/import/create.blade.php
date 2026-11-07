<x-layouts.account :title="__('asset_import.title')">
    <div>
        <a class="nav-link" href="{{ route('assets.index') }}">{{ __('assets.back') }}</a>
    </div>
    <p class="max-w-prose">{{ __('asset_import.upload_help') }}</p>
    <div class="grid items-start gap-5 lg:grid-cols-2">
        <x-ui.panel class="grid gap-4 p-4 sm:p-5" aria-labelledby="upload-heading">
            <h2 id="upload-heading">{{ __('asset_import.upload') }}</h2>
            <form action="{{ route('assets.import.store') }}" method="post" enctype="multipart/form-data" class="grid gap-3">
                @csrf
                <x-ui.field name="file" type="file" :label="__('people.file')" accept=".csv,.xlsx" required />
                <p>{{ __('asset_import.file_limits') }}</p>
                <x-ui.button class="justify-self-start" variant="primary" type="submit">{{ __('asset_import.preview_button') }}</x-ui.button>
            </form>
        </x-ui.panel>
        <section class="grid gap-3 pt-1" aria-labelledby="template-heading">
            <h2 id="template-heading">{{ __('asset_import.prepare') }}</h2>
            <p>{{ __('asset_import.prepare_help') }}</p>
            <div class="flex flex-wrap gap-2">
                <a class="ui-button" href="{{ route('assets.import.template', 'xlsx') }}">{{ __('asset_import.template_xlsx') }}</a>
                <a class="ui-button" href="{{ route('assets.import.template', 'csv') }}">{{ __('asset_import.template_csv') }}</a>
                <a class="ui-button" href="{{ route('assets.import.references') }}">{{ __('asset_import.references') }}</a>
            </div>
            <p>{{ __('asset_import.column_help') }}</p>
        </section>
    </div>
    <section class="grid gap-3" aria-labelledby="history-heading">
        <h2 id="history-heading">{{ __('asset_import.history') }}</h2>
        <x-ui.panel>
            @forelse($batches as $batch)
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] p-4 last:border-0">
                    <div>
                        <a class="font-semibold underline underline-offset-4" href="{{ route('assets.import.show', $batch->id) }}">
                            {{ __('asset_import.batch', ['id' => $batch->id]) }}
                        </a>
                        <p><time datetime="{{ \Illuminate\Support\Carbon::parse($batch->created_at)->toAtomString() }}">{{ $batch->created_at }}</time></p>
                    </div>
                    <p>{{ __('asset_import.'.$batch->status) }} · {{ __('asset_import.row_count', ['count' => $batch->total_rows]) }}</p>
                </div>
            @empty
                <p class="p-4">{{ __('asset_import.empty') }}</p>
            @endforelse
        </x-ui.panel>
        @include('people.pagination', ['pages' => $batches])
    </section>
</x-layouts.account>
