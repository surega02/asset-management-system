<?php

use App\Http\Controllers\OrganizationMasterController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('masters/{type}')->name('masters.')->group(function (): void {
    Route::get('/', [OrganizationMasterController::class, 'index'])->name('index');
    Route::get('/new', [OrganizationMasterController::class, 'create'])->name('create');
    Route::post('/', [OrganizationMasterController::class, 'store'])->name('store');
    Route::get('/{master}', [OrganizationMasterController::class, 'edit'])->whereNumber('master')->name('edit');
    Route::put('/{master}', [OrganizationMasterController::class, 'update'])->whereNumber('master')->name('update');
});
