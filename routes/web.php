<?php

use App\Http\Middleware\SetPreviewLocale;
use App\Livewire\Dashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

if (app()->environment('local', 'testing')) {
    Route::livewire('/', Dashboard::class)->name('preview.dashboard');

    Route::post('/preview/language', function (Request $request): RedirectResponse {
        abort_unless(app()->environment('local', 'testing'), 404);

        $locale = $request->validate(['locale' => 'required|in:'.implode(',', SetPreviewLocale::SUPPORTED)])['locale'];
        $request->session()->put('preview_locale', $locale);

        return to_route('preview.dashboard');
    })->name('preview.language');
}
