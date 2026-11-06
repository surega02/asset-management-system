@php
    $value = static function (string $key, mixed $default = '') use ($errors): string {
        $input = $errors->transfer->any() ? $default : old($key, $default);
        return is_scalar($input) ? (string) $input : '';
    };
@endphp
<form method="post" action="{{ $employee ? route('employees.update', $employee) : route('employees.store') }}"
    class="grid gap-4"
    x-data="{ branch: @js((string) ($employee?->department?->owner?->branch_id ?? '')) }"
    @if(!$employee) x-init="branch = $refs.department.selectedOptions[0]?.dataset.branch || ''" @endif>
    @csrf
    @if($employee)
        @method('PUT')
    @else
        <x-ui.field name="nik" :label="__('people.fields.nik')" :value="$value('nik')" required maxlength="64" />
    @endif
    <x-ui.field name="name" :label="__('people.fields.name')" :value="$value('name', $employee?->name)" required maxlength="255" />
    @if(!$employee)
        <x-ui.select-field name="department_id" :label="__('people.fields.department_id')" required x-ref="department"
            x-on:change="branch = $el.selectedOptions[0]?.dataset.branch || ''; $refs.location.value = ''">
            <option value="">{{ __('people.choose') }}</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" data-branch="{{ $department->owner->branch_id }}" @selected($value('department_id') === (string) $department->id)>
                    {{ $department->owner->owner->name }} / {{ $department->owner->name }} / {{ $department->name }}
                </option>
            @endforeach
        </x-ui.select-field>
    @endif
    <x-ui.select-field name="position_id" :label="__('people.fields.position_id')" required>
        <option value="">{{ __('people.choose') }}</option>
        @foreach($positions as $position)
            <option value="{{ $position->id }}" @selected($value('position_id', $employee?->position_id) === (string) $position->id)>{{ $position->name }} ({{ $position->code }})</option>
        @endforeach
    </x-ui.select-field>
    <x-ui.select-field name="home_location_id" :label="__('people.fields.home_location_id')" :help="__('people.location_help')" required x-ref="location">
        <option value="">{{ __('people.choose') }}</option>
        @if($employee && !$locations->contains('id', $employee->home_location_id))
            <option value="{{ $employee->home_location_id }}" selected>{{ $employee->homeLocation->name }}</option>
        @endif
        @foreach($locations as $location)
            <option value="{{ $location->id }}" data-branch="{{ $location->branch_id }}" x-bind:hidden="$el.dataset.branch !== branch"
                x-bind:disabled="$el.dataset.branch !== branch" @selected($value('home_location_id', $employee?->home_location_id) === (string) $location->id)>
                {{ $location->name }} ({{ $location->code }})
            </option>
        @endforeach
    </x-ui.select-field>
    <x-ui.field name="joined_at" type="date" :label="__('people.fields.joined_at')" :value="$value('joined_at', $employee?->joined_at?->toDateString())" required />
    <x-ui.select-field name="employment_status" :label="__('people.fields.employment_status')" :help="$employee ? __('people.inactive_help') : null" required>
        @foreach(['active', 'inactive'] as $state)
            <option value="{{ $state }}" @selected($value('employment_status', $employee?->employment_status ?? 'active') === $state)>{{ __('people.'.$state) }}</option>
        @endforeach
    </x-ui.select-field>
    <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('masters.save') }}</x-ui.button>
</form>
