@props(['name', 'label', 'id' => null, 'bag' => 'default', 'help' => null])

@php
    $fieldId = $id ?? $name;
    $error = $errors->getBag($bag)->first($name);
@endphp
<div class="grid min-w-0 gap-1.5">
    <label class="font-semibold" for="{{ $fieldId }}">{{ $label }}</label>
    <select {{ $attributes->class('ui-select w-full') }} id="{{ $fieldId }}" name="{{ $name }}"
        @if($error) aria-invalid="true" @endif
        @if($help || $error) aria-describedby="{{ $help ? $fieldId.'-help ' : '' }}{{ $error ? $fieldId.'-error' : '' }}" @endif>
        {{ $slot }}
    </select>
    @if($help)<p id="{{ $fieldId }}-help" class="text-sm">{{ $help }}</p>@endif
    @if($error)<p id="{{ $fieldId }}-error" class="text-sm text-[var(--error)]">{{ $error }}</p>@endif
</div>
