<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ auth()->user()?->theme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('qr.preview') }} · AMS</title>
    <link rel="stylesheet" href="{{ asset('build/app.css') }}">
    @php
        $width = sprintf('%.2F', $template->width_mm);
        $height = sprintf('%.2F', $template->height_mm);
        $qrSize = sprintf('%.2F', min(34, $template->height_mm - 6));
    @endphp
    <style>
        @page { size: {{ $width }}mm {{ $height }}mm; margin: 0; }
        .label-sheet {
            box-sizing: border-box;
            width: {{ $width }}mm;
            height: {{ $height }}mm;
            padding: 2mm;
            display: grid;
            grid-template-columns: {{ $qrSize }}mm minmax(0, 1fr);
            gap: 2mm;
            align-items: center;
            background: white;
            color: black;
            box-shadow: inset 0 0 0 1px #c5ccd0;
            overflow: hidden;
        }
        .label-sheet img { width: {{ $qrSize }}mm; height: {{ $qrSize }}mm; }
        .label-copy { overflow: hidden; overflow-wrap: anywhere; font-size: 8pt; line-height: 1.16; }
        .label-copy strong { display: block; font-size: 9pt; font-family: monospace; margin-bottom: 1mm; }
        .label-copy p { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
        @media print {
            html, body { margin: 0; padding: 0; background: white; }
            .print-controls { display: none !important; }
            .label-list { display: block !important; padding: 0 !important; margin: 0 !important; }
            .label-sheet { break-inside: avoid; break-after: page; box-shadow: none; print-color-adjust: exact; }
            .label-sheet:last-child { break-after: auto; }
        }
    </style>
    <script type="module" src="{{ asset('build/asset-label-print.mjs') }}"></script>
</head>
<body>
    <main>
        <div class="main-content print-controls grid gap-4">
            <h1>{{ __('qr.preview') }}</h1>
            <p>{{ __('qr.print_help') }}</p>
            <div class="flex flex-wrap gap-2">
                <a class="ui-button" href="{{ route('assets.labels.create') }}">{{ __('qr.back_labels') }}</a>
                <x-ui.button variant="primary" data-print-labels>{{ __('qr.print') }}</x-ui.button>
            </div>
        </div>
        <div class="main-content label-list flex flex-wrap gap-4 pt-0">
            @foreach($labels as $label)
                <article class="label-sheet" aria-label="{{ $label['code'] }}">
                    <img src="{{ $label['qr'] }}" alt="{{ __('qr.image_alt', ['code' => $label['code']]) }}">
                    <div class="label-copy">
                        <strong>{{ $label['code'] }}</strong>
                        @foreach($fields as $field)
                            <p>{{ $label[$field] }}</p>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </main>
</body>
</html>
