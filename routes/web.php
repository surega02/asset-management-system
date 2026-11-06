<?php

use App\Livewire\Dashboard;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('/language', function (Request $request): RedirectResponse {
    Locale::set($request->input('locale'));

    return to_route($request->user() ? 'workspace' : 'home');
})->name('language');

require __DIR__.'/identity.php';
require __DIR__.'/organization.php';

if (app()->environment('local', 'testing')) {
    Route::livewire('/preview', Dashboard::class)->name('preview.dashboard');
}
