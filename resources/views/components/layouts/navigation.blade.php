<nav aria-label="{{ __('dashboard.navigation') }}" {{ $attributes->class('grid gap-1') }}>
    <a class="nav-link nav-link-current" href="#main-content" aria-current="page"><x-ui.icon name="dashboard" />{{ __('dashboard.dashboard') }}</a>
    <a class="nav-link" href="#locations"><x-ui.icon name="pin" />{{ __('dashboard.locations') }}</a>
    <a class="nav-link" href="#activity"><x-ui.icon name="activity" />{{ __('dashboard.activity') }}</a>
</nav>
