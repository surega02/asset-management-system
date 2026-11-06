@php
    $value = static function (string $key, mixed $default = ''): string {
        $input = old($key, $default);

        return is_scalar($input) ? (string) $input : '';
    };
@endphp
<x-layouts.account :title="$asset->name">
    <div class="flex flex-wrap items-center gap-3">
        <a class="nav-link" href="{{ route('assets.index') }}">{{ __('assets.back') }}</a>
        <strong>{{ $asset->asset_code }} · {{ __('assets.'.($asset->is_active ? 'active' : 'inactive')) }}</strong>
    </div>
    <dl class="grid gap-x-6 gap-y-3 border-y border-[var(--border)] py-4 sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="font-semibold">{{ __('assets.classification') }}</dt><dd class="wrap-anywhere">{{ $asset->type->category->name }} / {{ $asset->type->name }}</dd></div>
        <div><dt class="font-semibold">{{ __('assets.fields.physical_location_id') }}</dt><dd class="wrap-anywhere">{{ $asset->location->owner->name }} / {{ $asset->location->name }}</dd></div>
        <div><dt class="font-semibold">{{ __('assets.assignee') }}</dt><dd class="wrap-anywhere">{{ $asset->activeAssignment?->assigneeName() ?? __('assets.none') }}</dd></div>
        <div><dt class="font-semibold">{{ __('assets.fields.status_id') }}</dt><dd class="wrap-anywhere">{{ $asset->status->name }}</dd></div>
        <div><dt class="font-semibold">{{ __('assets.fields.condition_id') }}</dt><dd class="wrap-anywhere">{{ $asset->condition->name }}</dd></div>
        <div><dt class="font-semibold">{{ __('assets.fields.purchase_date') }}</dt><dd>{{ $asset->purchase_date->toDateString() }}</dd></div>
        <div><dt class="font-semibold">{{ __('assets.fields.acquisition_cost') }}</dt><dd class="tabular-nums">{{ $asset->acquisition_cost }}</dd></div>
        @foreach(['serial_number' => $asset->serial_number, 'invoice_number' => $asset->invoice_number, 'brand_id' => $asset->brand?->name, 'vendor_id' => $asset->vendor?->name, 'warranty_ends_on' => $asset->warranty_ends_on?->toDateString()] as $key => $detail)
            @if($detail !== null)
                <div><dt class="font-semibold">{{ __('assets.fields.'.$key) }}</dt><dd class="wrap-anywhere">{{ $detail }}</dd></div>
            @endif
        @endforeach
    </dl>
    @if($attributes->isNotEmpty())
        <section class="grid gap-3" aria-labelledby="asset-attributes-heading">
            <h2 id="asset-attributes-heading">{{ __('asset_catalog.attributes') }}</h2>
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                @foreach($attributes as $attribute)
                    @php($stored = json_decode($attribute->value_json, true))
                    <div>
                        <dt class="wrap-anywhere font-semibold">{{ $attribute->label }} <span class="font-normal text-[var(--muted)]">· {{ __('asset_catalog.version', ['version' => $attribute->version]) }}</span></dt>
                        <dd class="wrap-anywhere">{{ $attribute->data_type === 'boolean' ? __('assets.'.($stored ? 'yes' : 'no')) : $stored }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif
    @if($asset->attachments->isNotEmpty())
        <section class="grid gap-3" aria-labelledby="asset-files-heading">
            <h2 id="asset-files-heading">{{ __('assets.attachments') }}</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach($asset->attachments as $file)
                    <li><a class="ui-button" href="{{ route('assets.attachments.download', [$asset, $file]) }}">{{ __('assets.download_'.$file->kind) }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
    @if($lastDeactivation)
        <section class="grid gap-1" aria-labelledby="asset-last-deactivation">
            <h2 id="asset-last-deactivation">{{ __('assets.last_deactivation') }}</h2>
            <p class="wrap-anywhere">{{ $lastDeactivation->reason }}</p>
        </section>
    @endif
    @if($canLifecycle || $canUpdateState)
        <div class="grid max-w-3xl gap-4">
            @if($canUpdateState)
                <x-ui.panel class="p-4 sm:p-6">
                    <details @if($errors->hasAny(['status_id', 'condition_id'])) open @endif>
                        <summary class="min-h-11 cursor-pointer py-2 font-semibold">{{ __('assets.update_state') }}</summary>
                        <form method="post" action="{{ route('assets.state', $asset) }}" class="mt-2 grid gap-4">
                            @csrf
                            @method('PUT')
                            @include('assets.state-fields')
                            <x-ui.field name="reason" id="state-reason" :label="__('people.fields.reason')" :value="$value('reason')" required maxlength="100" />
                            <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('masters.save') }}</x-ui.button>
                        </form>
                    </details>
                </x-ui.panel>
            @endif
            @if($canLifecycle)
                <x-ui.panel class="p-4 sm:p-6">
                    <details @if($errors->any()) open @endif>
                        <summary class="min-h-11 cursor-pointer py-2 font-semibold">{{ __('assets.'.($asset->is_active ? 'deactivate' : 'reactivate')) }}</summary>
                        <form method="post" action="{{ route($asset->is_active ? 'assets.deactivate' : 'assets.reactivate', $asset) }}" enctype="multipart/form-data" class="mt-2 grid gap-4">
                            @csrf
                            <p>{{ __('assets.'.($asset->is_active ? 'deactivate_help' : 'reactivate_help')) }}</p>
                            @if(!$asset->is_active)
                                @include('assets.assignee')
                                @include('assets.state-fields')
                            @endif
                            <x-ui.field name="reason" id="lifecycle-reason" :label="__('people.fields.reason')" :value="$value('reason')" required maxlength="100" />
                            @if($asset->is_active)
                                <x-ui.field name="deactivation" type="file" :label="__('assets.deactivation_file')" accept="application/pdf,image/jpeg,image/png" class="py-2" />
                                <p class="text-sm">{{ __('assets.document_help') }}</p>
                            @endif
                            <x-ui.button type="submit" class="justify-self-start">{{ __('assets.'.($asset->is_active ? 'confirm_deactivate' : 'confirm_reactivate')) }}</x-ui.button>
                        </form>
                    </details>
                </x-ui.panel>
            @endif
        </div>
    @endif
</x-layouts.account>
