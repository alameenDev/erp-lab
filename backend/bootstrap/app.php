<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ASTM hashes and non-numeric result strings must retain their exact bytes.
        $middleware->trimStrings(except: [
            fn (\Illuminate\Http\Request $request) => $request->is('api/device/bridge/results'),
        ]);
        $middleware->convertEmptyStringsToNull(except: [
            fn (\Illuminate\Http\Request $request) => $request->is('api/device/bridge/results'),
        ]);
        $middleware->append([
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\CorsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
