@php
    $selected = array_map('strval', array_filter((array) old('asset_ids', []), 'is_scalar'));
    $selectedFields = array_filter((array) old('fields', ['name']), 'is_string');
    $selectedTemplate = is_scalar(old('template_id')) ? (string) old('template_id') : (string) $templates->firstWhere('name', 'standard')?->id;
@endphp
<x-layouts.account :title="__('qr.labels')">
    <div class="flex flex-wrap gap-2">
        <a class="nav-link" href="{{ $single ? route('assets.show', $single) : route('assets.index') }}">{{ $single ? __('tracking.back_asset') : __('assets.back') }}</a>
    </div>
    <p>{{ __('qr.label_help') }}</p>
    @if(!$single)
        <form method="get" action="{{ route('assets.labels.create') }}" class="grid max-w-2xl items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
            <x-ui.field name="q" type="search" :label="__('masters.search')" :value="$search" maxlength="255" />
            <x-ui.button type="submit">{{ __('masters.apply') }}</x-ui.button>
        </form>
    @endif
    @if($rows->isEmpty() || $templates->isEmpty())
        <x-ui.panel class="p-5">{{ __('qr.no_labels') }}</x-ui.panel>
    @else
        <form method="post" action="{{ route('assets.labels.store') }}" class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,.65fr)]">
            @csrf
            <x-ui.panel class="grid gap-3 p-4 sm:p-5">
                <h2>{{ __('qr.selection') }}</h2>
                @if($single)
                    <input type="hidden" name="asset_ids[]" value="{{ $single->id }}">
                    <p class="wrap-anywhere">{{ $single->asset_code }} · {{ $single->name }}</p>
                @else
                    <p>{{ __('qr.selection_help') }}</p>
                    <ul class="grid gap-1">
                        @foreach($rows as $row)
                            <li class="border-b border-[var(--border)]">
                                <label class="flex min-h-11 cursor-pointer items-center gap-3 py-2">
                                    <input class="size-5 shrink-0" type="checkbox" name="asset_ids[]" value="{{ $row->id }}" @checked(in_array((string) $row->id, $selected, true))>
                                    <span class="wrap-anywhere"><strong>{{ $row->asset_code }}</strong> · {{ $row->name }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.panel>
            <x-ui.panel class="grid gap-3 p-4 sm:p-5">
                <h2>{{ __('qr.template') }}</h2>
                <x-ui.select-field name="template_id" :label="__('qr.template')" required>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}" @selected($selectedTemplate === (string) $template->id)>
                            {{ __('qr.size', ['width' => rtrim(rtrim($template->width_mm, '0'), '.'), 'height' => rtrim(rtrim($template->height_mm, '0'), '.')]) }}
                        </option>
                    @endforeach
                </x-ui.select-field>
                <fieldset class="grid gap-1">
                    <legend class="font-semibold">{{ __('qr.fields') }}</legend>
                    <p>{{ __('qr.required_fields') }}</p>
                    @foreach($fields as $field)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3">
                            <input class="size-5" type="checkbox" name="fields[]" value="{{ $field }}" @checked(in_array($field, $selectedFields, true))>
                            <span>{{ __('qr.'.$field) }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('qr.preview') }}</x-ui.button>
            </x-ui.panel>
        </form>
        @if(!$single)
            @include('people.pagination', ['pages' => $rows])
        @endif
    @endif
</x-layouts.account>
