<x-layouts.app :title="__('foundation.title')">
    <a href="#main-content" class="skip-link">{{ __('dashboard.skip') }}</a>
    <header class="topbar flex-wrap py-2">
        <a href="{{ route('home') }}" class="brand !m-0 !p-0" aria-label="AMS">
            <span class="brand-icon"><x-ui.icon name="box" /></span>
            <strong>AMS</strong>
        </a>
        <x-layouts.preferences />
    </header>
    <main id="main-content" class="main-content" tabindex="-1">
        {{ $slot }}
    </main>
</x-layouts.app>
