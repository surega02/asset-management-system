<x-layouts.account :title="__('asset_catalog.attributes')">
    <a class="nav-link justify-self-start" href="{{ route('asset-catalog.show', [\App\Support\AssetMasterType::Type, $assetType]) }}">{{ __('asset_catalog.back_type') }}</a>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="grid gap-1">
            <h2 class="wrap-anywhere">{{ $assetType->name }}</h2>
            <p class="max-w-prose">{{ __('asset_catalog.version_help') }}</p>
        </div>
        @if($assetType->is_active)
            @can('update', $assetType)
                <a class="ui-button ui-button-primary" href="{{ route('asset-attributes.create', $assetType) }}">{{ __('asset_catalog.add_attribute') }}</a>
            @endcan
        @endif
    </div>
    <x-ui.panel>
        @if($rows->isEmpty())
            <p class="p-5">{{ __('asset_catalog.no_attributes') }}</p>
        @else
            <table class="w-full table-fixed text-sm">
                <caption class="sr-only">{{ __('asset_catalog.attributes') }}</caption>
                <thead class="bg-[var(--subtle)]">
                    <tr>
                        <th scope="col" class="p-3 text-start">{{ __('asset_catalog.fields.label') }}</th>
                        <th scope="col" class="w-[40%] p-3 text-start sm:w-48">{{ __('masters.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr class="border-t border-[var(--border)] align-top">
                            <th scope="row" class="wrap-anywhere p-3 text-start font-normal">
                                <a class="inline-flex min-h-11 items-center font-semibold underline underline-offset-4" href="{{ route('asset-attributes.edit', [$assetType, $row]) }}">{{ $row->label }}</a>
                                <span class="block text-[var(--muted)]">{{ $row->key }} · {{ __('asset_catalog.version', ['version' => $row->version]) }}</span>
                            </th>
                            <td class="wrap-anywhere p-3">
                                <span class="block">{{ __('masters.'.($row->is_active ? 'active' : 'archived')) }}</span>
                                <span class="block text-[var(--muted)]">{{ __('asset_catalog.type_'.$row->data_type) }} · {{ __('asset_catalog.'.($row->configuration->is_required ? 'required' : 'optional')) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.panel>
    @include('people.pagination', ['pages' => $rows])
</x-layouts.account>
