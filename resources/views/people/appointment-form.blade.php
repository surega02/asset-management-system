@php
    $value = static fn (string $key): string => is_scalar(old($key)) ? (string) old($key) : '';
@endphp
<x-layouts.account :title="__('people.add_appointment')">
    <a class="nav-link justify-self-start" href="{{ route('appointments.index') }}">{{ __('people.back_appointments') }}</a>
    <x-ui.panel class="max-w-2xl p-4 sm:p-6">
        @if($employees->isEmpty())
            <p>{{ __('people.no_candidates') }}</p>
        @else
            <form method="post" action="{{ route('appointments.store') }}" class="grid gap-4" x-data="{ role: @js($value('role_kind')) }">
                @csrf
                <x-ui.select-field name="employee_id" :label="__('people.fields.employee_id')" required>
                    <option value="">{{ __('people.choose') }}</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected($value('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </x-ui.select-field>
                <x-ui.select-field name="role_kind" :label="__('people.fields.role_kind')" required x-model="role" x-on:change="$refs.unit.value = ''">
                    <option value="">{{ __('people.choose') }}</option>
                    @foreach(['hod', 'director'] as $role)
                        <option value="{{ $role }}" @selected($value('role_kind') === $role)>{{ __('people.'.$role) }}</option>
                    @endforeach
                </x-ui.select-field>
                <x-ui.select-field name="scope_key" :label="__('people.fields.scope_type')" :help="__('people.scope_help')" required x-ref="unit">
                    <option value="">{{ __('people.choose') }}</option>
                    @foreach($units as $type => $options)
                        @php($requiredRole = $type === 'department' ? 'hod' : 'director')
                        <optgroup label="{{ __('people.'.$type) }}" data-role="{{ $requiredRole }}" x-bind:hidden="role !== $el.dataset.role" x-bind:disabled="role !== $el.dataset.role">
                            @foreach($options as $unit)
                                @php($key = $type.':'.$unit->id)
                                <option value="{{ $key }}" @selected($value('scope_key') === $key)>{{ $unit->name }} ({{ $unit->code }})</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                    @can('create', [\App\Models\StructuralAppointment::class, 'company'])
                        <option value="company" x-bind:hidden="role !== 'director'" x-bind:disabled="role !== 'director'" @selected($value('scope_key') === 'company')>{{ __('people.company') }}</option>
                    @endcan
                </x-ui.select-field>
                <x-ui.select-field name="appointment_kind" :label="__('people.fields.appointment_kind')" required>
                    <option value="">{{ __('people.choose') }}</option>
                    @foreach(['definitive', 'acting'] as $kind)
                        <option value="{{ $kind }}" @selected($value('appointment_kind') === $kind)>{{ __('people.'.$kind) }}</option>
                    @endforeach
                </x-ui.select-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="starts_on" type="date" :label="__('people.fields.starts_on')" :value="$value('starts_on')" required />
                    <x-ui.field name="ends_on" type="date" :label="__('people.fields.ends_on')" :value="$value('ends_on')" />
                </div>
                <p class="text-sm">{{ __('people.end_help') }}</p>
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('masters.save') }}</x-ui.button>
            </form>
        @endif
    </x-ui.panel>
</x-layouts.account>
