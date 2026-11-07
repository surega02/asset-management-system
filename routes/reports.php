<?php

use App\Http\Controllers\AssetReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function (): void {
    Route::get('/{mode?}', [AssetReportController::class, 'show'])->whereIn('mode', ['units', 'groups', 'events'])->name('show');
    Route::get('/{mode}/export/{format}', [AssetReportController::class, 'export'])
        ->whereIn('mode', ['units', 'groups', 'events'])->whereIn('format', ['csv', 'xlsx'])
        ->middleware('throttle:20,1')->name('export');
});
