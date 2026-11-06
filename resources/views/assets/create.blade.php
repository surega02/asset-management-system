@php
    $value = static function (string $key, mixed $default = ''): string {
        $input = old($key, $default);

        return is_scalar($input) ? (string) $input : '';
    };
@endphp
<x-layouts.account :title="__('assets.register')">
    <a class="nav-link justify-self-start" href="{{ route('assets.index') }}">{{ __('assets.back') }}</a>
    <section class="grid max-w-3xl gap-3" aria-labelledby="asset-type-heading">
        <h2 id="asset-type-heading">{{ __('assets.classification') }}</h2>
        @if($types->isEmpty())
            <p>{{ __('assets.no_types') }}</p>
        @else
            <form method="get" action="{{ route('assets.create') }}" class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                <x-ui.select-field name="type" :label="__('assets.fields.asset_type_id')" required>
                    <option value="">{{ __('people.choose') }}</option>
                    @foreach($types as $option)
                        <option value="{{ $option->id }}" @selected($type?->id === $option->id)>{{ $option->category->name }} / {{ $option->name }} ({{ $option->code }})</option>
                    @endforeach
                </x-ui.select-field>
                <x-ui.button type="submit">{{ __('assets.load_type') }}</x-ui.button>
            </form>
        @endif
    </section>
    @if($type)
        <form method="post" action="{{ route('assets.store') }}" enctype="multipart/form-data" class="grid max-w-3xl gap-4">
            @csrf
            <input type="hidden" name="category_id" value="{{ $type->category_id }}">
            <input type="hidden" name="asset_type_id" value="{{ $type->id }}">
            <x-ui.panel class="grid gap-4 p-4 sm:p-6">
                <h2>{{ __('assets.identification') }}</h2>
                <x-ui.field name="name" :label="__('assets.fields.name')" :value="$value('name')" required maxlength="255" />
                <x-ui.select-field name="physical_location_id" :label="__('assets.fields.physical_location_id')" required>
                    <option value="">{{ __('people.choose') }}</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected($value('physical_location_id') === (string) $location->id)>{{ $location->owner->name }} / {{ $location->name }}</option>
                    @endforeach
                </x-ui.select-field>
                @include('assets.assignee')
                @include('assets.state-fields')
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="purchase_date" type="date" :label="__('assets.fields.purchase_date')" :value="$value('purchase_date')" required />
                    <x-ui.field name="acquisition_cost" :label="__('assets.fields.acquisition_cost')" :value="$value('acquisition_cost')" inputmode="decimal" pattern="[0-9]{1,16}(\.[0-9]{1,2})?" maxlength="19" required />
                </div>
                <p class="text-sm">{{ __('assets.cost_help') }}</p>
            </x-ui.panel>
            @if($fields->isNotEmpty())
                <x-ui.panel class="grid gap-4 p-4 sm:p-6">
                    <h2>{{ __('asset_catalog.attributes') }}</h2>
                    @foreach($fields as $field)
                        @php
                            $name = 'attributes['.$field->key.']';
                            $error = $errors->first('attributes.'.$field->key);
                            $id = 'attribute-'.$field->key;
                            $selected = $value('attributes.'.$field->key);
                        @endphp
                        <div class="grid gap-1.5">
                            <label for="{{ $id }}" class="font-semibold">{{ $field->label }} <span class="font-normal text-[var(--muted)]">· {{ __('asset_catalog.'.($field->is_required ? 'required' : 'optional')) }}</span></label>
                            @if(in_array($field->data_type, ['select', 'boolean'], true))
                                <select name="{{ $name }}" id="{{ $id }}" class="ui-select w-full" @required($field->is_required)
                                    @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
                                    <option value="">{{ __('people.choose') }}</option>
                                    @foreach($field->data_type === 'select' ? array_map(fn ($option) => [$option, $option], $field->allowed_options) : [['1', __('assets.yes')], ['0', __('assets.no')]] as [$key, $label])
                                        <option value="{{ $key }}" @selected($key === $selected)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input name="{{ $name }}" id="{{ $id }}" class="ui-select w-full" type="{{ $field->data_type === 'date' ? 'date' : 'text' }}" value="{{ $selected }}"
                                    @if($field->data_type === 'decimal') inputmode="decimal" @elseif($field->data_type === 'integer') inputmode="numeric" @endif
                                    @required($field->is_required) @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
                            @endif
                            @if($error)<p id="{{ $id }}-error" class="text-sm text-[var(--error)]">{{ $error }}</p>@endif
                        </div>
                    @endforeach
                </x-ui.panel>
            @endif
            <x-ui.panel class="p-4 sm:p-6">
                <details @if($errors->hasAny(['photo', 'invoice', 'serial_number', 'invoice_number', 'brand_id', 'vendor_id', 'warranty_ends_on'])) open @endif>
                    <summary class="min-h-11 cursor-pointer py-2 font-semibold">{{ __('assets.optional_details') }}</summary>
                    <div class="mt-2 grid gap-4 sm:grid-cols-2">
                        <x-ui.field name="serial_number" :label="__('assets.fields.serial_number')" :value="$value('serial_number')" maxlength="255" />
                        <x-ui.field name="invoice_number" :label="__('assets.fields.invoice_number')" :value="$value('invoice_number')" maxlength="255" />
                        @foreach(['brand_id' => $brands, 'vendor_id' => $vendors] as $key => $options)
                            <x-ui.select-field :name="$key" :label="__('assets.fields.'.$key)">
                                <option value="">{{ __('assets.none') }}</option>
                                @foreach($options as $option)
                                    <option value="{{ $option->id }}" @selected($value($key) === (string) $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </x-ui.select-field>
                        @endforeach
                        <x-ui.field name="warranty_ends_on" type="date" :label="__('assets.fields.warranty_ends_on')" :value="$value('warranty_ends_on')" />
                        <div class="min-w-0 sm:col-span-2">
                            <x-ui.field name="photo" type="file" :label="__('assets.photo')" accept="image/jpeg,image/png,image/webp" class="py-2" />
                            <p class="mt-1 text-sm">{{ __('assets.photo_help') }}</p>
                        </div>
                        <div class="min-w-0 sm:col-span-2">
                            <x-ui.field name="invoice" type="file" :label="__('assets.invoice')" accept="application/pdf,image/jpeg,image/png" class="py-2" />
                            <p class="mt-1 text-sm">{{ __('assets.document_help') }}</p>
                        </div>
                    </div>
                </details>
            </x-ui.panel>
            <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('assets.save_registration') }}</x-ui.button>
        </form>
    @endif
</x-layouts.account>
