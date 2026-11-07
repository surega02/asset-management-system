<x-layouts.account :title="__('reports.title')">
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('reports.title') }}">
        @foreach(['units' => 'units', 'groups' => 'groups', 'events' => 'events_tab'] as $tab => $key)
            <a @class(['nav-link', 'nav-link-current' => $tab === $mode]) href="{{ route('reports.show', $tab) }}"
                @if($tab === $mode) aria-current="page" @endif>{{ __('reports.'.$key) }}</a>
        @endforeach
    </nav>
    <p class="max-w-prose">{{ __($mode === 'events' ? 'reports.event_help' : 'reports.current_help') }}</p>
    <x-ui.panel class="grid min-w-0 gap-3 p-4 sm:p-5" aria-labelledby="report-filters">
        <h2 id="report-filters">{{ __('reports.filter_heading') }}</h2>
        <form method="get" action="{{ route('reports.show', $mode) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.field name="q" :label="__('reports.filters.q')" :value="$filters['q'] ?? ''" maxlength="120" />
            <x-ui.select-field name="branch" :label="__('reports.filters.branch')">
                <option value="">{{ __('reports.all') }}</option>
                @foreach($branches as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['branch'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </x-ui.select-field>
            @if($mode === 'events')
                <x-ui.select-field name="kind" :label="__('reports.filters.kind')">
                    <option value="">{{ __('reports.all') }}</option>
                    @foreach(\App\Queries\AssetReports::EVENTS as $key)
                        <option value="{{ $key }}" @selected(($filters['kind'] ?? '') === $key)>{{ __('reports.events.'.$key) }}</option>
                    @endforeach
                </x-ui.select-field>
            @else
                <x-ui.select-field name="state" :label="__('reports.filters.state')">
                    <option value="">{{ __('reports.all') }}</option>
                    @foreach(['active', 'inactive'] as $key)
                        <option value="{{ $key }}" @selected(($filters['state'] ?? '') === $key)>{{ __('assets.'.$key) }}</option>
                    @endforeach
                </x-ui.select-field>
                <x-ui.select-field name="date" :label="__('reports.filters.date')">
                    @foreach(array_keys(\App\Queries\AssetReports::DATES) as $key)
                        <option value="{{ $key }}" @selected(($filters['date'] ?? 'registration') === $key)>{{ __('reports.columns.'.$key) }}</option>
                    @endforeach
                </x-ui.select-field>
            @endif
            <x-ui.field name="from" type="date" :label="__('reports.filters.from')" :value="$filters['from'] ?? ''" />
            <x-ui.field name="to" type="date" :label="__('reports.filters.to')" :value="$filters['to'] ?? ''" />
            @if($mode === 'groups')
                <x-ui.select-field name="group" :label="__('reports.filters.group')" :help="__('reports.group_help')">
                    @foreach(array_keys(\App\Queries\AssetReports::GROUPS) as $key)
                        <option value="{{ $key }}" @selected(($filters['group'] ?? 'branch') === $key)>{{ __('reports.columns.'.$key) }}</option>
                    @endforeach
                </x-ui.select-field>
            @endif
            <div class="flex flex-wrap items-center gap-2 self-end">
                <x-ui.button type="submit" variant="primary">{{ __('masters.apply') }}</x-ui.button>
                <a class="nav-link" href="{{ route('reports.show', $mode) }}">{{ __('reports.reset') }}</a>
            </div>
        </form>
    </x-ui.panel>
    <section class="grid min-w-0 gap-3" aria-labelledby="report-results">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="report-results">{{ __('reports.results') }}</h2>
            <div class="flex flex-wrap gap-2">
                @foreach(['csv', 'xlsx'] as $format)
                    <a class="ui-button" href="{{ route('reports.export', ['mode' => $mode, 'format' => $format] + $filters) }}">{{ __('reports.download', ['format' => strtoupper($format)]) }}</a>
                @endforeach
            </div>
        </div>
        <x-ui.panel class="min-w-0">
            @if($rows->isEmpty())
                <p class="p-4">{{ __('reports.empty') }}</p>
            @else
                <p id="table-scroll-help" class="px-4 py-2 text-sm">{{ __('reports.scroll_help') }}</p>
                <div class="overflow-x-auto focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[var(--focus)]"
                    tabindex="0" role="region" aria-labelledby="report-results" aria-describedby="table-scroll-help">
                    <table class="w-full text-sm">
                        <thead class="bg-[var(--subtle)]">
                            <tr>
                                @foreach($columns as $column)
                                    <th scope="col" class="px-4 py-3 text-start align-top">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border)]">
                            @foreach($rows as $row)
                                <tr>
                                    @foreach($row['cells'] as $cell)
                                        @if($loop->first)
                                            <th scope="row" class="px-4 py-3 text-start align-top">
                                                @if($row['asset'])
                                                    <a class="whitespace-nowrap font-semibold underline underline-offset-4" href="{{ route('assets.show', $row['asset']) }}">{{ $cell }}</a>
                                                @else
                                                    {{ $cell }}
                                                @endif
                                            </th>
                                        @else
                                            <td @class(['px-4 py-3 align-top wrap-anywhere', 'min-w-28' => $mode !== 'groups'])>{{ $cell }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>
        @include('people.pagination', ['pages' => $rows])
    </section>
</x-layouts.account>
