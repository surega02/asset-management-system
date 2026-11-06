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
    <x-ui.button class="icon-button" aria-label="{{ __('dashboard.theme') }}"
        x-data="{ dark: document.documentElement.dataset.theme === 'dark' }"
        x-bind:aria-pressed="dark"
        @click="dark = !dark; document.documentElement.dataset.theme = dark ? 'dark' : 'light'; try { localStorage.setItem('ams-preview-theme', dark ? 'dark' : 'light') } catch {}">
        <x-ui.icon name="sun" class="theme-sun" />
        <x-ui.icon name="moon" class="theme-moon" />
    </x-ui.button>
</div>
