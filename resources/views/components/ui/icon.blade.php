@props(['name'])

<svg {{ $attributes->class('size-5 shrink-0') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('box')
            <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z M4 7.5l8 4.5 8-4.5 M12 12v9 M8 5.25l8 4.5"/>
            @break
        @case('dashboard')
            <rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><rect x="13.5" y="13.5" width="7" height="7" rx="1"/>
            @break
        @case('pin')
            <path d="M19 10c0 5-7 10-7 10S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/>
            @break
        @case('activity')
            <path d="M3 12h4l3-7 4 14 3-7h4"/>
            @break
        @case('plus')
            <path d="M12 5v14 M5 12h14"/>
            @break
        @case('search')
            <circle cx="10.5" cy="10.5" r="5.5"/><path d="m15 15 4.5 4.5"/>
            @break
        @case('scan')
            <path d="M4 8V4h4 M16 4h4v4 M20 16v4h-4 M8 20H4v-4 M4 12h16"/>
            @break
        @case('sun')
            <circle cx="12" cy="12" r="4"/><path d="M12 2v2 M12 20v2 M2 12h2 M20 12h2 M5 5l1.5 1.5 M17.5 17.5 19 19 M19 5l-1.5 1.5 M6.5 17.5 5 19"/>
            @break
        @case('moon')
            <path d="M20 15.5A8.5 8.5 0 0 1 8.5 4 8.5 8.5 0 1 0 20 15.5Z"/>
            @break
        @case('menu')
            <path d="M4 6h16 M4 12h16 M4 18h16"/>
            @break
        @case('arrow')
            <path d="M4 12h15m-6-6 6 6-6 6"/>
            @break
    @endswitch
</svg>
