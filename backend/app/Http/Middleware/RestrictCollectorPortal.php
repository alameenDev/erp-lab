<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class RestrictCollectorPortal
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->collector_portal_only) {
            abort_unless($request->is('api/collector-portal/*','api/user/access','api/user/logout'),403,'هذا الحساب مخصص لتطبيق ساحب العينات.');
            if ($request->is('api/user/logout')) return $next($request);
            $lab = app(\App\Services\CollectorAccess::class)->lab($request->user());
            if (!$request->is('api/user/logout')) abort_unless(app(\App\Services\CollectorAccess::class)->activeSubscription($lab),403,'اشتراك المختبر غير فعال.');
        }
        return $next($request);
    }
}
