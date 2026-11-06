<x-layouts.account :title="__('people.import')">
    <a class="nav-link justify-self-start" href="{{ route('employees.index') }}">{{ __('people.back_employees') }}</a>
    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,.8fr)]">
        <x-ui.panel class="grid gap-4 p-4 sm:p-6">
            <p>{{ __('people.import_help') }}</p>
            <p>{{ __('people.import_format') }}</p>
            <a class="ui-button justify-self-start" href="{{ route('employees.import.template') }}">{{ __('people.template') }}</a>
            <form method="post" action="{{ route('employees.import.store') }}" enctype="multipart/form-data" class="grid gap-4">
                @csrf
                <x-ui.field name="file" type="file" :label="__('people.file')" accept=".csv,.xlsx" class="py-2" required />
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('people.upload') }}</x-ui.button>
            </form>
        </x-ui.panel>
        <x-ui.panel class="grid gap-3 p-4 sm:p-6">
            <h2>{{ __('people.lookup_ids') }}</h2>
            <p>{{ __('people.lookup_help') }}</p>
            @foreach(['department_id' => $departments, 'position_id' => $positions, 'home_location_id' => $locations] as $key => $options)
                <details class="border-b border-[var(--border)] pb-2">
                    <summary class="min-h-11 cursor-pointer py-2 font-semibold">{{ __('people.fields.'.$key) }}</summary>
                    <ul class="grid gap-2 ps-4">
                        @forelse($options as $option)
                            <li class="wrap-anywhere">{{ $option->id }} · {{ $option->name }} ({{ $option->code }})@if($key === 'department_id') · {{ $option->owner->owner->name }} / {{ $option->owner->name }}@elseif($key === 'home_location_id') · {{ $option->owner->name }}@endif</li>
                        @empty
                            <li>{{ __('people.no_units') }}</li>
                        @endforelse
                    </ul>
                </details>
            @endforeach
        </x-ui.panel>
    </div>
    <section aria-labelledby="batches-heading" class="grid gap-3">
        <h2 id="batches-heading">{{ __('people.recent_batches') }}</h2>
        @forelse($batches as $batch)
            <a class="nav-link justify-self-start" href="{{ route('employees.import.show', $batch->id) }}">{{ __('people.batch', ['id' => $batch->id]) }} · {{ $batch->created_at }}</a>
        @empty
            <p>{{ __('people.no_batches') }}</p>
        @endforelse
        @include('people.pagination', ['pages' => $batches])
    </section>
</x-layouts.account>
