<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SystemSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->name('system.')->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->whereNumber('id')->name('notifications.read');
    Route::get('/audit', AuditController::class)->name('audit');
    Route::get('/admin/settings', [SystemSettingsController::class, 'show'])->name('settings');
    Route::put('/admin/settings/locale', [SystemSettingsController::class, 'locale'])->name('settings.locale');
    Route::put('/admin/settings/labels/{template}', [SystemSettingsController::class, 'label'])->whereNumber('template')->name('settings.label');
});
