<?php

use App\Livewire\Dashboard;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('/language', function (Request $request): RedirectResponse {
    Locale::set($request->input('locale'));

    return to_route('home');
})->name('language');

if (app()->environment('local', 'testing')) {
    Route::livewire('/preview', Dashboard::class)->name('preview.dashboard');
}
