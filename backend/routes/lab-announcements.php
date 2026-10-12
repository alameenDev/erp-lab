<?php

use App\Http\Controllers\LabAnnouncementController;
use App\Http\Middleware\AuditActivity;
use App\Http\Middleware\RestrictReferralPortal;
use Illuminate\Support\Facades\Route;

Route::prefix('api/lab-announcements')->middleware(['api', 'auth:sanctum', RestrictReferralPortal::class, AuditActivity::class])->group(function () {
    Route::get('labs', [LabAnnouncementController::class, 'labs']);
    Route::post('/', [LabAnnouncementController::class, 'send'])->middleware('throttle:15,1');
    Route::get('history', [LabAnnouncementController::class, 'history']);
    Route::post('{id}/cancel', [LabAnnouncementController::class, 'cancel'])->whereNumber('id');
    Route::get('pending', [LabAnnouncementController::class, 'pending'])->middleware('throttle:120,1');
    Route::get('inbox', [LabAnnouncementController::class, 'inbox']);
    Route::post('{id}/read', [LabAnnouncementController::class, 'read'])->whereNumber('id');
});
