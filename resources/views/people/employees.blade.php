<x-layouts.account :title="__('people.employees')">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p>{{ __('people.employee_list_help') }}</p>
        <div class="flex flex-wrap gap-2">
            @can('import', \App\Models\Employee::class)
                <a class="ui-button" href="{{ route('employees.import.create') }}">{{ __('people.import') }}</a>
            @endcan
            @can('create', \App\Models\Employee::class)
                <a class="ui-button ui-button-primary" href="{{ route('employees.create') }}">{{ __('people.add_employee') }}</a>
            @endcan
        </div>
    </div>
    <form method="get" class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
        <x-ui.field name="q" type="search" :label="__('people.search')" :value="$search" maxlength="255" />
        <x-ui.select-field name="status" :label="__('people.fields.employment_status')">
            @foreach(['active', 'inactive', 'all'] as $filter)
                <option value="{{ $filter }}" @selected($status === $filter)>{{ __('people.'.$filter) }}</option>
            @endforeach
        </x-ui.select-field>
        <x-ui.button type="submit">{{ __('masters.apply') }}</x-ui.button>
    </form>
    <x-ui.panel>
        @if($rows->isEmpty())
            <div class="grid gap-2 p-5">
                <h2>{{ __('people.no_employees') }}</h2>
                <p>{{ __('people.no_employees_help') }}</p>
            </div>
        @else
            <table class="w-full table-fixed text-sm">
                <caption class="sr-only">{{ __('people.employees') }}</caption>
                <thead class="bg-[var(--subtle)] text-start">
                    <tr>
                        <th scope="col" class="p-3 text-start">{{ __('people.fields.name') }}</th>
                        <th scope="col" class="w-24 p-3 text-start sm:w-40">{{ __('people.fields.employment_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $employee)
                        <tr class="border-t border-[var(--border)] align-top">
                            <th scope="row" class="wrap-anywhere p-3 text-start font-normal">
                                <a href="{{ route('employees.show', $employee) }}" class="inline-flex min-h-11 items-center font-semibold underline underline-offset-4">{{ $employee->name }}</a>
                                <span class="block text-[var(--muted)]">{{ $employee->nik }} · {{ $employee->position->name }}</span>
                                <span class="block text-[var(--muted)]">{{ $employee->department->owner->owner->name }} / {{ $employee->department->owner->name }} / {{ $employee->department->name }}</span>
                            </th>
                            <td class="wrap-anywhere p-3">{{ __('people.'.$employee->employment_status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.panel>
    @include('people.pagination', ['pages' => $rows])
</x-layouts.account>
