<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('dashboard.dashboard') }} · AMS</title>
    <script>
        try {
            const theme = localStorage.getItem('ams-preview-theme');
            document.documentElement.dataset.theme = theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        } catch {}
    </script>
    <link rel="stylesheet" href="{{ asset('build/app.css') }}">
    @livewireStyles
</head>
<body>
    <a href="#main-content" class="skip-link">{{ __('dashboard.skip') }}</a>
    <div class="shell">
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
                    <details class="mobile-menu">
                        <summary class="ui-button icon-button" aria-label="{{ __('dashboard.menu') }}"><x-ui.icon name="menu" /></summary>
                        <x-layouts.navigation class="mobile-nav" x-data @click="$el.closest('details').open = false" />
                    </details>
                    <span class="topbar-title">{{ __('dashboard.workspace') }}</span>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <form action="{{ route('preview.language') }}" method="post" class="flex items-center gap-1">
                        @csrf
                        <label class="sr-only" for="locale">{{ __('dashboard.locale') }}</label>
                        <select class="ui-select language-select" id="locale" name="locale" x-data @change="$el.form.requestSubmit()">
                            <option value="id" lang="id" @selected(app()->getLocale() === 'id')>ID</option>
                            <option value="en" lang="en" @selected(app()->getLocale() === 'en')>EN</option>
                            <option value="zh-CN" lang="zh-CN" @selected(app()->getLocale() === 'zh-CN')>简中</option>
                        </select>
                        <noscript><x-ui.button type="submit">{{ __('dashboard.apply_locale') }}</x-ui.button></noscript>
                    </form>
                    <x-ui.button class="icon-button" aria-label="{{ __('dashboard.theme') }}"
                        x-data="{ dark: document.documentElement.dataset.theme === 'dark' }"
                        x-bind:aria-pressed="dark"
                        @click="dark = !dark; document.documentElement.dataset.theme = dark ? 'dark' : 'light'; try { localStorage.setItem('ams-preview-theme', dark ? 'dark' : 'light') } catch {}">
                        <x-ui.icon name="sun" class="theme-sun" />
                        <x-ui.icon name="moon" class="theme-moon" />
                    </x-ui.button>
                </div>
            </header>

            <main id="main-content" class="main-content" tabindex="-1">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
