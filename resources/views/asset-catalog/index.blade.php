<x-layouts.account :title="__('asset_catalog.title')">
    <nav aria-label="{{ __('masters.sections') }}" class="flex flex-wrap gap-1">
        @foreach(\App\Support\AssetMasterType::cases() as $item)
            <a @class(['nav-link', 'nav-link-current' => $item === $section]) href="{{ route('asset-catalog.index', $item) }}"
                @if($item === $section) aria-current="page" @endif>{{ __('asset_catalog.'.$item->value) }}</a>
        @endforeach
    </nav>
    <section aria-labelledby="catalog-heading" class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <h2 id="catalog-heading">{{ __('asset_catalog.'.$section->value) }}</h2>
                <p>{{ __('asset_catalog.help_'.$section->value) }}</p>
            </div>
            @can('create', $section->model())
                <a class="ui-button ui-button-primary" href="{{ route('asset-catalog.create', $section) }}">{{ __('asset_catalog.add') }}</a>
            @endcan
        </div>
        <form method="get" class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
            <x-ui.field name="q" type="search" :label="__('masters.search')" :value="$search" maxlength="255" />
            <x-ui.select-field name="status" :label="__('masters.status')">
                @foreach(['active', 'archived', 'all'] as $state)
                    <option value="{{ $state }}" @selected($status === $state)>{{ __('masters.'.$state) }}</option>
                @endforeach
            </x-ui.select-field>
            <x-ui.button type="submit">{{ __('masters.apply') }}</x-ui.button>
        </form>
        <x-ui.panel>
            @if($rows->isEmpty())
                <div class="grid gap-2 p-5">
                    <h3>{{ __('masters.empty') }}</h3>
                    <p>{{ __('asset_catalog.empty_help') }}</p>
                </div>
            @else
                <table class="w-full table-fixed text-sm">
                    <caption class="sr-only">{{ __('asset_catalog.'.$section->value) }}</caption>
                    <thead class="bg-[var(--subtle)]">
                        <tr>
                            <th scope="col" class="p-3 text-start">{{ __('masters.name') }}</th>
                            <th scope="col" class="w-[38%] p-3 text-start sm:w-44">{{ __('masters.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="border-t border-[var(--border)] align-top">
                                <th scope="row" class="wrap-anywhere p-3 text-start font-normal">
                                    <a class="inline-flex min-h-11 items-center font-semibold underline underline-offset-4" href="{{ route('asset-catalog.show', [$section, $row]) }}">{{ $row->name }}</a>
                                    <span class="block text-[var(--muted)]">{{ $row->code }}</span>
                                    @if($section === \App\Support\AssetMasterType::Category && $row->parent)
                                        <span class="block text-[var(--muted)]">{{ __('asset_catalog.parent') }}: {{ $row->parent->name }}</span>
                                    @elseif($section === \App\Support\AssetMasterType::Type)
                                        <span class="block text-[var(--muted)]">{{ __('asset_catalog.category') }}: {{ $row->category->name }}</span>
                                    @endif
                                </th>
                                <td class="wrap-anywhere p-3">
                                    {{ __('masters.'.($row->is_active ? 'active' : 'archived')) }}
                                    @if($defaultId === $row->id)
                                        <strong class="block">{{ __('asset_catalog.default') }}</strong>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.panel>
        @include('people.pagination', ['pages' => $rows])
    </section>
</x-layouts.account>
