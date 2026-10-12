<?php

use App\Http\Controllers\LabBannerController;
use App\Http\Middleware\{AuditActivity, RestrictReferralPortal};
use Illuminate\Support\Facades\Route;

Route::prefix('api/lab-banners')->middleware(['api', 'auth:sanctum', RestrictReferralPortal::class, AuditActivity::class])->group(function () {
    Route::get('active', [LabBannerController::class, 'active'])->middleware('throttle:30,1');
    Route::get('labs', [LabBannerController::class, 'labs']);
    Route::get('/', [LabBannerController::class, 'index']);
    Route::post('/', [LabBannerController::class, 'store'])->middleware('throttle:10,1');
    // Multipart POST is used deliberately, avoiding PHP PUT file parsing differences.
    Route::post('{id}', [LabBannerController::class, 'update'])->whereNumber('id')->middleware('throttle:20,1');
    Route::delete('{id}', [LabBannerController::class, 'destroy'])->whereNumber('id');
});
