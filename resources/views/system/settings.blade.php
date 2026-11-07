<x-layouts.account :title="__('system.settings')">
    <x-ui.panel class="grid gap-3 p-4 sm:p-5" aria-labelledby="locale-title">
        <h2 id="locale-title">{{ __('system.default_locale') }}</h2>
        <p>{{ __('system.locale_help') }}</p>
        <form action="{{ route('system.settings.locale') }}" method="post" class="flex flex-wrap items-end gap-3">
            @csrf
            @method('put')
            <x-ui.select-field name="locale" id="default-locale" :label="__('system.default_locale')" required>
                @foreach(\App\Support\Locale::OPTIONS as $code => [, $name])
                    <option value="{{ $code }}" @selected($locale === $code)>{{ $name }}</option>
                @endforeach
            </x-ui.select-field>
            <x-ui.button type="submit" variant="primary">{{ __('system.save') }}</x-ui.button>
        </form>
    </x-ui.panel>
    <section class="grid gap-3" aria-labelledby="template-title">
        <h2 id="template-title">{{ __('system.label_templates') }}</h2>
        <p>{{ __('qr.required_fields') }}</p>
        <x-ui.panel class="divide-y divide-[var(--border)]">
            @foreach($templates as $template)
                <form method="post" action="{{ route('system.settings.label', $template->id) }}" class="grid gap-3 p-4 sm:p-5">
                    @csrf
                    @method('put')
                    <h3 class="font-semibold">{{ __('system.preset.'.$template->name) }}</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.field :id="'width-'.$template->id" name="width_mm" :label="__('system.settings_fields.width_mm')"
                            type="number" min="60" max="120" step="0.01" :value="$template->width_mm" required />
                        <x-ui.field :id="'height-'.$template->id" name="height_mm" :label="__('system.settings_fields.height_mm')"
                            type="number" min="36" max="75" step="0.01" :value="$template->height_mm" required />
                    </div>
                    <fieldset>
                        <legend class="font-semibold">{{ __('qr.fields') }}</legend>
                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                            @foreach(\App\Actions\Assets\PrintAssetLabels::OPTIONAL_FIELDS as $field)
                                <label class="flex min-h-11 items-center gap-2">
                                    <input class="size-5" type="checkbox" name="allowed_fields[]" value="{{ $field }}" @checked(in_array($field, $template->allowed_fields, true))>
                                    {{ __('qr.'.$field) }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <x-ui.button class="justify-self-start" type="submit">{{ __('system.save_template') }}</x-ui.button>
                </form>
            @endforeach
        </x-ui.panel>
    </section>
    <x-ui.panel class="grid gap-3 p-4 sm:p-5" aria-labelledby="queue-title">
        <h2 id="queue-title">{{ __('system.queue') }}</h2>
        @if($health)
            <p>{{ __('system.queue_observed', ['time' => $health['observed_at']]) }}</p>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div><dt>{{ __('system.queue_overdue') }}</dt><dd class="font-semibold tabular-nums">{{ $health['overdue'] }}</dd></div>
                <div><dt>{{ __('system.queue_failed') }}</dt><dd class="font-semibold tabular-nums">{{ $health['failed'] }}</dd></div>
            </dl>
        @else
            <p>{{ __('system.queue_missing') }}</p>
        @endif
    </x-ui.panel>
</x-layouts.account>
