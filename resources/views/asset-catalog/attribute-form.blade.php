@php
    $canWrite = $assetType->is_active && (!$definition || $definition->is_active) && \Illuminate\Support\Facades\Gate::allows('update', $assetType);
    $value = static function (string $key, mixed $default = ''): string {
        $input = old($key, $default);

        return is_scalar($input) ? (string) $input : '';
    };
@endphp
<x-layouts.account :title="$definition?->label ?? __('asset_catalog.add_attribute')">
    <a class="nav-link justify-self-start" href="{{ route('asset-attributes.index', $assetType) }}">{{ __('asset_catalog.back_attributes') }}</a>
    <x-ui.panel class="grid max-w-2xl gap-4 p-4 sm:p-6">
        @if($definition)
            <dl class="grid gap-3">
                <div><dt class="font-semibold">{{ __('asset_catalog.fields.key') }}</dt><dd class="wrap-anywhere">{{ $definition->key }}</dd></div>
                <div><dt class="font-semibold">{{ __('asset_catalog.version_label') }}</dt><dd>{{ $definition->version }} · {{ __('masters.'.($definition->is_active ? 'active' : 'archived')) }}</dd></div>
                @if(!$canWrite)
                    <div><dt class="font-semibold">{{ __('asset_catalog.fields.data_type') }}</dt><dd>{{ __('asset_catalog.type_'.$definition->data_type) }}</dd></div>
                    <div><dt class="font-semibold">{{ __('asset_catalog.fields.is_required') }}</dt><dd>{{ __('asset_catalog.'.($definition->configuration->is_required ? 'required' : 'optional')) }}</dd></div>
                    <div><dt class="font-semibold">{{ __('asset_catalog.fields.sort_order') }}</dt><dd>{{ $definition->configuration->sort_order }}</dd></div>
                    @foreach($definition->validation_rules ?? [] as $bound => $limit)
                        <div><dt class="font-semibold">{{ __('asset_catalog.fields.'.$bound) }}</dt><dd>{{ $limit }}</dd></div>
                    @endforeach
                    @if($definition->allowed_options)
                        <div><dt class="font-semibold">{{ __('asset_catalog.fields.options') }}</dt><dd><ul class="list-disc ps-5">
                            @foreach($definition->allowed_options as $option)<li class="wrap-anywhere">{{ $option }}</li>@endforeach
                        </ul></dd></div>
                    @endif
                @endif
            </dl>
        @endif
        @if($canWrite)
            @if($definition)<p>{{ __('asset_catalog.revision_help') }}</p>@endif
            <form method="post" action="{{ $definition ? route('asset-attributes.update', [$assetType, $definition]) : route('asset-attributes.store', $assetType) }}"
                class="grid gap-4" x-data="{ type: @js($value('data_type', $definition?->data_type ?? 'text')) }">
                @csrf
                @if($definition)
                    @method('PUT')
                @else
                    <x-ui.field name="key" :label="__('asset_catalog.fields.key')" :value="$value('key')" pattern="[a-z][a-z0-9_]*" required maxlength="64" aria-describedby="attribute-key-help" />
                    <p id="attribute-key-help" class="-mt-2 text-sm">{{ __('asset_catalog.key_help') }}</p>
                @endif
                <x-ui.field name="label" :label="__('asset_catalog.fields.label')" :value="$value('label', $definition?->label)" required maxlength="255" />
                <x-ui.select-field name="data_type" :label="__('asset_catalog.fields.data_type')" required x-model="type">
                    @foreach(\App\Support\AssetAttributeType::cases() as $type)
                        <option value="{{ $type->value }}" @selected($value('data_type', $definition?->data_type ?? 'text') === $type->value)>{{ __('asset_catalog.type_'.$type->value) }}</option>
                    @endforeach
                </x-ui.select-field>
                <div class="grid gap-1.5" x-show="type === 'select'">
                    <label for="options" class="font-semibold">{{ __('asset_catalog.fields.options') }}</label>
                    <textarea name="options" id="options" class="ui-select w-full py-2" rows="4" maxlength="6000" x-bind:disabled="type !== 'select'"
                        aria-describedby="options-help @error('options') options-error @enderror" @error('options') aria-invalid="true" @enderror>{{ $value('options', implode("\n", $definition?->allowed_options ?? [])) }}</textarea>
                    <p id="options-help" class="text-sm">{{ __('asset_catalog.options_help') }}</p>
                    @error('options')<p id="options-error" class="text-sm text-[var(--error)]">{{ $message }}</p>@enderror
                </div>
                <fieldset class="grid gap-2" x-show="['text','integer','decimal'].includes(type)" x-bind:disabled="!['text','integer','decimal'].includes(type)">
                    <legend class="font-semibold">{{ __('asset_catalog.bounds') }}</legend>
                    <p class="text-sm">{{ __('asset_catalog.bounds_help') }}</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach(['min', 'max'] as $bound)
                            <x-ui.field :name="$bound" :label="__('asset_catalog.fields.'.$bound)" :value="$value($bound, $definition?->validation_rules[$bound] ?? '')" maxlength="20" />
                        @endforeach
                    </div>
                </fieldset>
                <x-ui.select-field name="is_required" :label="__('asset_catalog.fields.is_required')" required>
                    <option value="0" @selected($value('is_required', (int) ($definition?->configuration?->is_required ?? false)) === '0')>{{ __('asset_catalog.optional') }}</option>
                    <option value="1" @selected($value('is_required', (int) ($definition?->configuration?->is_required ?? false)) === '1')>{{ __('asset_catalog.required') }}</option>
                </x-ui.select-field>
                <x-ui.field name="sort_order" type="number" :label="__('asset_catalog.fields.sort_order')" :value="$value('sort_order', $definition?->configuration?->sort_order ?? 0)" min="0" max="65535" required />
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ $definition ? __('asset_catalog.save_revision') : __('masters.save') }}</x-ui.button>
            </form>
            @if($definition)
                <form method="post" action="{{ route('asset-attributes.archive', [$assetType, $definition]) }}" class="grid gap-2 border-t border-[var(--border)] pt-4">
                    @csrf
                    <p>{{ __('asset_catalog.archive_help') }}</p>
                    <x-ui.button type="submit" class="justify-self-start">{{ __('asset_catalog.archive') }}</x-ui.button>
                </form>
            @endif
        @endif
    </x-ui.panel>
</x-layouts.account>
