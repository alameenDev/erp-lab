<?php

use App\Http\Controllers\PatientMobileController;
use Illuminate\Support\Facades\Route;

// Separate paths and throttle prefixes: never reuse staff auth or portal OTP.
Route::prefix('patient-mobile/v1')->group(function () {
    Route::post('exchange', [PatientMobileController::class, 'exchange'])->middleware('throttle:20,1,patient-mobile-exchange');
    Route::middleware('throttle:120,1,patient-mobile-read')->group(function () {
        Route::get('me', [PatientMobileController::class, 'overview']);
        Route::get('reports', [PatientMobileController::class, 'reports']);
        Route::get('reports/{invoice}', [PatientMobileController::class, 'report'])->whereNumber('invoice');
        Route::get('points', [PatientMobileController::class, 'ledger']);
        Route::delete('session', [PatientMobileController::class, 'logout']);
    });
});
Route::prefix('portal/{token}/mobile-link')->middleware('throttle:30,1,patient-mobile-portal')->group(function () {
    Route::get('/', [PatientMobileController::class, 'availability']);
    Route::post('/', [PatientMobileController::class, 'issue']);
    Route::delete('/', [PatientMobileController::class, 'revokePortal']);
});
