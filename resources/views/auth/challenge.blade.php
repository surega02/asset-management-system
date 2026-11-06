<x-layouts.guest :title="__('iam.challenge')">
    <x-ui.panel class="mx-auto grid max-w-md gap-5 p-5 sm:p-7" aria-labelledby="challenge-title">
        <div class="grid gap-2">
            <h1 id="challenge-title">{{ __('iam.challenge') }}</h1>
            <p>{{ __('iam.factor_help') }}</p>
        </div>
        <form method="post" action="{{ route('two-factor.verify') }}" class="grid gap-4">
            @csrf
            <x-ui.field name="code" :label="__('iam.factor_code')" autocomplete="one-time-code" maxlength="64" required />
            <x-ui.button type="submit" variant="primary">{{ __('iam.verify') }}</x-ui.button>
        </form>
        <a class="underline underline-offset-4" href="{{ route('login') }}">{{ __('iam.back_login') }}</a>
    </x-ui.panel>
</x-layouts.guest>
