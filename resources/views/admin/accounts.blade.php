<x-layouts.account :title="__('iam.accounts')">
    @if(auth()->user()->checkPermissionTo('users.manage'))
        <x-ui.panel class="grid gap-4 p-5" aria-labelledby="create-account">
            <h2 id="create-account">{{ __('iam.create_account') }}</h2>
            <p>{{ __('iam.create_help') }}</p>
            <form method="post" action="{{ route('accounts.store') }}" class="grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto]">
                @csrf
                <x-ui.field name="nik" label="NIK" maxlength="64" required />
                <x-ui.field name="email" type="email" :label="__('iam.optional_email')" maxlength="254" />
                <x-ui.button type="submit" variant="primary">{{ __('iam.create_account') }}</x-ui.button>
            </form>
        </x-ui.panel>
    @endif
    <x-ui.panel class="grid gap-4 p-5" aria-labelledby="account-list">
        <h2 id="account-list">{{ __('iam.account_list') }}</h2>
        <ul class="grid gap-2">
            @forelse($accounts as $account)
                <li class="flex flex-wrap items-center justify-between gap-2 border-b py-2" style="border-color: var(--border)">
                    <a href="{{ route('accounts.show', $account) }}" class="font-semibold underline underline-offset-4">{{ $account->employee->name }}</a>
                    <span class="asset-code">{{ $account->employee->nik }}</span>
                    <span class="status-tag">{{ __($account->is_active ? 'iam.active' : 'iam.inactive') }}</span>
                </li>
            @empty
                <li>{{ __('iam.no_accounts') }}</li>
            @endforelse
        </ul>
        {{ $accounts->links() }}
    </x-ui.panel>
</x-layouts.account>
