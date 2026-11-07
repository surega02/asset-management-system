<?php

use App\Actions\Identity\UpdatePreferences;
use App\Http\Controllers\AccessCatalogController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TwoFactorSettingsController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:30,1')->name('login.store');
    Route::get('/two-factor-challenge', [SessionController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [SessionController::class, 'verify'])->name('two-factor.verify');

    Route::view('/forgot-password', 'auth.forgot')->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'requestCode'])->middleware('throttle:5,1')->name('password.email');
    Route::view('/reset-password', 'auth.reset')->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/workspace', WorkspaceController::class)->name('workspace');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::post('/preferences/theme', function (Request $request) {
        $data = $request->validate(['theme' => ['required', 'in:light,dark']]);
        UpdatePreferences::save($request->user(), $data);

        return response()->noContent();
    })->name('preferences.theme');

    Route::get('/security', [TwoFactorSettingsController::class, 'show'])->name('security.show');
    Route::prefix('security')->name('security.')->middleware('throttle:8,1')->group(function (): void {
        Route::post('/enroll', [TwoFactorSettingsController::class, 'start'])->name('start');
        Route::post('/confirm', [TwoFactorSettingsController::class, 'confirm'])->name('confirm');
        Route::post('/recovery-codes', [TwoFactorSettingsController::class, 'regenerate'])->name('regenerate');
        Route::delete('/two-factor', [TwoFactorSettingsController::class, 'disable'])->name('disable');
    });

    Route::prefix('admin')->group(function (): void {
        Route::get('/users', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('/users', [AccountController::class, 'store'])->name('accounts.store');
        Route::get('/users/{account}', [AccountController::class, 'show'])->name('accounts.show');
        Route::put('/users/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::post('/users/{account}/password-reset', [AccountController::class, 'reset'])->middleware('throttle:5,1')->name('accounts.reset');
        Route::put('/users/{account}/access', [AccountController::class, 'access'])->name('accounts.access');

        Route::get('/access', [AccessCatalogController::class, 'index'])->name('catalog.index');
        Route::get('/access/roles/new', [AccessCatalogController::class, 'role'])->name('catalog.roles.create');
        Route::post('/access/roles', [AccessCatalogController::class, 'saveRole'])->name('catalog.roles.store');
        Route::get('/access/roles/{role}', [AccessCatalogController::class, 'role'])->name('catalog.roles.edit');
        Route::put('/access/roles/{role}', [AccessCatalogController::class, 'saveRole'])->name('catalog.roles.update');
        Route::delete('/access/roles/{role}', [AccessCatalogController::class, 'deleteRole'])->name('catalog.roles.delete');
        Route::get('/access/permissions/new', [AccessCatalogController::class, 'permission'])->name('catalog.permissions.create');
        Route::post('/access/permissions', [AccessCatalogController::class, 'savePermission'])->name('catalog.permissions.store');
        Route::get('/access/permissions/{permission}', [AccessCatalogController::class, 'permission'])->name('catalog.permissions.edit');
        Route::put('/access/permissions/{permission}', [AccessCatalogController::class, 'savePermission'])->name('catalog.permissions.update');
        Route::delete('/access/permissions/{permission}', [AccessCatalogController::class, 'deletePermission'])->name('catalog.permissions.delete');
    });
});
