<x-layouts.account :title="$employee->name">
    <a class="nav-link justify-self-start" href="{{ route('employees.index') }}">{{ __('people.back_employees') }}</a>
    <dl class="grid gap-x-6 gap-y-3 border-y border-[var(--border)] py-4 sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="font-semibold">{{ __('people.fields.nik') }}</dt><dd class="wrap-anywhere">{{ $employee->nik }}</dd></div>
        <div><dt class="font-semibold">{{ __('people.organization') }}</dt><dd class="wrap-anywhere">{{ $employee->department->owner->owner->name }} / {{ $employee->department->owner->name }} / {{ $employee->department->name }}</dd></div>
        <div><dt class="font-semibold">{{ __('people.fields.position_id') }}</dt><dd class="wrap-anywhere">{{ $employee->position->name }}</dd></div>
        <div><dt class="font-semibold">{{ __('people.fields.home_location_id') }}</dt><dd class="wrap-anywhere">{{ $employee->homeLocation->name }}</dd></div>
        <div><dt class="font-semibold">{{ __('people.fields.employment_status') }}</dt><dd>{{ __('people.'.$employee->employment_status) }}</dd></div>
        <div><dt class="font-semibold">{{ __('people.tenure') }}</dt><dd>{{ __('people.tenure_months', ['months' => max(0, (int) $employee->joined_at->diffInMonths(today()))]) }} · {{ $employee->joined_at->toDateString() }}</dd></div>
    </dl>
    @if($canWrite)
        @if($warning['count'])
            <div class="action-note !mt-0 !block" role="status">
                <p>{{ __('people.assets_warning', ['count' => $warning['count']]) }}</p>
                @if($warning['assets']->isNotEmpty())
                    <ul class="mt-2 list-disc ps-5">
                        @foreach($warning['assets'] as $asset)
                            <li class="wrap-anywhere">{{ $asset->asset_code }} · {{ $asset->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
        <div class="grid items-start gap-4 lg:grid-cols-2">
            <x-ui.panel class="grid gap-4 p-4 sm:p-6">
                <h2>{{ __('people.edit_profile') }}</h2>
                @include('people.profile-fields')
            </x-ui.panel>
            <x-ui.panel class="grid gap-3 p-4 sm:p-6">
                <h2>{{ __('people.transfer') }}</h2>
                <p>{{ __('people.transfer_help') }}</p>
                @if($departments->where('id', '!=', $employee->department_id)->isEmpty())
                    <p>{{ __('people.no_destinations') }}</p>
                @else
                    @include('people.transfer-fields')
                @endif
            </x-ui.panel>
        </div>
    @endif
    <section aria-labelledby="movements-heading" class="grid gap-3">
        <h2 id="movements-heading">{{ __('people.movements') }}</h2>
        @forelse($movements as $movement)
            <article class="grid gap-1 border-b border-[var(--border)] pb-3">
                <p class="font-semibold">{{ $movement->changed_at }} · {{ $movement->actor_name }}</p>
                <p class="wrap-anywhere">{{ __('people.from') }}: {{ $movement->from_branch }} / {{ $movement->from_division }} / {{ $movement->from_department }}</p>
                <p class="wrap-anywhere">{{ __('people.to') }}: {{ $movement->to_branch }} / {{ $movement->to_division }} / {{ $movement->to_department }}</p>
                <p class="wrap-anywhere">{{ __('people.fields.reason') }}: {{ $movement->reason }}</p>
            </article>
        @empty
            <p>{{ __('people.no_movements') }}</p>
        @endforelse
        @include('people.pagination', ['pages' => $movements])
    </section>
</x-layouts.account>
