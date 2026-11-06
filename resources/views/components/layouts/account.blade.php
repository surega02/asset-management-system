@props(['title'])

<x-layouts.guest :title="$title">
    <div class="grid gap-5">
        <nav aria-label="{{ __('iam.account_navigation') }}" class="flex flex-wrap items-center gap-2">
            <a class="nav-link" href="{{ route('workspace') }}">{{ __('iam.workspace') }}</a>
            <a class="nav-link" href="{{ route('security.show') }}">{{ __('iam.security') }}</a>
            @can('viewAny', \App\Models\User::class)
                <a class="nav-link" href="{{ route('accounts.index') }}">{{ __('iam.accounts') }}</a>
            @endcan
            @can('manage-access-catalog')
                <a class="nav-link" href="{{ route('catalog.index') }}">{{ __('iam.catalog') }}</a>
            @endcan
            @can('viewAny', \App\Models\Branch::class)
                <a @class(['nav-link', 'nav-link-current' => request()->routeIs('masters.*')])
                    href="{{ route('masters.index', \App\Support\MasterType::Branch) }}"
                    @if(request()->routeIs('masters.*')) aria-current="true" @endif>{{ __('masters.title') }}</a>
            @endcan
            @can('viewAny', \App\Models\Employee::class)
                <a @class(['nav-link', 'nav-link-current' => request()->routeIs('employees.*')]) href="{{ route('employees.index') }}"
                    @if(request()->routeIs('employees.*')) aria-current="true" @endif>{{ __('people.employees') }}</a>
            @endcan
            @can('viewAny', \App\Models\StructuralAppointment::class)
                <a @class(['nav-link', 'nav-link-current' => request()->routeIs('appointments.*')]) href="{{ route('appointments.index') }}"
                    @if(request()->routeIs('appointments.*')) aria-current="true" @endif>{{ __('people.appointments') }}</a>
            @endcan
            @can('viewAny', \App\Models\AssetCategory::class)
                <a @class(['nav-link', 'nav-link-current' => request()->routeIs('asset-catalog.*', 'asset-attributes.*')]) href="{{ route('asset-catalog.index', \App\Support\AssetMasterType::Category) }}"
                    @if(request()->routeIs('asset-catalog.*', 'asset-attributes.*')) aria-current="true" @endif>{{ __('asset_catalog.title') }}</a>
            @endcan
            <form method="post" action="{{ route('logout') }}" class="sm:ms-auto">
                @csrf
                <x-ui.button type="submit">{{ __('iam.logout') }}</x-ui.button>
            </form>
        </nav>
        <h1 class="wrap-anywhere">{{ $title }}</h1>
        <x-ui.feedback />
        {{ $slot }}
    </div>
</x-layouts.guest>
