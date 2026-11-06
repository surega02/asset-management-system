<x-layouts.account :title="$permission?->name ?? __('iam.new_permission')">
    <x-ui.panel class="grid max-w-2xl gap-4 p-5">
        @if($permission && in_array($permission->name, \App\Support\IdentityAccess::PERMISSIONS, true))
            <p>{{ __('iam.access_protected') }}</p>
        @else
            <form method="post" action="{{ $permission ? route('catalog.permissions.update', $permission) : route('catalog.permissions.store') }}" class="grid gap-4">
                @csrf
                @if($permission) @method('PUT') @endif
                <x-ui.field name="name" :value="old('name', $permission?->name)" :label="__('iam.permission_name')" maxlength="125" required />
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('iam.save') }}</x-ui.button>
            </form>
            @if($permission)
                <form method="post" action="{{ route('catalog.permissions.delete', $permission) }}" x-data @submit="if (!confirm(@js(__('iam.confirm_delete')))) $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit">{{ __('iam.delete_permission') }}</x-ui.button>
                </form>
            @endif
        @endif
    </x-ui.panel>
</x-layouts.account>
