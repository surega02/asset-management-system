@php
    $oldTransfer = static fn (string $key): string => $errors->transfer->any() && is_scalar(old($key)) ? (string) old($key) : '';
@endphp
<form method="post" action="{{ route('employees.transfer', $employee) }}" class="grid gap-4"
    x-data="{ branch: '' }" x-init="branch = $refs.destination.selectedOptions[0]?.dataset.branch || ''">
    @csrf
    <x-ui.select-field name="department_id" id="transfer-department" bag="transfer" :label="__('people.destination')" required x-ref="destination"
        x-on:change="branch = $el.selectedOptions[0]?.dataset.branch || ''; $refs.location.value = ''">
        <option value="">{{ __('people.choose') }}</option>
        @foreach($departments->where('id', '!=', $employee->department_id) as $department)
            <option value="{{ $department->id }}" data-branch="{{ $department->owner->branch_id }}" @selected($oldTransfer('department_id') === (string) $department->id)>
                {{ $department->owner->owner->name }} / {{ $department->owner->name }} / {{ $department->name }}
            </option>
        @endforeach
    </x-ui.select-field>
    <x-ui.select-field name="home_location_id" id="transfer-location" bag="transfer" :label="__('people.fields.home_location_id')" :help="__('people.location_help')" required x-ref="location">
        <option value="">{{ __('people.choose') }}</option>
        @foreach($locations as $location)
            <option value="{{ $location->id }}" data-branch="{{ $location->branch_id }}" x-bind:hidden="$el.dataset.branch !== branch"
                x-bind:disabled="$el.dataset.branch !== branch" @selected($oldTransfer('home_location_id') === (string) $location->id)>
                {{ $location->name }} ({{ $location->code }})
            </option>
        @endforeach
    </x-ui.select-field>
    <div class="grid gap-1.5">
        <label for="transfer-reason" class="font-semibold">{{ __('people.fields.reason') }}</label>
        <textarea name="reason" id="transfer-reason" class="ui-select w-full py-2" rows="2" maxlength="100" required
            @error('reason', 'transfer') aria-invalid="true" aria-describedby="transfer-reason-error" @enderror>{{ $oldTransfer('reason') }}</textarea>
        @error('reason', 'transfer')<p id="transfer-reason-error" class="text-sm text-[var(--error)]">{{ $message }}</p>@enderror
    </div>
    <x-ui.button type="submit" class="justify-self-start">{{ __('people.transfer_submit') }}</x-ui.button>
</form>
