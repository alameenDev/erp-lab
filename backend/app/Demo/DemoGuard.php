<?php
namespace App\Demo;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
class DemoGuard {
    public function handle(Request $request, Closure $next) {
        if (!config('demo.enabled')) {
            abort_if($request->is('api/learning-demo/*'), 404);
            return $next($request);
        }
        DemoIsolation::assertConfigured();
        if ($request->is('api/learning-demo/start') && $request->isMethod('POST')) return $next($request);
        $secret = $request->header('X-Demo-Session') ?: $request->cookie('digital_lab_demo');
        abort_unless(is_string($secret) && strlen($secret) === 64, 401, 'ابدأ تجربة تعليمية جديدة.');
        $session = DemoSession::where('secret_hash', hash('sha256', $secret))->where('expires_at', '>', now())->first();
        abort_unless($session, 401, 'انتهت التجربة. يمكنك إنشاء تجربة جديدة.');
        $request->attributes->set('demo_session', $session);
        if ($token = $request->bearerToken()) {
            $user = PersonalAccessToken::findToken($token)?->tokenable;
            $ids = array_values($session->identities);
            $belongs = false;
            for ($depth = 0; $user && $depth < 8; $depth++, $user = $user->creator_id ? \App\Models\User::find($user->creator_id) : null) {
                if (in_array($user->id, $ids, true)) { $belongs = true; break; }
            }
            abort_unless($belongs, 403, 'هذا الحساب لا ينتمي إلى تجربة التدريب.');
        }
        abort_if($request->is('api/super-admin*', 'api/subscriptions*', 'api/user/register', 'api/user/login',
            'api/patient-mobile/*', 'api/*/mobile-link*', 'api/user/*password*', 'api/user/*otp*', 'api/user/forgot*', 'api/device/*'), 403, 'هذه العملية غير متاحة في التجربة التعليمية.');
        if ($request->is('api/whatsapp/*', 'api/*/ai-chat', 'api/*/push/*', 'api/*/otp*', 'api/lab-notifications/campaigns*', 'api/lab-notifications/history/*/retry')) {
            return response()->json(['message' => 'محاكاة تعليمية: لم تُرسل أي رسالة أو إشعار خارجي.', 'simulated' => true], 409);
        }
        // Public report URLs must belong to this browser's synthetic lab too.
        $path = $request->path(); $lab = $session->identities['lab'];
        if (preg_match('#^(?:medical-reports)/(\d+)#', $path, $m)) {
            abort_unless(\App\Models\Patient::whereKey($m[1])->where('creator_id', $lab)->exists(), 404);
        }
        if (preg_match('#^api/(?:lab-settings|lab-background|lab-margins)/(\d+)#', $path, $m)) abort_unless((int)$m[1] === $lab, 404);
        if (preg_match('#^(?:api/)?portal/([A-Za-z0-9]{48})(?:/|$)#', $path, $m)) {
            $patientId = \App\Models\PortalAccessToken::where('token', $m[1])->value('patient_id_fk');
            abort_unless(\App\Models\Patient::whereKey($patientId)->where('creator_id', $lab)->exists(), 404);
        }
        if (preg_match('#^(?:api/invoices/public|api/report-attachments|result|invoice)/(\d+)#', $path, $m)) abort_unless(\App\Models\Invoice::whereKey($m[1])->where('lab_id_fk', $lab)->exists(), 404);
        return $next($request);
    }
}
