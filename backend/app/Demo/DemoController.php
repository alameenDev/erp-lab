<?php
namespace App\Demo;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class DemoController extends Controller {
    public function start(Request $request) {
        DemoIsolation::assertConfigured();
        abort_if(DemoSession::where('expires_at', '>', now())->count() >= config('demo.max_sessions'), 429, 'التجربة مشغولة حالياً. يرجى المحاولة لاحقاً.');
        $secret = Str::random(64);
        $session = DB::transaction(fn () => DemoSession::create([
            ...app(DemoFixtures::class)->create(), 'secret_hash' => hash('sha256', $secret), 'expires_at' => now()->addDay(),
        ]));
        return $this->response($session, 'lab', $secret);
    }
    public function resume(Request $request) {
        $data = $request->validate(['identity' => 'sometimes|in:lab,owner,collector,doctor,referral']);
        return $this->response($request->attributes->get('demo_session'), $data['identity'] ?? 'lab');
    }
    public function reset(Request $request) {
        $session = $request->attributes->get('demo_session');
        return DB::transaction(function () use ($session, $request) {
            $response = $this->start($request);
            foreach ($session->identities as $id) User::find($id)?->tokens()->delete();
            $session->update(['expires_at' => now()]);
            return $response;
        });
    }
    private function response(DemoSession $session, string $identity, ?string $secret = null) {
        $user = User::findOrFail($session->identities[$identity]);
        $user->load(['roles.permissions', 'permissions']);
        $user->is_referral_partner = in_array($identity, ['doctor','referral'], true);
        $user->is_doctor_portal = $identity === 'doctor';
        $user->tokens()->where('name', 'learning-demo')->delete();
        $token = $user->createToken('learning-demo', ['*'], $session->expires_at)->plainTextToken;
        $response = response()->json(['token' => $token, 'user' => $user, 'permissions' => $user->getAllPermissions()->pluck('name')->values(), 'secret' => $secret, 'examples' => $session->examples, 'expires_at' => $session->expires_at, 'identity' => $identity]);
        if ($secret) $response->cookie('digital_lab_demo', $secret, 1440, '/demo', null, true, true, false, 'Strict');
        return $response;
    }
}
