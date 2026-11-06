@props(['name', 'label', 'type' => 'text', 'value' => null, 'id' => null])

@php($fieldId = $id ?? $name)
<div class="grid gap-1.5">
    <label for="{{ $fieldId }}" class="font-semibold">{{ $label }}</label>
    <input {{ $attributes->class('ui-select w-full') }} id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}"
        @if($type !== 'password') value="{{ $value ?? old($name) }}" @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror>
    @error($name)
        <p id="{{ $fieldId }}-error" class="text-sm" style="color: var(--error)">{{ $message }}</p>
    @enderror
</div>
