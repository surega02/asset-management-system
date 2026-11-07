<?php

use App\Actions\Imports\AssetImportBatches;
use App\Livewire\Dashboard;
use App\Support\Locale;
use App\Support\QueueHealth;
use App\Support\SystemNotifications;
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
require __DIR__.'/assets.php';
require __DIR__.'/asset-import.php';
require __DIR__.'/qr.php';
require __DIR__.'/reports.php';
require __DIR__.'/system.php';

Schedule::call(SystemNotifications::remindActing(...))->name('remind-acting-expiry')->hourly();
Schedule::call(fn () => app(SystemNotifications::class)->publish())->name('publish-system-notifications')->everyMinute();
Schedule::call(QueueHealth::check(...))->name('check-database-queue')->everyMinute();

Schedule::call(fn () => DB::table('employee_imports')->where('created_at', '<', now()->subDays(90))->delete())
    ->name('prune-employee-imports')->daily();
Schedule::call(fn () => app(AssetImportBatches::class)->resumeDue())->name('resume-asset-imports')->everyMinute();
Schedule::call(fn () => DB::table('import_batches')->where('created_at', '<=', now()->subDays(90))->delete())
    ->name('prune-asset-imports')->daily();

if (app()->environment('local', 'testing')) {
    Route::livewire('/preview', Dashboard::class)->name('preview.dashboard');
}
