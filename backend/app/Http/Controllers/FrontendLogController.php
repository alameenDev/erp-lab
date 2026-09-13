<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FrontendLogController extends Controller
{
    /**
     * Log frontend errors
     */
    public function error(Request $request)
    {
        Log::channel('frontend')->error($request->message ?? 'Unknown error', [
            'type' => 'error',
            'url' => $request->url,
            'user_id' => auth()->user()?->id,
            'user_email' => auth()->user()?->email,
            'stack' => $request->stack,
            'source' => $request->source,
            'line' => $request->line,
            'column' => $request->column,
            'component' => $request->component,
            'info' => $request->info,
            'user_agent' => $request->header('User-Agent'),
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json(['status' => 'logged']);
    }

    /**
     * Log frontend warnings
     */
    public function warning(Request $request)
    {
        Log::channel('frontend')->warning($request->message ?? 'Unknown warning', [
            'type' => 'warning',
            'url' => $request->url,
            'user_id' => auth()->user()?->id,
            'user_email' => auth()->user()?->email,
            'data' => $request->data,
            'component' => $request->component,
            'user_agent' => $request->header('User-Agent'),
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json(['status' => 'logged']);
    }

    /**
     * Log frontend info
     */
    public function info(Request $request)
    {
        Log::channel('frontend')->info($request->message ?? 'Info', [
            'type' => 'info',
            'url' => $request->url,
            'user_id' => auth()->user()?->id,
            'data' => $request->data,
            'component' => $request->component,
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json(['status' => 'logged']);
    }

    /**
     * Log frontend debug
     */
    public function debug(Request $request)
    {
        Log::channel('frontend')->debug($request->message ?? 'Debug', [
            'type' => 'debug',
            'url' => $request->url,
            'user_id' => auth()->user()?->id,
            'data' => $request->data,
            'component' => $request->component,
            'timestamp' => now()->toISOString(),
        ]);

        return response()->json(['status' => 'logged']);
    }
}
