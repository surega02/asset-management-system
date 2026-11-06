@props(['variant' => 'outline', 'type' => 'button'])

<button type="{{ $type }}" {{ $attributes->class(['ui-button', 'ui-button-primary' => $variant === 'primary']) }}>
    {{ $slot }}
</button>
