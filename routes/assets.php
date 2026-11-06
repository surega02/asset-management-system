<?php

use App\Http\Controllers\AssetAttachmentController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetTrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('assets')->name('assets.')->group(function (): void {
    Route::get('/', [AssetController::class, 'index'])->name('index');
    Route::get('/new', [AssetController::class, 'create'])->name('create');
    Route::post('/', [AssetController::class, 'store'])->name('store');
    Route::get('/{asset}', [AssetController::class, 'show'])->whereNumber('asset')->name('show');
    Route::get('/{asset}/tracking', [AssetTrackingController::class, 'show'])->whereNumber('asset')->name('tracking');
    Route::post('/{asset}/reassign', [AssetTrackingController::class, 'reassign'])->whereNumber('asset')->name('reassign');
    Route::post('/{asset}/move', [AssetTrackingController::class, 'move'])->whereNumber('asset')->name('move');
    Route::post('/{asset}/deactivate', [AssetController::class, 'deactivate'])->whereNumber('asset')->name('deactivate');
    Route::post('/{asset}/reactivate', [AssetController::class, 'reactivate'])->whereNumber('asset')->name('reactivate');
    Route::put('/{asset}/state', [AssetController::class, 'updateState'])->whereNumber('asset')->name('state');
    Route::get('/{asset}/attachments/{attachment}', AssetAttachmentController::class)->whereNumber(['asset', 'attachment'])->name('attachments.download');
});
