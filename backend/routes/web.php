<?php

use App\Http\Controllers\SocialPreviewController;
use App\Http\Controllers\PatientPortalShellController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/portal/{token}', PatientPortalShellController::class)
    ->where('token', '[A-Za-z0-9]{48}');

// Social preview routes for bots (WhatsApp, Facebook, Twitter, etc.)
// Normal browsers get redirected to the SPA frontend
Route::get('/result/{invoiceId}', [SocialPreviewController::class, 'resultPreview'])
    ->where('invoiceId', '[0-9]+');
Route::get('/invoice/{invoiceId}', [SocialPreviewController::class, 'invoicePreview'])
    ->where('invoiceId', '[0-9]+');
