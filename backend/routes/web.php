<?php

use App\Http\Controllers\SocialPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Social preview routes for bots (WhatsApp, Facebook, Twitter, etc.)
// Normal browsers get redirected to the SPA frontend
Route::get('/result/{invoiceId}', [SocialPreviewController::class, 'resultPreview'])
    ->where('invoiceId', '[0-9]+');
Route::get('/invoice/{invoiceId}', [SocialPreviewController::class, 'invoicePreview'])
    ->where('invoiceId', '[0-9]+');
