<x-layouts.account :title="__('iam.security')">
    <x-ui.panel class="grid max-w-2xl gap-4 p-5 sm:p-6" aria-labelledby="security-heading">
        <h2 id="security-heading">{{ __('iam.two_factor') }}</h2>
        <p>{{ __($enabled ? 'iam.two_factor_on' : 'iam.two_factor_off') }}</p>

        @if($recoveryCodes !== [])
            <div class="action-note !grid gap-2">
                <strong>{{ __('iam.recovery_heading') }}</strong>
                <p>{{ __('iam.recovery_help') }}</p>
                <ul class="grid gap-1 font-mono sm:grid-cols-2">
                    @foreach($recoveryCodes as $code)
                        <li>{{ $code }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($enabled)
            <form method="post" action="{{ route('security.regenerate') }}" class="grid gap-4">
                @csrf
                <x-ui.field name="current_password" type="password" :label="__('iam.current_password')" autocomplete="current-password" required />
                <x-ui.field name="code" :label="__('iam.factor_code')" autocomplete="one-time-code" maxlength="64" required />
                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit">{{ __('iam.regenerate') }}</x-ui.button>
                    <x-ui.button type="submit" formaction="{{ route('security.disable') }}" name="_method" value="DELETE"
                        x-data @click="if (!confirm(@js(__('iam.confirm_disable')))) $event.preventDefault()">{{ __('iam.disable_two_factor') }}</x-ui.button>
                </div>
            </form>
        @elseif($secret)
            <p>{{ __('iam.enroll_help') }}</p>
            <img width="200" height="200" src="data:image/svg+xml;base64,{{ $qr }}" alt="{{ __('iam.enrollment_qr') }}" class="bg-white">
            <p class="break-all font-mono">{{ $secret }}</p>
            <form method="post" action="{{ route('security.confirm') }}" class="grid gap-4">
                @csrf
                <x-ui.field name="current_password" type="password" :label="__('iam.current_password')" autocomplete="current-password" required />
                <x-ui.field name="code" :label="__('iam.authenticator_code')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('iam.enable_two_factor') }}</x-ui.button>
            </form>
        @else
            <form method="post" action="{{ route('security.start') }}" class="grid gap-4">
                @csrf
                <x-ui.field name="current_password" type="password" :label="__('iam.current_password')" autocomplete="current-password" required />
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('iam.start_two_factor') }}</x-ui.button>
            </form>
        @endif
    </x-ui.panel>
</x-layouts.account>
