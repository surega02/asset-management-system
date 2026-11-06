@php($canWrite = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\StructuralAppointment::class))
<x-layouts.account :title="__('people.appointments')">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-prose">{{ __('people.appointment_help') }}</p>
        @if($canWrite)
            <a class="ui-button ui-button-primary" href="{{ route('appointments.create') }}">{{ __('people.add_appointment') }}</a>
        @endif
    </div>
    <form method="get" class="flex flex-wrap items-end gap-3">
        <x-ui.field name="date" type="date" :label="__('people.reference_date')" :value="$date" required />
        <x-ui.button type="submit">{{ __('masters.apply') }}</x-ui.button>
    </form>
    <x-ui.panel>
        @if($rows->isEmpty())
            <div class="grid gap-2 p-5">
                <h2>{{ __('people.no_appointments') }}</h2>
                <p>{{ __('people.no_appointments_help') }}</p>
            </div>
        @else
            <table class="w-full table-fixed text-sm">
                <caption class="sr-only">{{ __('people.appointments') }}</caption>
                <thead class="bg-[var(--subtle)]">
                    <tr>
                        <th scope="col" class="p-3 text-start">{{ __('people.fields.employee_id') }}</th>
                        <th scope="col" class="w-[44%] p-3 text-start sm:w-[38%]">{{ __('people.period') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-t border-[var(--border)] align-top">
                            <th scope="row" class="wrap-anywhere p-3 text-start font-normal">
                                <span class="block font-semibold">{{ $row->employee->name }}</span>
                                <span class="block">{{ __('people.'.$row->role_kind) }} · {{ __('people.'.$row->appointment_kind) }}</span>
                                <span class="block text-[var(--muted)]">{{ __('people.'.$row->scope_type) }}@if($row->scope_type !== 'company'): {{ $row->{$row->scope_type}->name }}@endif</span>
                            </th>
                            <td class="wrap-anywhere p-3">
                                <time datetime="{{ $row->starts_on }}">{{ $row->starts_on }}</time>
                                <span aria-hidden="true">—</span>
                                <span class="sr-only">{{ __('people.to') }}</span>
                                @if($row->ends_on)<time datetime="{{ $row->ends_on }}">{{ $row->ends_on }}</time>@else{{ __('people.open_end') }}@endif
                                @if((int) $row->effective_id === $row->id)
                                    <strong class="block">{{ __('people.effective') }}</strong>
                                @endif
                                @if($canWrite && ($row->scope_type !== 'company' || \Illuminate\Support\Facades\Gate::allows('create', [\App\Models\StructuralAppointment::class, 'company'])))
                                    <a class="mt-1 inline-flex min-h-11 items-center underline underline-offset-4" href="{{ route('appointments.edit', $row) }}"
                                        aria-label="{{ __('people.edit_period_for', ['name' => $row->employee->name]) }}">{{ __('people.edit_period') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.panel>
    @include('people.pagination', ['pages' => $rows])
</x-layouts.account>
