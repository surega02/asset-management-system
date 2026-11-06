<x-layouts.account :title="__('assets.title')">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p>{{ __('assets.list_help') }}</p>
        <div class="flex flex-wrap gap-2">
            <a class="ui-button" href="{{ route('assets.scan') }}">{{ __('qr.scan_title') }}</a>
            @can('printAny', \App\Models\Asset::class)
                <a class="ui-button" href="{{ route('assets.labels.create') }}">{{ __('qr.labels') }}</a>
            @endcan
            @can('create', \App\Models\Asset::class)
                <a class="ui-button ui-button-primary" href="{{ route('assets.create') }}">{{ __('assets.register') }}</a>
            @endcan
        </div>
    </div>
    <form method="get" class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
        <x-ui.field name="q" type="search" :label="__('masters.search')" :value="$search" maxlength="255" />
        <x-ui.select-field name="state" :label="__('assets.lifecycle')">
            @foreach(['all', 'active', 'inactive'] as $filter)
                <option value="{{ $filter }}" @selected($state === $filter)>{{ __('assets.'.$filter) }}</option>
            @endforeach
        </x-ui.select-field>
        <x-ui.button type="submit">{{ __('masters.apply') }}</x-ui.button>
    </form>
    <x-ui.panel>
        @if($rows->isEmpty())
            <div class="grid gap-2 p-5">
                <h2>{{ __('assets.empty') }}</h2>
                <p>{{ __('assets.empty_help') }}</p>
            </div>
        @else
            <table class="w-full table-fixed text-sm">
                <caption class="sr-only">{{ __('assets.title') }}</caption>
                <thead class="bg-[var(--subtle)]">
                    <tr>
                        <th scope="col" class="p-3 text-start">{{ __('assets.fields.name') }}</th>
                        <th scope="col" class="w-[40%] p-3 text-start sm:w-64">{{ __('assets.lifecycle') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $asset)
                        <tr class="border-t border-[var(--border)] align-top">
                            <th scope="row" class="wrap-anywhere p-3 text-start font-normal">
                                <a class="inline-flex min-h-11 items-center font-semibold underline underline-offset-4" href="{{ route('assets.show', $asset) }}">{{ $asset->name }}</a>
                                <span class="block text-[var(--muted)]">{{ $asset->asset_code }} · {{ $asset->location->name }}</span>
                            </th>
                            <td class="wrap-anywhere p-3">
                                {{ __('assets.'.($asset->is_active ? 'active' : 'inactive')) }}
                                <span class="block text-[var(--muted)]">{{ $asset->status->name }} · {{ $asset->condition->name }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.panel>
    @include('people.pagination', ['pages' => $rows])
</x-layouts.account>
