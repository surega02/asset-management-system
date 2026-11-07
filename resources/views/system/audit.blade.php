<x-layouts.account :title="__('system.audit')">
    <p>{{ __('system.audit_help') }}</p>
    <x-ui.panel class="p-4 sm:p-5">
        <form method="get" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.select-field name="actor" :label="__('system.audit_fields.actor')">
                <option value="">{{ __('reports.all') }}</option>
                @foreach($actors as $id => $name)
                    <option value="{{ $id }}" @selected((string) ($filters['actor'] ?? '') === (string) $id)>{{ $name }} · #{{ $id }}</option>
                @endforeach
            </x-ui.select-field>
            <x-ui.select-field name="event" :label="__('system.audit_fields.event')">
                <option value="">{{ __('reports.all') }}</option>
                @foreach($events as $event)
                    <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
                @endforeach
            </x-ui.select-field>
            <x-ui.field name="asset" :label="__('system.audit_fields.asset')" :value="$filters['asset'] ?? ''" placeholder="AST-000001" />
            <x-ui.field name="from" type="date" :label="__('system.audit_fields.from')" :value="$filters['from'] ?? ''" />
            <x-ui.field name="to" type="date" :label="__('system.audit_fields.to')" :value="$filters['to'] ?? ''" />
            <div class="flex flex-wrap items-end gap-2">
                <x-ui.button type="submit" variant="primary">{{ __('masters.apply') }}</x-ui.button>
                <a class="nav-link" href="{{ route('system.audit') }}">{{ __('reports.reset') }}</a>
            </div>
        </form>
    </x-ui.panel>
    <x-ui.panel>
        <ul class="divide-y divide-[var(--border)]">
            @forelse($rows as $row)
                <li class="grid gap-2 p-4 sm:p-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="wrap-anywhere">{{ $row->event }}</h2><time>{{ $row->created_at }}</time>
                    </div>
                    <p>{{ $row->actor_name ?? __('system.system_actor') }} @if($row->asset_code) · {{ $row->asset_code }} @endif</p>
                    <p class="wrap-anywhere">{{ $row->reason === '[redacted]' ? __('system.redacted') : $row->reason }}</p>
                    @if($row->old_values || $row->new_values)
                        <details>
                            <summary class="cursor-pointer font-semibold">{{ __('system.details') }}</summary>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                @foreach(['old_values' => 'previous_value', 'new_values' => 'next_value'] as $field => $label)
                                    <div class="min-w-0 rounded-md bg-[var(--subtle)] p-3">
                                        <h3 class="mb-1 font-semibold">{{ __('reports.columns.'.$label) }}</h3>
                                        @if($row->{$field})
                                            <dl class="grid gap-1 text-sm">
                                                @foreach($row->{$field} as $key => $value)
                                                    <div class="wrap-anywhere"><dt class="inline font-semibold">{{ $key }}</dt>
                                                        <dd class="inline">{{ $value === '[redacted]' ? __('system.redacted') : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd></div>
                                                @endforeach
                                            </dl>
                                        @else
                                            <p>{{ __('reports.none') }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </li>
            @empty
                <li class="p-4">{{ __('reports.empty') }}</li>
            @endforelse
        </ul>
    </x-ui.panel>
    @include('people.pagination', ['pages' => $rows])
</x-layouts.account>
