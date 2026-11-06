@props(['live' => false])

<div class="flex shrink-0 flex-wrap items-center gap-2">
    @if($live)
        <div>
    @else
        <form action="{{ route('language') }}" method="post" class="flex flex-wrap items-center gap-2">
        @csrf
    @endif
        <label class="sr-only" for="locale">{{ __('dashboard.locale') }}</label>
        <select class="ui-select language-select" id="locale" name="locale"
            @if($live) wire:change="changeLocale($event.target.value)" wire:loading.attr="disabled" wire:target="changeLocale" @endif>
            @foreach(\App\Support\Locale::OPTIONS as $locale => [$shortLabel, $fullLabel])
                <option value="{{ $locale }}" lang="{{ $locale }}" aria-label="{{ $fullLabel }}"
                    @selected(app()->getLocale() === $locale)>{{ $shortLabel }}</option>
            @endforeach
        </select>
    @if($live)
        </div>
    @else
            <x-ui.button type="submit">{{ __('dashboard.apply_locale') }}</x-ui.button>
        </form>
    @endif
    <div x-data="{ dark: document.documentElement.dataset.theme === 'dark', saving: false, failed: false }"
        data-theme-url="{{ auth()->check() ? route('preferences.theme') : '' }}">
        <x-ui.button class="icon-button" aria-label="{{ __('dashboard.theme') }}"
            x-bind:aria-pressed="dark" x-bind:disabled="saving"
            @click="
                const next = dark ? 'light' : 'dark';
                failed = false;
                saving = true;
                try {
                    if ($root.dataset.themeUrl) {
                        const result = await fetch($root.dataset.themeUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                            },
                            body: JSON.stringify({ theme: next })
                        });
                        if (result.status !== 204) throw new Error();
                    } else {
                        localStorage.setItem('ams-preview-theme', next);
                    }
                    dark = !dark;
                    document.documentElement.dataset.theme = next;
                } catch {
                    failed = true;
                } finally {
                    saving = false;
                }
            ">
            <x-ui.icon name="sun" class="theme-sun" />
            <x-ui.icon name="moon" class="theme-moon" />
        </x-ui.button>
        <span x-cloak x-show="failed" role="status" class="block text-sm">{{ __('iam.theme_failed') }}</span>
    </div>
</div>
