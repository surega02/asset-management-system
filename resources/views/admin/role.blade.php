<x-layouts.account :title="$role?->name ?? __('iam.new_role')">
    <x-ui.panel class="grid max-w-2xl gap-4 p-5">
        @if($role?->name === \App\Support\IdentityAccess::SUPER_ADMIN)
            <p>{{ __('iam.access_protected') }}</p>
            <ul class="grid gap-1">@foreach($role->permissions as $permission)<li>{{ $permission->name }}</li>@endforeach</ul>
        @else
            <form method="post" action="{{ $role ? route('catalog.roles.update', $role) : route('catalog.roles.store') }}" class="grid gap-4">
                @csrf
                @if($role) @method('PUT') @endif
                <x-ui.field name="name" :value="old('name', $role?->name)" :label="__('iam.role_name')"
                    maxlength="125" :readonly="$role && in_array($role->name, \App\Support\IdentityAccess::ROLES, true)" required />
                <fieldset class="grid gap-2">
                    <legend class="mb-2 font-semibold">{{ __('iam.permissions') }}</legend>
                    @foreach($permissions as $permission)
                        <label class="flex items-start gap-2">
                            <input type="checkbox" class="mt-1" name="permissions[]" value="{{ $permission->id }}"
                                @checked(in_array($permission->id, (array) old('permissions', $role?->permissions->modelKeys() ?? [])))>
                            <span class="break-all">{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('iam.save') }}</x-ui.button>
            </form>
            @if($role && !in_array($role->name, \App\Support\IdentityAccess::ROLES, true))
                <form method="post" action="{{ route('catalog.roles.delete', $role) }}" x-data @submit="if (!confirm(@js(__('iam.confirm_delete')))) $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit">{{ __('iam.delete_role') }}</x-ui.button>
                </form>
            @endif
        @endif
    </x-ui.panel>
</x-layouts.account>
