<x-layouts.account :title="__('people.add_employee')">
    <a class="nav-link justify-self-start" href="{{ route('employees.index') }}">{{ __('people.back_employees') }}</a>
    <x-ui.panel class="max-w-2xl p-4 sm:p-6">
        @if($departments->isEmpty())
            <p>{{ __('people.no_units') }}</p>
        @else
            @include('people.profile-fields')
        @endif
    </x-ui.panel>
</x-layouts.account>
