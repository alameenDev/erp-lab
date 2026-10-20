<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() || (int) $request->user()->status !== 1) {
            return response()->json(['message' => 'الحساب غير فعال. يرجى التواصل مع مدير المختبر.'], 401);
        }
        return $next($request);
    }
}
