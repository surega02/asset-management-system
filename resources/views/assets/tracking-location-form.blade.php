@php
    $value = static fn (string $key): string => $errors->locationMove->any() && is_scalar(old($key)) ? (string) old($key) : '';
@endphp
<details @if($errors->locationMove->any()) open @endif>
    <summary class="min-h-11 cursor-pointer py-2 font-semibold">{{ __('tracking.move') }}</summary>
    <form method="post" action="{{ route('assets.move', $asset) }}" class="mt-2 grid gap-4">
        @csrf
        <input type="hidden" name="location_history_id" value="{{ $locationVersion }}">
        @include('assets.tracking-errors', ['formErrors' => $errors->locationMove])
        <x-ui.select-field name="physical_location_id" bag="locationMove" :label="__('tracking.destination')" required>
            <option value="">{{ __('people.choose') }}</option>
            @foreach($destinations as $destination)
                <option value="{{ $destination->id }}" @selected($value('physical_location_id') === (string) $destination->id)>{{ $destination->owner->name }} / {{ $destination->name }}</option>
            @endforeach
        </x-ui.select-field>
        @include('assets.tracking-reason', ['bag' => 'locationMove', 'id' => 'move-reason'])
        <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('tracking.save_move') }}</x-ui.button>
    </form>
</details>
