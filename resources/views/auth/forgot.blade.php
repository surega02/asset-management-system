<x-layouts.guest :title="__('iam.forgot')">
    <x-ui.panel class="mx-auto grid max-w-md gap-5 p-5 sm:p-7" aria-labelledby="forgot-title">
        <h1 id="forgot-title">{{ __('iam.forgot') }}</h1>
        <p>{{ __('iam.forgot_help') }}</p>
        <x-ui.feedback />
        <form method="post" action="{{ route('password.email') }}" class="grid gap-4">
            @csrf
            <x-ui.field name="email" type="email" :label="__('iam.email')" autocomplete="email" maxlength="254" required />
            <x-ui.button type="submit" variant="primary">{{ __('iam.send_code') }}</x-ui.button>
        </form>
        <a class="underline underline-offset-4" href="{{ route('password.reset') }}">{{ __('iam.have_code') }}</a>
    </x-ui.panel>
</x-layouts.guest>
