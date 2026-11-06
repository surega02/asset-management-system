<x-layouts.account :title="__('iam.catalog')">
    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.panel class="grid gap-4 p-5" aria-labelledby="role-list">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="role-list">{{ __('iam.roles') }}</h2>
                <a class="ui-button" href="{{ route('catalog.roles.create') }}">{{ __('iam.new_role') }}</a>
            </div>
            <ul class="grid gap-2">
                @foreach($roles as $role)
                    <li class="border-b py-2" style="border-color: var(--border)">
                        <a class="underline underline-offset-4" href="{{ route('catalog.roles.edit', $role) }}">{{ $role->name }}</a>
                    </li>
                @endforeach
            </ul>
            {{ $roles->withQueryString()->links() }}
        </x-ui.panel>
        <x-ui.panel class="grid gap-4 p-5" aria-labelledby="permission-list">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="permission-list">{{ __('iam.permissions') }}</h2>
                <a class="ui-button" href="{{ route('catalog.permissions.create') }}">{{ __('iam.new_permission') }}</a>
            </div>
            <ul class="grid gap-2">
                @foreach($permissions as $permission)
                    <li class="border-b py-2" style="border-color: var(--border)">
                        <a class="underline underline-offset-4" href="{{ route('catalog.permissions.edit', $permission) }}">{{ $permission->name }}</a>
                    </li>
                @endforeach
            </ul>
            {{ $permissions->withQueryString()->links() }}
        </x-ui.panel>
    </div>
</x-layouts.account>
