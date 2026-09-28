<?php
namespace App\Http\Middleware;
use App\Models\Referal;
use Closure;
use Illuminate\Http\Request;
class RestrictReferralPortal {
    public function handle(Request $request, Closure $next) {
        $user = $request->user();
        if ($user && ($user->referral_portal_only || Referal::where('referral_id_fk', $user->id)->exists())) {
            $portal = (int) $user->role_id === 5 ? 'api/doctor-portal/*' : 'api/referral-portal/*';
            abort_unless($request->is($portal, 'api/user/logout'), 403, 'هذا الحساب مخصص لبوابته فقط');
        }
        return $next($request);
    }
}
