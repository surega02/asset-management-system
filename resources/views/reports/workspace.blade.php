<x-layouts.account :title="__('iam.workspace')">
    <x-ui.panel class="action-panel" aria-labelledby="workspace-actions">
        <div class="action-layout">
            <div class="min-w-0">
                <h2 id="workspace-actions">{{ __('dashboard.quick') }}</h2>
                <p>{{ __('dashboard.quick_note') }}</p>
                <form class="lookup-form" action="{{ route('assets.find') }}" method="get">
                    <label for="workspace-asset-code">{{ __('dashboard.code') }}</label>
                    <div class="lookup-fields">
                        <div class="lookup-input">
                            <x-ui.icon name="search" class="size-4" />
                            <input id="workspace-asset-code" name="asset_code" placeholder="AST-000001"
                                autocomplete="off" autocapitalize="characters" maxlength="23" required>
                        </div>
                        <x-ui.button type="submit">{{ __('dashboard.search') }}</x-ui.button>
                    </div>
                </form>
            </div>
            <div class="quick-buttons">
                @can('create', \App\Models\Asset::class)
                    <a class="ui-button ui-button-primary" href="{{ route('assets.create') }}"><x-ui.icon name="plus" class="size-4" />{{ __('assets.register') }}</a>
                @endcan
                <a class="ui-button" href="{{ route('assets.scan') }}"><x-ui.icon name="scan" class="size-4" />{{ __('qr.scan_title') }}</a>
            </div>
        </div>
    </x-ui.panel>

    @if($branches->isNotEmpty())
        <form method="get" class="flex flex-wrap items-end gap-3">
            <x-ui.select-field name="branch" :label="__('reports.filters.branch')">
                <option value="">{{ __('reports.all') }}</option>
                @foreach($branches as $id => $name)
                    <option value="{{ $id }}" @selected((int) ($filters['branch'] ?? 0) === $id)>{{ $name }}</option>
                @endforeach
            </x-ui.select-field>
            <x-ui.button type="submit">{{ __('masters.apply') }}</x-ui.button>
        </form>
        <section class="branch-band" aria-labelledby="operational-summary">
            <div class="branch-intro">
                <div>
                    <h2 id="operational-summary">{{ __('reports.operational') }}
                        @if(isset($filters['branch'], $branches[$filters['branch']])) <span aria-hidden="true">/</span> {{ $branches[$filters['branch']] }} @endif
                    </h2>
                    <p>{{ __('dashboard.scope_note') }}</p>
                </div>
                <div class="branch-mark" aria-hidden="true"><x-ui.icon name="pin" class="size-5" /></div>
            </div>
            <dl class="branch-metrics">
                @foreach(['total', 'active', 'inactive'] as $metric)
                    <div><dt>{{ __('reports.columns.'.$metric) }}</dt><dd>{{ $totals->{$metric} }}</dd></div>
                @endforeach
            </dl>
        </section>
        <div class="detail-grid">
            <x-ui.panel aria-labelledby="report-locations">
                <div class="section-heading">
                    <h2 id="report-locations">{{ __('dashboard.locations') }}</h2>
                    <p>{{ __('reports.top_locations') }}</p>
                </div>
                <ul class="location-list">
                    @forelse($locations as $location)
                        <li>
                            <div><span class="wrap-anywhere">{{ $location->name }}</span><span class="location-count">{{ $location->total }} <span>{{ __('dashboard.unit_suffix') }}</span></span></div>
                            <div class="location-track" aria-hidden="true"><div style="--portion: {{ $totals->total ? $location->total / $totals->total * 100 : 0 }}%"></div></div>
                        </li>
                    @empty
                        <li>{{ __('reports.empty') }}</li>
                    @endforelse
                </ul>
                <div class="px-4 pb-4 sm:px-5"><a class="ui-button" href="{{ route('reports.show', ['mode' => 'units'] + $filters) }}">{{ __('reports.units') }}</a></div>
            </x-ui.panel>
            <x-ui.panel aria-labelledby="report-activity">
                <div class="section-heading">
                    <h2 id="report-activity">{{ __('dashboard.activity') }}</h2>
                    <p>{{ __('reports.recent_help') }}</p>
                </div>
                <ul class="divide-y divide-[var(--border)]">
                    @forelse($recent as $event)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-3 sm:px-5">
                            <a class="wrap-anywhere font-semibold underline underline-offset-4" href="{{ route('assets.show', $event->id) }}">{{ $event->name }} <span class="asset-code">{{ $event->code }}</span></a>
                            <span>{{ __('reports.events.'.$event->kind) }} · <time>{{ $event->occurred }}</time></span>
                        </li>
                    @empty
                        <li class="px-4 pb-3 sm:px-5">{{ __('reports.empty') }}</li>
                    @endforelse
                </ul>
                <div class="p-4 sm:px-5"><a class="ui-button" href="{{ route('reports.show', ['mode' => 'events'] + $filters) }}">{{ __('reports.events_tab') }}</a></div>
            </x-ui.panel>
        </div>
    @endif

    <x-ui.panel aria-labelledby="personal-assets">
        <div class="section-heading">
            <h2 id="personal-assets">{{ __('reports.personal') }} <span class="tabular-nums">({{ $personal->total() }})</span></h2>
            <p>{{ __('reports.personal_help') }}</p>
        </div>
        <ul class="divide-y divide-[var(--border)]">
            @forelse($personal as $asset)
                <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-3 sm:px-5">
                    <a class="wrap-anywhere font-semibold underline underline-offset-4" href="{{ route('assets.show', $asset) }}">{{ $asset->name }} <span class="asset-code">{{ $asset->asset_code }}</span></a>
                    <span>{{ $asset->location->owner->name }} · {{ $asset->location->name }}</span>
                </li>
            @empty
                <li class="px-4 pb-4 sm:px-5">{{ __('reports.empty_personal') }}</li>
            @endforelse
        </ul>
        @if($personal->hasPages())
            <div class="p-4 sm:px-5">@include('people.pagination', ['pages' => $personal])</div>
        @endif
    </x-ui.panel>
</x-layouts.account>
