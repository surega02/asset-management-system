<?php

use App\Livewire\Dashboard;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;

Route::view('/', 'welcome')->name('home');

Route::post('/language', function (Request $request): RedirectResponse {
    Locale::set($request->input('locale'));

    return to_route($request->user() ? 'workspace' : 'home');
})->name('language');

require __DIR__.'/identity.php';
require __DIR__.'/organization.php';
require __DIR__.'/people.php';
require __DIR__.'/asset-catalog.php';

Schedule::call(fn () => DB::table('employee_imports')->where('created_at', '<', now()->subDays(90))->delete())
    ->name('prune-employee-imports')->daily();

if (app()->environment('local', 'testing')) {
    Route::livewire('/preview', Dashboard::class)->name('preview.dashboard');
}
