<x-layouts.guest :title="__('qr.scan_title')">
    <section class="mx-auto grid max-w-xl gap-5" aria-labelledby="scanner-heading">
        <div class="grid gap-1">
            <h1 id="scanner-heading">{{ __('qr.scan_title') }}</h1>
            <p>{{ __('qr.scan_help') }}</p>
        </div>
        <x-ui.panel class="grid gap-3 p-4 sm:p-6" data-asset-scanner data-prefix="{{ $qrPrefix }}"
            data-unavailable="{{ __('qr.camera_unavailable') }}" data-denied="{{ __('qr.camera_denied') }}"
            data-requesting="{{ __('qr.camera_requesting') }}" data-scanning="{{ __('qr.camera_scanning') }}"
            data-invalid="{{ __('qr.camera_invalid') }}" data-stopped="{{ __('qr.camera_stopped') }}">
            <h2>{{ __('qr.camera') }}</h2>
            <video hidden muted playsinline class="aspect-video w-full rounded-lg bg-[var(--subtle)] object-cover" aria-label="{{ __('qr.camera') }}"></video>
            <canvas hidden aria-hidden="true"></canvas>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="primary" data-start>{{ __('qr.camera_start') }}</x-ui.button>
                <x-ui.button data-stop hidden class="[&[hidden]]:hidden">{{ __('qr.camera_stop') }}</x-ui.button>
            </div>
            <p role="status" data-message>{{ __('qr.camera_hint') }}</p>
            <noscript><p>{{ __('qr.camera_unavailable') }}</p></noscript>
        </x-ui.panel>
        <x-ui.panel class="grid gap-3 p-4 sm:p-6">
            <h2>{{ __('qr.manual') }}</h2>
            <p>{{ __('qr.manual_help') }}</p>
            <form method="get" action="{{ route('assets.find') }}" class="grid gap-3">
                <x-ui.field name="asset_code" :label="__('qr.code')" :value="is_string(old('asset_code')) ? old('asset_code') : ''"
                    placeholder="AST-000001" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="23" required />
                <x-ui.button type="submit" class="justify-self-start">{{ __('qr.find') }}</x-ui.button>
            </form>
        </x-ui.panel>
    </section>
    <script src="{{ asset('build/jsQR.js') }}" defer></script>
    <script type="module" src="{{ asset('build/asset-scanner.mjs') }}"></script>
</x-layouts.guest>
