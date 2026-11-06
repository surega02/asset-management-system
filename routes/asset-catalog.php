<?php

use App\Http\Controllers\AssetAttributeController;
use App\Http\Controllers\AssetMasterController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('asset-catalog')->group(function (): void {
    Route::prefix('types/{assetType}/attributes')->name('asset-attributes.')->where(['assetType' => '[0-9]+'])->group(function (): void {
        Route::get('/', [AssetAttributeController::class, 'index'])->name('index');
        Route::get('/new', [AssetAttributeController::class, 'create'])->name('create');
        Route::post('/', [AssetAttributeController::class, 'store'])->name('store');
        Route::get('/{version}', [AssetAttributeController::class, 'edit'])->whereNumber('version')->name('edit');
        Route::put('/{version}', [AssetAttributeController::class, 'update'])->whereNumber('version')->name('update');
        Route::post('/{version}/archive', [AssetAttributeController::class, 'archive'])->whereNumber('version')->name('archive');
    });

    Route::prefix('{section}')->name('asset-catalog.')->group(function (): void {
        Route::get('/', [AssetMasterController::class, 'index'])->name('index');
        Route::get('/new', [AssetMasterController::class, 'create'])->name('create');
        Route::post('/', [AssetMasterController::class, 'store'])->name('store');
        Route::get('/{master}', [AssetMasterController::class, 'show'])->whereNumber('master')->name('show');
        Route::put('/{master}', [AssetMasterController::class, 'update'])->whereNumber('master')->name('update');
        Route::post('/{master}/default', [AssetMasterController::class, 'setDefault'])->whereNumber('master')->name('default');
    });
});
