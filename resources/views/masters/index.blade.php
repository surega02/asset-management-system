<x-layouts.account :title="__('masters.title')">
    <nav aria-label="{{ __('masters.sections') }}" class="flex flex-wrap gap-1">
        @foreach(\App\Support\MasterType::cases() as $section)
            <a @class(['nav-link', 'nav-link-current' => $type === $section]) href="{{ route('masters.index', $section) }}"
                @if($type === $section) aria-current="page" @endif>{{ __('masters.'.$section->value) }}</a>
        @endforeach
    </nav>

    <section aria-labelledby="master-list" class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <h2 id="master-list">{{ __('masters.'.$type->value) }}</h2>
                <p>{{ __('masters.help_'.$type->value) }}</p>
            </div>
            @can('create', [$type->model(), $type])
                <a class="ui-button ui-button-primary" href="{{ route('masters.create', $type) }}">{{ __('masters.create_'.$type->value) }}</a>
            @endcan
        </div>

        <form method="get" action="{{ route('masters.index', $type) }}" class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
            <x-ui.field name="q" :label="__('masters.search')" :value="$search" maxlength="255" type="search" />
            <div class="grid gap-1.5">
                <label for="status" class="font-semibold">{{ __('masters.status') }}</label>
                <select id="status" name="status" class="ui-select w-full">
                    @foreach(['active', 'archived', 'all'] as $filter)
                        <option value="{{ $filter }}" @selected($status === $filter)>{{ __('masters.'.$filter) }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit">{{ __('masters.apply') }}</x-ui.button>
        </form>

        <x-ui.panel>
            @if($rows->isEmpty())
                <div class="grid gap-2 p-5">
                    <h3 class="font-semibold">{{ __('masters.empty') }}</h3>
                    <p>{{ $search !== '' || $status !== 'active' ? __('masters.empty_filtered') : __('masters.empty_default') }}</p>
                    @if($search !== '' || $status !== 'active')
                        <a class="ui-button justify-self-start" href="{{ route('masters.index', $type) }}">{{ __('masters.reset') }}</a>
                    @endif
                </div>
            @else
                <table class="w-full table-fixed text-start text-sm">
                    <caption class="sr-only">{{ __('masters.'.$type->value) }}</caption>
                    <thead class="bg-[var(--subtle)]">
                        <tr>
                            <th scope="col" class="p-3 text-start">{{ __('masters.name') }}</th>
                            <th scope="col" class="w-24 p-3 text-start sm:w-36">{{ __('masters.status') }}</th>
                            <th scope="col" class="w-24 p-3 text-end sm:w-36">{{ __('masters.details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="border-t border-[var(--border)] align-top">
                                <th scope="row" class="wrap-anywhere p-3 text-start font-semibold">
                                    {{ $row->name }}
                                    <span class="block font-normal text-[var(--muted)]">{{ $row->code }}</span>
                                    @if($type->owner())
                                        <span class="block font-normal text-[var(--muted)]">{{ $row->owner->name }}</span>
                                    @endif
                                    @if($type === \App\Support\MasterType::Location && $row->parent)
                                        <span class="block font-normal text-[var(--muted)]">{{ __('masters.parent') }}: {{ $row->parent->name }}</span>
                                    @endif
                                </th>
                                <td class="wrap-anywhere p-3">{{ __('masters.'.($row->is_active ? 'active' : 'archived')) }}</td>
                                <td class="p-2 text-end">
                                    <a class="ui-button" aria-label="{{ __('masters.open_named', ['name' => $row->name]) }}"
                                        href="{{ route('masters.edit', [$type, $row]) }}">{{ __('masters.open') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.panel>

        @if($rows->total())
            <nav aria-label="{{ __('masters.pagination') }}" class="flex flex-wrap items-center justify-between gap-2">
                @if($rows->count())
                    <p>{{ __('masters.page_range', ['first' => $rows->firstItem(), 'last' => $rows->lastItem(), 'total' => $rows->total()]) }}</p>
                @endif
                <div class="flex flex-wrap gap-2">
                    @if($rows->previousPageUrl())
                        <a class="ui-button" rel="prev" href="{{ $rows->previousPageUrl() }}">{{ __('masters.previous') }}</a>
                    @endif
                    @if($rows->nextPageUrl())
                        <a class="ui-button" rel="next" href="{{ $rows->nextPageUrl() }}">{{ __('masters.next') }}</a>
                    @endif
                </div>
            </nav>
        @endif
    </section>
</x-layouts.account>
