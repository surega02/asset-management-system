<x-layouts.account :title="__('people.edit_period')">
    <a class="nav-link justify-self-start" href="{{ route('appointments.index') }}">{{ __('people.back_appointments') }}</a>
    <x-ui.panel class="grid max-w-2xl gap-4 p-4 sm:p-6">
        <h2 class="wrap-anywhere">{{ $appointment->employee->name }}</h2>
        <p>{{ __('people.'.$appointment->role_kind) }} · {{ __('people.'.$appointment->appointment_kind) }} · {{ __('people.fields.starts_on') }}: {{ $appointment->starts_on }}</p>
        <form method="post" action="{{ route('appointments.update', $appointment) }}" class="grid gap-4">
            @csrf
            @method('PUT')
            <x-ui.field name="ends_on" type="date" :label="__('people.fields.ends_on')" :value="is_scalar(old('ends_on', $appointment->ends_on)) ? (string) old('ends_on', $appointment->ends_on) : ''" :min="$appointment->starts_on" required />
            <x-ui.field name="reason" :label="__('people.fields.reason')" :value="is_scalar(old('reason')) ? (string) old('reason') : ''" maxlength="100" required />
            <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('masters.save') }}</x-ui.button>
        </form>
    </x-ui.panel>
</x-layouts.account>
