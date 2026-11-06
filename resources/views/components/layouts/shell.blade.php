<div class="shell">
    <a href="#main-content" class="skip-link">{{ __('dashboard.skip') }}</a>
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-icon"><x-ui.icon name="box" /></span>
            <span><strong>AMS</strong><span>{{ __('dashboard.asset_workspace') }}</span></span>
        </div>
        <x-layouts.navigation />
        <div class="sidebar-footer">
            <span class="role-mark" aria-hidden="true"><x-ui.icon name="box" /></span>
            <span><strong>{{ __('dashboard.officer') }}</strong><span>{{ __('dashboard.preview') }}</span></span>
        </div>
    </aside>

    <div class="min-w-0">
        <header class="topbar">
            <div class="flex min-w-0 items-center gap-3">
                <details class="mobile-menu" x-data @keydown.escape="if ($el.open) { $el.open = false; $refs.toggle.focus() }">
                    <summary class="ui-button icon-button" aria-label="{{ __('dashboard.menu') }}" x-ref="toggle"><x-ui.icon name="menu" /></summary>
                    <x-layouts.navigation class="mobile-nav" @click="$el.closest('details').open = false" />
                </details>
                <span class="topbar-title">{{ __('dashboard.workspace') }}</span>
            </div>

            <x-layouts.preferences live />
        </header>

        <main id="main-content" class="main-content" tabindex="-1">
            {{ $slot }}
        </main>
    </div>
</div>
