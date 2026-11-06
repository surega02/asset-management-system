<x-layouts.guest :title="__('iam.login')">
    <x-ui.panel class="mx-auto grid max-w-md gap-5 p-5 sm:p-7" aria-labelledby="login-title">
        <div class="grid gap-2">
            <h1 id="login-title">{{ __('iam.login') }}</h1>
            <p>{{ __('iam.login_help') }}</p>
        </div>
        <x-ui.feedback />
        <form method="post" action="{{ route('login.store') }}" class="grid gap-4">
            @csrf
            <x-ui.field name="login" :label="__('iam.identifier')" autocomplete="username" maxlength="254" required />
            <x-ui.field name="password" type="password" :label="__('iam.password')" autocomplete="current-password" required />
            <x-ui.button type="submit" variant="primary">{{ __('iam.login') }}</x-ui.button>
        </form>
        <div class="flex flex-wrap gap-x-5 gap-y-2">
            <a class="underline underline-offset-4" href="{{ route('password.request') }}">{{ __('iam.forgot') }}</a>
            <a class="underline underline-offset-4" href="{{ route('password.reset') }}">{{ __('iam.have_code') }}</a>
        </div>
    </x-ui.panel>
</x-layouts.guest>
