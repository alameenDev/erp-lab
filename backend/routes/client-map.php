<?php

use App\Http\Controllers\ClientMapLocationController;
use App\Http\Middleware\{AuditActivity, RestrictReferralPortal};
use Illuminate\Support\Facades\Route;

Route::get('api/public/client-labs', [ClientMapLocationController::class, 'publicIndex'])
    ->middleware(['api', 'throttle:60,1']);

Route::prefix('api/super-admin/client-labs')
    ->middleware(['api', 'auth:sanctum', RestrictReferralPortal::class, AuditActivity::class])
    ->group(function () {
        Route::get('/', [ClientMapLocationController::class, 'index']);
        Route::post('/', [ClientMapLocationController::class, 'store'])->middleware('throttle:20,1');
        // Multipart POST is used so PHP handles file uploads consistently.
        Route::post('{id}', [ClientMapLocationController::class, 'update'])->whereNumber('id')->middleware('throttle:30,1');
        Route::delete('{id}', [ClientMapLocationController::class, 'destroy'])->whereNumber('id');
    });
