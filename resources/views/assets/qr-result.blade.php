<x-layouts.guest :title="__('qr.public_title')">
    <section class="mx-auto grid max-w-xl gap-5" aria-labelledby="qr-result-heading">
        <div class="grid gap-1">
            <h1 id="qr-result-heading">{{ __('qr.public_title') }}</h1>
            <p>{{ __('qr.public_help') }}</p>
        </div>
        <x-ui.panel class="p-4 sm:p-6">
            <dl data-public-asset class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach($fields as $key => $value)
                    <div data-public-field="{{ $key }}">
                        <dt class="font-semibold">{{ __('qr.'.$key) }}</dt>
                        <dd class="wrap-anywhere">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.panel>
        <div class="flex flex-wrap gap-2">
            <a class="ui-button ui-button-primary" href="{{ route('assets.qr.detail', $token) }}">{{ __('qr.open_detail') }}</a>
            <a class="ui-button" href="{{ route('assets.scan') }}">{{ __('qr.scan_title') }}</a>
        </div>
    </section>
</x-layouts.guest>
