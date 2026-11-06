<x-layouts.guest>
    <x-ui.panel class="mx-auto grid max-w-2xl gap-4 p-5 sm:p-8" aria-labelledby="welcome-title">
        <h1 id="welcome-title">{{ __('foundation.title') }}</h1>
        <p>{{ __('foundation.description') }}</p>
        @error('locale')
            <p class="lookup-error" role="alert">{{ __('foundation.invalid_locale') }}</p>
        @enderror
        <a class="ui-button ui-button-primary justify-self-start" href="{{ route(auth()->check() ? 'workspace' : 'login') }}">
            {{ __(auth()->check() ? 'iam.workspace' : 'iam.login') }}
        </a>
        @if(app()->environment('local', 'testing') && \Illuminate\Support\Facades\Route::has('preview.dashboard'))
            <a class="ui-button justify-self-start" href="{{ route('preview.dashboard') }}">
                {{ __('foundation.open_preview') }} <x-ui.icon name="arrow" class="size-4" />
            </a>
        @endif
    </x-ui.panel>
</x-layouts.guest>
