<x-layouts.account :title="$account->employee->name">
    <p><span class="font-mono">{{ $account->employee->nik }}</span> · {{ __($account->is_active ? 'iam.active' : 'iam.inactive') }}</p>
    <x-ui.panel class="grid gap-4 p-5" aria-labelledby="account-profile">
        <h2 id="account-profile">{{ __('iam.account_profile') }}</h2>
        @can('manage', $account)
            <form method="post" action="{{ route('accounts.update', $account) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-ui.field name="email" type="email" :value="$account->email" :label="__('iam.optional_email')" maxlength="254" />
                <div class="grid gap-1.5">
                    <label for="is_active" class="font-semibold">{{ __('iam.account_status') }}</label>
                    <select class="ui-select" id="is_active" name="is_active">
                        <option value="1" @selected(old('is_active', (int) $account->is_active) == 1)>{{ __('iam.active') }}</option>
                        <option value="0" @selected(old('is_active', (int) $account->is_active) == 0)>{{ __('iam.inactive') }}</option>
                    </select>
                </div>
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('iam.save') }}</x-ui.button>
            </form>
        @else
            <p>{{ __('iam.email') }} · {{ $account->email ?? __('iam.no_email') }}</p>
        @endcan
        @can('reset', $account)
            <form method="post" action="{{ route('accounts.reset', $account) }}" class="grid gap-2">
                @csrf
                <p>{{ __($account->email ? 'iam.admin_reset_email' : 'iam.admin_reset_handover') }}</p>
                <x-ui.button type="submit" class="justify-self-start">{{ __('iam.request_admin_reset') }}</x-ui.button>
            </form>
        @endcan
    </x-ui.panel>

    @can('access', $account)
        <x-ui.panel class="grid gap-4 p-5" aria-labelledby="account-access">
            <h2 id="account-access">{{ __('iam.account_access') }}</h2>
            <p>{{ __('iam.scope_help') }}</p>
            <form method="post" action="{{ route('accounts.access', $account) }}" class="grid gap-5">
                @csrf
                @method('PUT')
                <fieldset class="grid gap-2">
                    <legend class="mb-2 font-semibold">{{ __('iam.roles') }}</legend>
                    @foreach($roles as $role)
                        <label class="flex items-start gap-2">
                            <input type="checkbox" class="mt-1" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, (array) old('roles', $account->roles->modelKeys())))>
                            <span>{{ __('iam.roles_named.'.$role->name) === 'iam.roles_named.'.$role->name ? $role->name : __('iam.roles_named.'.$role->name) }}</span>
                        </label>
                    @endforeach
                </fieldset>
                @foreach($scopeOptions as $type => $options)
                    <fieldset class="grid gap-2">
                        <legend class="mb-2 font-semibold">{{ __('iam.scope_'.$type) }}</legend>
                        @forelse($options as $option)
                            @php($value = $type.':'.$option->id)
                            <label class="flex items-start gap-2">
                                <input type="checkbox" class="mt-1" name="scopes[]" value="{{ $value }}" @checked(in_array($value, (array) old('scopes', $selectedScopes), true))>
                                <span>{{ isset($option->branch) ? $option->branch.' / ' : '' }}{{ isset($option->division) ? $option->division.' / ' : '' }}{{ $option->name }}</span>
                            </label>
                        @empty
                            <p>{{ __('iam.no_scope_targets') }}</p>
                        @endforelse
                    </fieldset>
                @endforeach
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('iam.save_access') }}</x-ui.button>
            </form>
        </x-ui.panel>
    @endcan
</x-layouts.account>
