<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\StructuralAppointmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::prefix('employees')->name('employees.')->group(function (): void {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/new', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/import', [EmployeeImportController::class, 'create'])->name('import.create');
        Route::post('/import', [EmployeeImportController::class, 'store'])->middleware('throttle:10,1')->name('import.store');
        Route::get('/import/template', [EmployeeImportController::class, 'template'])->name('import.template');
        Route::get('/import/{batch}', [EmployeeImportController::class, 'show'])->whereNumber('batch')->name('import.show');
        Route::post('/import/{batch}/process', [EmployeeImportController::class, 'process'])->whereNumber('batch')->name('import.process');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->name('show');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee')->name('update');
        Route::post('/{employee}/transfer', [EmployeeController::class, 'transfer'])->whereNumber('employee')->name('transfer');
    });

    Route::prefix('appointments')->name('appointments.')->group(function (): void {
        Route::get('/', [StructuralAppointmentController::class, 'index'])->name('index');
        Route::get('/new', [StructuralAppointmentController::class, 'create'])->name('create');
        Route::post('/', [StructuralAppointmentController::class, 'store'])->name('store');
        Route::get('/{appointment}/edit', [StructuralAppointmentController::class, 'edit'])->whereNumber('appointment')->name('edit');
        Route::put('/{appointment}', [StructuralAppointmentController::class, 'update'])->whereNumber('appointment')->name('update');
    });
});
