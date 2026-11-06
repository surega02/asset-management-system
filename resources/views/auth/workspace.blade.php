<x-layouts.account :title="__('iam.workspace')">
    <x-ui.panel class="grid max-w-2xl gap-3 p-5" aria-labelledby="account-heading">
        <h2 id="account-heading">{{ auth()->user()->employee->name }}</h2>
        <p>{{ __('iam.workspace_help') }}</p>
        <a class="ui-button justify-self-start" href="{{ route('security.show') }}">{{ __('iam.security') }}</a>
    </x-ui.panel>
</x-layouts.account>
