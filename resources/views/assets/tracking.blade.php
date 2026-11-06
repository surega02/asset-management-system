<x-layouts.account :title="__('tracking.title')">
    <div class="flex flex-wrap items-center gap-3">
        <a class="nav-link" href="{{ route('assets.show', $asset) }}">{{ __('tracking.back_asset') }}</a>
        <strong class="wrap-anywhere">{{ $asset->asset_code }} · {{ $asset->name }}</strong>
    </div>
    <p>{{ __('tracking.independent') }}</p>
    @if(!$asset->is_active)
        <p class="action-note !mt-0">{{ __('tracking.inactive') }}</p>
    @endif
    <div class="grid items-start gap-5 lg:grid-cols-2">
        <section aria-labelledby="assignment-heading" class="grid min-w-0 gap-4">
            <x-ui.panel class="grid gap-3 p-4 sm:p-5">
                <h2 id="assignment-heading">{{ __('tracking.assignment') }}</h2>
                <p class="wrap-anywhere">{{ __('tracking.current') }}: <strong>{{ $asset->activeAssignment?->assigneeName() ?? __('assets.none') }}</strong></p>
                @if($canReassign && $asset->activeAssignment)
                    @include('assets.tracking-assignment-form')
                @endif
            </x-ui.panel>
            <section class="grid gap-3" aria-labelledby="assignment-history-heading">
                <h3 id="assignment-history-heading" class="font-semibold">{{ __('tracking.assignment_history') }}</h3>
                <ol class="grid gap-2">
                    @forelse($assignments as $assignment)
                        <li class="grid gap-1 border-b border-[var(--border)] pb-3">
                            <strong class="wrap-anywhere">{{ $assignment->assigneeName() }}</strong>
                            <p class="text-[var(--muted)]">
                                {{ $assignment->started_at->format('Y-m-d H:i:s') }} ·
                                {{ $assignment->ended_at?->format('Y-m-d H:i:s') ?? __('tracking.ongoing') }}
                            </p>
                            <p class="wrap-anywhere">{{ $assignment->actor->employee->name }} · {{ $assignment->reason === 'asset-registration' ? __('tracking.registration') : $assignment->reason }}</p>
                        </li>
                    @empty
                        <li>{{ __('tracking.no_history') }}</li>
                    @endforelse
                </ol>
                @include('people.pagination', ['pages' => $assignments])
            </section>
        </section>
        <section aria-labelledby="location-heading" class="grid min-w-0 gap-4">
            <x-ui.panel class="grid gap-3 p-4 sm:p-5">
                <h2 id="location-heading">{{ __('assets.fields.physical_location_id') }}</h2>
                <p class="wrap-anywhere">{{ __('tracking.current') }}: <strong>{{ $asset->location->owner->name }} / {{ $asset->location->name }}</strong></p>
                @if($canMove && $locationVersion)
                    @if($destinations->isEmpty())
                        <p>{{ __('tracking.no_destinations') }}</p>
                    @else
                        @include('assets.tracking-location-form')
                    @endif
                @endif
            </x-ui.panel>
            <section class="grid gap-3" aria-labelledby="location-history-heading">
                <h3 id="location-history-heading" class="font-semibold">{{ __('tracking.location_history') }}</h3>
                <ol class="grid gap-2">
                    @forelse($moves as $move)
                        <li class="grid gap-1 border-b border-[var(--border)] pb-3">
                            @if($move->origin)
                                <p class="wrap-anywhere">{{ __('people.from') }}: {{ $move->origin_branch }} / {{ $move->origin }}</p>
                            @endif
                            <p class="wrap-anywhere">{{ __('people.to') }}: <strong>{{ $move->destination_branch }} / {{ $move->destination }}</strong></p>
                            <p class="text-[var(--muted)]"><time datetime="{{ str_replace(' ', 'T', $move->changed_at) }}">{{ substr($move->changed_at, 0, 19) }}</time> · {{ $move->actor_name }}</p>
                            <p class="wrap-anywhere">{{ $move->reason === 'asset-registration' ? __('tracking.registration') : $move->reason }}</p>
                        </li>
                    @empty
                        <li>{{ __('tracking.no_history') }}</li>
                    @endforelse
                </ol>
                @include('people.pagination', ['pages' => $moves])
            </section>
        </section>
    </div>
</x-layouts.account>
