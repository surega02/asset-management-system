<div class="dashboard">
    <div class="dashboard-heading">
        <div>
            <h1>{{ __('dashboard.dashboard') }}</h1>
            <p class="preview-note"><span class="preview-tag">{{ __('dashboard.preview') }}</span> {{ __('dashboard.preview_note') }}</p>
        </div>
        <div class="branch-control">
            <label for="branch"><x-ui.icon name="pin" class="size-4" />{{ __('dashboard.branch') }}</label>
            <select id="branch" class="ui-select" wire:model.live="branch" wire:loading.attr="disabled" wire:target="branch">
                @foreach(array_combine($branchKeys, $branchNames) as $key => $name)
                    <option value="{{ $key }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-ui.panel class="action-panel" aria-labelledby="quick-title"
        x-data="{ panel: null }"
        @keydown.escape="if (panel) { $refs[panel + 'Button'].focus(); panel = null }">
        <div class="action-layout">
            <div class="min-w-0">
                <h2 id="quick-title">{{ __('dashboard.quick') }}</h2>
                <p>{{ __('dashboard.quick_note') }}</p>
                <form class="lookup-form" wire:submit="lookup">
                    <label for="asset-code">{{ __('dashboard.code') }}</label>
                    <div class="lookup-fields">
                        <div class="lookup-input">
                            <x-ui.icon name="search" class="size-4" />
                            <input id="asset-code" x-ref="assetCode" type="text" wire:model="assetCode" placeholder="AST-000128"
                                maxlength="10" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                @error('assetCode') aria-invalid="true" aria-describedby="lookup-error" @enderror>
                        </div>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="lookup,branch">
                            <span wire:loading.remove wire:target="lookup">{{ __('dashboard.search') }}</span>
                            <span wire:loading wire:target="lookup" role="status">{{ __('dashboard.searching') }}</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
            <div class="quick-buttons">
                <x-ui.button variant="primary" x-ref="registerButton" aria-controls="registration-preview"
                    x-bind:aria-expanded="panel === 'register'" @click="panel = panel === 'register' ? null : 'register'">
                    <x-ui.icon name="plus" class="size-4" />{{ __('dashboard.register') }}
                </x-ui.button>
                <x-ui.button x-ref="scanButton" aria-controls="scan-preview"
                    x-bind:aria-expanded="panel === 'scan'" @click="panel = panel === 'scan' ? null : 'scan'">
                    <x-ui.icon name="scan" class="size-4" />{{ __('dashboard.scan') }}
                </x-ui.button>
            </div>
        </div>

        <div aria-live="polite">
            @error('assetCode')
                <p class="lookup-error" id="lookup-error">{{ $message }}</p>
            @enderror
            @if($match)
                <div class="lookup-result">
                    <strong>{{ __('dashboard.result') }}</strong>
                    <span class="asset-code">{{ $match[0] }}</span>
                    <span>{{ $match[1] }} · {{ $locations[$match[2]] }}</span>
                    <span class="status-tag">{{ __($match[3] ? 'dashboard.active_state' : 'dashboard.inactive_state') }}</span>
                </div>
            @endif
        </div>

        <div id="registration-preview" class="action-note" x-cloak x-show="panel === 'register'">
            <p>{{ __('dashboard.register_note') }}</p>
        </div>
        <div id="scan-preview" class="action-note" x-cloak x-show="panel === 'scan'">
            <p>{{ __('dashboard.scan_note') }}</p>
            <x-ui.button @click="$refs.assetCode.focus(); panel = null">{{ __('dashboard.manual') }}</x-ui.button>
        </div>
    </x-ui.panel>

    <div role="status" class="sr-only" wire:loading wire:target="branch">{{ __('dashboard.scope_loading') }}</div>
    <section class="branch-band" aria-labelledby="branch-title" wire:key="branch-{{ $branch }}">
        <div class="branch-intro">
            <div>
                <h2 id="branch-title">{{ __('dashboard.summary') }} <span aria-hidden="true">/</span> {{ $branchName }}</h2>
                <p>{{ __('dashboard.scope_note') }}</p>
            </div>
            <div class="branch-mark" aria-hidden="true"><x-ui.icon name="pin" class="size-5" /></div>
        </div>
        <dl class="branch-metrics">
            @foreach(['units' => $total, 'active' => $active, 'inactive' => $inactive] as $metric => $value)
                <div>
                    <dt>{{ __('dashboard.'.$metric) }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <div class="detail-grid">
        <x-ui.panel class="detail-panel" id="locations" aria-labelledby="locations-title">
            <div class="section-heading">
                <h2 id="locations-title">{{ __('dashboard.locations') }}</h2>
                <p>{{ __('dashboard.location_note') }}</p>
            </div>
            <ul class="location-list">
                @foreach($locations as $key => $location)
                    @php($count = $locationTotals->get($key, 0))
                    <li>
                        <div><span>{{ $location }}</span><span class="location-count">{{ $count }} <span>{{ __('dashboard.unit_suffix') }}</span></span></div>
                        <div class="location-track" aria-hidden="true"><div style="--portion: {{ $total ? $count / $total * 100 : 0 }}%"></div></div>
                    </li>
                @endforeach
            </ul>
        </x-ui.panel>

        <x-ui.panel id="activity" aria-labelledby="activity-title">
            <div class="section-heading">
                <h2 id="activity-title">{{ __('dashboard.activity') }}</h2>
                <p>{{ __('dashboard.activity_note') }}</p>
            </div>
            <table class="activity-table" aria-labelledby="activity-title">
                <thead>
                    <tr>
                        <th scope="col">{{ __('dashboard.asset') }}</th>
                        <th scope="col">{{ __('dashboard.event') }}</th>
                        <th scope="col">{{ __('dashboard.time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activity as $asset)
                        <tr wire:key="activity-{{ $asset[0] }}">
                            <th scope="row"><span>{{ $asset[1] }}</span><span class="asset-code">{{ $asset[0] }}</span></th>
                            <td>
                                <span class="event-tag" data-event="{{ $asset[4] }}">{{ __('dashboard.'.$asset[4]) }}</span>
                                @if(isset($asset[6]))
                                    <span class="event-location">{{ $locations[$asset[6]] }} <x-ui.icon name="arrow" class="size-3" /> {{ $locations[$asset[2]] }}</span>
                                @endif
                            </td>
                            <td class="activity-time"><time>{{ $asset[5] }}</time></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.panel>
    </div>
</div>
