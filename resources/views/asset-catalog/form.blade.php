@php
    $value = static function (string $key, mixed $default = ''): string {
        $input = old($key, $default);

        return is_scalar($input) ? (string) $input : '';
    };
    $isDefault = $record && $defaultId === $record->id;
@endphp
<x-layouts.account :title="$record?->name ?? __('asset_catalog.create_title', ['section' => __('asset_catalog.'.$section->value)])">
    <div class="flex flex-wrap gap-2">
        <a class="nav-link" href="{{ route('asset-catalog.index', ['section' => $section, 'status' => $record && !$record->is_active ? 'archived' : null]) }}">{{ __('masters.back', ['section' => __('asset_catalog.'.$section->value)]) }}</a>
        @if($record instanceof \App\Models\AssetType)
            <a class="ui-button" href="{{ route('asset-attributes.index', $record) }}">{{ __('asset_catalog.attributes') }}</a>
        @endif
    </div>
    @if($isDefault)
        <p class="action-note !mt-0" role="status">{{ __('asset_catalog.current_default') }}</p>
    @endif
    <x-ui.panel class="grid max-w-2xl gap-4 p-4 sm:p-6">
        @if($record)
            <dl class="grid gap-3">
                <div><dt class="font-semibold">{{ __('masters.code') }}</dt><dd class="wrap-anywhere">{{ $record->code }}</dd></div>
                @if($record instanceof \App\Models\AssetType)
                    <div><dt class="font-semibold">{{ __('asset_catalog.category') }}</dt><dd class="wrap-anywhere">{{ $record->category->name }}</dd></div>
                @elseif(!$canWrite && $record instanceof \App\Models\AssetCategory)
                    <div><dt class="font-semibold">{{ __('asset_catalog.parent') }}</dt><dd class="wrap-anywhere">{{ $record->parent?->name ?? __('masters.no_parent') }}</dd></div>
                @endif
                @if(!$canWrite)
                    <div><dt class="font-semibold">{{ __('masters.status') }}</dt><dd>{{ __('masters.'.($record->is_active ? 'active' : 'archived')) }}</dd></div>
                @endif
            </dl>
        @endif
        @if($canWrite)
            <form method="post" action="{{ $record ? route('asset-catalog.update', [$section, $record]) : route('asset-catalog.store', $section) }}" class="grid gap-4">
                @csrf
                @if($record)
                    @method('PUT')
                @else
                    <x-ui.field name="master_code" :label="__('masters.code')" :value="$value('master_code')" required maxlength="64" />
                @endif
                <x-ui.field name="name" :label="__('masters.name')" :value="$value('name', $record?->name)" required maxlength="255" />
                @if($section === \App\Support\AssetMasterType::Category)
                    <x-ui.select-field name="parent_id" :label="__('asset_catalog.parent')">
                        <option value="">{{ __('masters.no_parent') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($value('parent_id', $record?->parent_id) === (string) $category->id)>{{ $category->name }} ({{ $category->code }})</option>
                        @endforeach
                    </x-ui.select-field>
                @elseif($section === \App\Support\AssetMasterType::Type && !$record)
                    <x-ui.select-field name="category_id" :label="__('asset_catalog.category')" required>
                        <option value="">{{ __('people.choose') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($value('category_id') === (string) $category->id)>{{ $category->name }} ({{ $category->code }})</option>
                        @endforeach
                    </x-ui.select-field>
                @endif
                <x-ui.select-field name="is_active" :label="__('masters.status')" :help="$isDefault ? __('asset_catalog.replace_default') : __('masters.archive_help')" required>
                    <option value="1" @selected($value('is_active', (int) ($record?->is_active ?? true)) === '1')>{{ __('masters.active') }}</option>
                    <option value="0" @selected($value('is_active', (int) ($record?->is_active ?? true)) === '0')>{{ __('masters.archived') }}</option>
                </x-ui.select-field>
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('masters.save') }}</x-ui.button>
            </form>
        @endif
        @if($record?->is_active && $section->defaultColumn() && !$isDefault)
            @can('setDefault', $record)
                <form method="post" action="{{ route('asset-catalog.default', [$section, $record]) }}" class="border-t border-[var(--border)] pt-4">
                    @csrf
                    <x-ui.button type="submit">{{ __('asset_catalog.set_default') }}</x-ui.button>
                </form>
            @endcan
        @endif
    </x-ui.panel>
</x-layouts.account>
