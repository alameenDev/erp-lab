<?php

use App\Http\Controllers\Sync\TransportController;
use App\Http\Middleware\AuthenticateSyncPeer;
use Illuminate\Support\Facades\Route;

Route::prefix('lab-sync/v1')->middleware(['throttle:60,1', AuthenticateSyncPeer::class])->group(function () {
    Route::post('events', [TransportController::class, 'receive']);
    Route::post('acknowledgements', [TransportController::class, 'acknowledge']);
    Route::get('changes', [TransportController::class, 'changes']);
});

Route::get('lab-sync/status', \App\Http\Controllers\Sync\StatusController::class)->middleware('auth:sanctum');
