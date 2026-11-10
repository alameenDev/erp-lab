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
        if (!$request->is('api/learning-demo/*') && ($token = $request->bearerToken())) {
            $user = PersonalAccessToken::findToken($token)?->tokenable;
            abort_unless($user && $this->ownsUser($user->id, $session), 403, 'هذا الحساب لا ينتمي إلى تجربة التدريب.');
        }
        abort_if($request->is('api/super-admin*', 'api/subscriptions*', 'api/user/register', 'api/user/login', 'api/user/resend_code', 'api/user/verify_code',
            'api/patient-mobile/*', 'api/*/mobile-link*', 'api/user/*password*', 'api/user/*otp*', 'api/user/forgot*', 'api/device/*'), 403, 'هذه العملية غير متاحة في التجربة التعليمية.');
        if ($request->is('api/whatsapp/*', 'api/referral-portal/*/share-report', 'api/*/ai-chat', 'api/*/push/*', 'api/*/otp*', 'api/lab-notifications/campaigns*', 'api/lab-notifications/history/*/retry')) {
            return response()->json(['message' => 'محاكاة تعليمية: لم تُرسل أي رسالة أو إشعار خارجي.', 'simulated' => true], 409);
        }
        // Public report URLs must belong to this browser's synthetic lab too.
        $path = $request->path();
        if (preg_match('#^(?:medical-reports)/(\d+)#', $path, $m)) {
            abort_unless($this->ownsUser(\App\Models\Patient::find($m[1])?->creator_id, $session), 404);
        }
        if (preg_match('#^api/(?:lab-settings|lab-background|lab-margins)/(\d+)#', $path, $m)) abort_unless($this->ownsUser((int)$m[1], $session), 404);
        if (preg_match('#^(?:api/)?portal/([A-Za-z0-9]{48})(?:/|$)#', $path, $m)) {
            $patientId = \App\Models\PortalAccessToken::where('token', $m[1])->value('patient_id_fk');
            abort_unless($this->ownsUser(\App\Models\Patient::find($patientId)?->creator_id, $session), 404);
        }
        if (preg_match('#^(?:api/referral-report|api/invoices/public|api/report-attachments|result|invoice)/(\d+)#', $path, $m)) abort_unless($this->ownsUser(\App\Models\Invoice::find($m[1])?->lab_id_fk, $session), 404);
        return $next($request);
    }
    private function ownsUser(?int $id, DemoSession $session): bool {
        $ids = array_values($session->identities);
        for ($depth = 0; $id && $depth < 8; $depth++) {
            if (in_array($id, $ids, true)) return true;
            $id = \App\Models\User::withTrashed()->find($id)?->creator_id;
        }
        return false;
    }
}
