<x-layouts.guest :title="__('iam.reset_title')">
    <x-ui.panel class="mx-auto grid max-w-md gap-5 p-5 sm:p-7" aria-labelledby="reset-title">
        <h1 id="reset-title">{{ __('iam.reset_title') }}</h1>
        <p>{{ __('iam.reset_help') }}</p>
        <form method="post" action="{{ route('password.update') }}" class="grid gap-4">
            @csrf
            <x-ui.field name="reset_code" :label="__('iam.reset_code')" autocomplete="one-time-code" maxlength="48" required />
            <x-ui.field name="password" type="password" :label="__('iam.new_password')" autocomplete="new-password" minlength="12" required />
            <x-ui.field name="password_confirmation" type="password" :label="__('iam.confirm_password')" autocomplete="new-password" minlength="12" required />
            <x-ui.button type="submit" variant="primary">{{ __('iam.reset_title') }}</x-ui.button>
        </form>
        <a class="underline underline-offset-4" href="{{ route('login') }}">{{ __('iam.back_login') }}</a>
    </x-ui.panel>
</x-layouts.guest>
