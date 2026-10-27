<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictOwnerPortal
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->owner_portal_only) {
            abort_unless($request->is('api/owner-portal/*', 'api/user/access', 'api/user/logout'), 403, 'هذا الحساب مخصص لبوابة المالك.');
        }
        return $next($request);
    }
}
