@props(['title' => __('dashboard.dashboard')])

<!doctype html>
<html lang="{{ app()->getLocale() }}" data-account-theme="{{ auth()->user()?->theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · AMS</title>
    <script>
        try {
            const theme = document.documentElement.dataset.accountTheme || localStorage.getItem('ams-preview-theme');
            document.documentElement.dataset.theme = theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        } catch {}
    </script>
    <link rel="stylesheet" href="{{ asset('build/app.css') }}">
    @livewireStyles
</head>
<body x-data @ams-locale-changed.window="document.documentElement.lang = $event.detail.locale; document.title = $event.detail.title">
    {{ $slot }}
    @livewireScripts
</body>
</html>
