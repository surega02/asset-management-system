<?php

use App\Http\Controllers\AssetImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('assets/import')->name('assets.import.')->group(function (): void {
    Route::get('/', [AssetImportController::class, 'create'])->name('create');
    Route::post('/', [AssetImportController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('/template/{format}', [AssetImportController::class, 'template'])->whereIn('format', ['csv', 'xlsx'])->name('template');
    Route::get('/references', [AssetImportController::class, 'references'])->name('references');
    Route::get('/{batch}', [AssetImportController::class, 'show'])->whereNumber('batch')->name('show');
    Route::post('/{batch}/choices', [AssetImportController::class, 'decide'])->whereNumber('batch')->name('decide');
    Route::post('/{batch}/rows/{row}/retry', [AssetImportController::class, 'retry'])->whereNumber(['batch', 'row'])->name('retry');
    Route::get('/{batch}/errors', [AssetImportController::class, 'errors'])->whereNumber('batch')->name('errors');
});
