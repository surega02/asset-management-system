<?php

use App\Http\Controllers\AssetLabelController;
use App\Http\Controllers\AssetQrController;
use Illuminate\Support\Facades\Route;

Route::get('/scan', [AssetQrController::class, 'scan'])->name('assets.scan');
Route::get('/q/{token}', [AssetQrController::class, 'publicResult'])->where('token', AssetQrController::TOKEN_PATTERN)
    ->middleware('throttle:90,1')->name('assets.qr.public');

Route::middleware('auth')->group(function (): void {
    Route::get('/q/{token}/detail', [AssetQrController::class, 'detail'])->where('token', AssetQrController::TOKEN_PATTERN)->name('assets.qr.detail');
    Route::get('/assets/find', [AssetQrController::class, 'findByCode'])->name('assets.find');
    Route::prefix('assets/labels')->name('assets.labels.')->group(function (): void {
        Route::get('/', [AssetLabelController::class, 'create'])->name('create');
        Route::post('/', [AssetLabelController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::get('/{job}', [AssetLabelController::class, 'preview'])->whereNumber('job')->name('preview');
    });
    Route::get('/assets/{asset}/label', [AssetLabelController::class, 'single'])->whereNumber('asset')->name('assets.labels.single');
});
