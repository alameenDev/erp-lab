<?php

namespace App\Http\Controllers;

use App\Models\{PortalNotification, PortalPushSubscription};
use App\Rules\BrowserPushSubscription;
use App\Services\{PatientPortalAccess, PortalNotifications, PortalPushTransport};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PortalNotificationController extends Controller
{
    public function __construct(private PatientPortalAccess $access) {}

    public function configuration(string $token)
    {
        [, , $lab] = $this->access->resolve($token);
        $enabled = \App\Models\PortalLabNotificationSetting::forLab($lab->id)['enabled'];
        $keys = $enabled ? app(PortalPushTransport::class)->keys() : null;
        return response()->json(['public_key' => $keys['publicKey'] ?? null,
            'lab_notifications_enabled' => $enabled,
            'lab_name' => $lab->labSetting?->lab_display_name ?: $lab->name,
            'manifest_url' => '/api/portal/'.$token.'/manifest.webmanifest']);
    }

    public function manifest(string $token)
    {
        [, , $lab] = $this->access->resolve($token);
        $url = rtrim(config('app.frontend_url', config('app.url')), '/');
        return response()->json([
            'id' => $url.'/portal/'.$token, 'start_url' => $url.'/portal/'.$token, 'scope' => $url.'/portal/',
            'name' => 'بوابة المريض — '.($lab->labSetting?->lab_display_name ?: $lab->name),
            'short_name' => 'بوابة المريض', 'description' => 'نتائجك ومواعيدك ومكافآتك في مكان واحد',
            'display' => 'standalone', 'lang' => 'ar', 'dir' => 'rtl',
            'theme_color' => '#0f766e', 'background_color' => '#f8fafc',
            'icons' => [
                ['src' => $url.'/logo-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $url.'/logo-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function subscribe(Request $request, string $token)
    {
        [$access, $patient, $lab] = $this->access->resolve($token);
        abort_unless(\App\Models\PortalLabNotificationSetting::forLab($lab->id)['enabled'], 409, 'أوقف المختبر إشعارات الجهاز مؤقتاً.');
        abort_unless(app(PortalPushTransport::class)->keys(), 503, 'الإشعارات غير مفعّلة حالياً. يمكنك متابعة تحديثاتك من داخل البوابة.');
        $data = $request->validate(['subscription' => ['required', 'array', new BrowserPushSubscription],
            'results_enabled' => 'required|boolean', 'offers_enabled' => 'required|boolean']);
        $raw = $data['subscription'];
        $subscription = PortalPushSubscription::updateOrCreate([
            'patient_id' => $patient->id, 'endpoint_hash' => hash('sha256', $raw['endpoint']),
        ], ['lab_id' => $lab->id, 'portal_access_token_id' => $access->id,
            'subscription' => ['endpoint' => $raw['endpoint'], 'keys' => ['p256dh' => $raw['keys']['p256dh'], 'auth' => $raw['keys']['auth']]],
            'results_enabled' => $data['results_enabled'], 'offers_enabled' => $data['offers_enabled']]);
        return response()->json(['subscribed' => true, 'results_enabled' => $subscription->results_enabled, 'offers_enabled' => $subscription->offers_enabled]);
    }

    private function subscription(Request $request, string $token): ?PortalPushSubscription
    {
        [, $patient, $lab] = $this->access->resolve($token);
        $data = $request->validate(['endpoint' => 'required|string|max:2048']);
        return PortalPushSubscription::where('patient_id', $patient->id)->where('lab_id', $lab->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))->first();
    }

    public function status(Request $request, string $token)
    {
        $subscription = $this->subscription($request, $token);
        return response()->json(['subscribed' => (bool) $subscription, 'results_enabled' => $subscription?->results_enabled ?? true,
            'offers_enabled' => $subscription?->offers_enabled ?? false]);
    }

    public function unsubscribe(Request $request, string $token)
    {
        $this->subscription($request, $token)?->delete();
        // Do not unsubscribe the shared browser endpoint: another family member
        // may have separately opted in from this same device.
        return response()->json(['subscribed' => false]);
    }

    public function test(Request $request, string $token)
    {
        $subscription = $this->subscription($request, $token);
        abort_unless($subscription, 409, 'فعّل إشعارات هذا الجهاز أولاً');
        abort_unless(\App\Models\PortalLabNotificationSetting::allows($subscription->lab_id, 'test'), 409, 'أوقف المختبر إشعارات الجهاز مؤقتاً.');
        $notice = PortalNotification::create(['patient_id' => $subscription->patient_id, 'lab_id' => $subscription->lab_id,
            'event_key' => hash('sha256', Str::uuid()), 'kind' => 'test', 'title' => 'إشعارات بوابتك جاهزة',
            'body' => 'هذا إشعار تجريبي من بوابة المريض. يمكنك إدارة تفضيلاتك في أي وقت.']);
        app(PortalNotifications::class)->queue($notice, $subscription);
        return response()->json(['queued' => true], 202);
    }

    public function inbox(string $token)
    {
        [, $patient, $lab] = $this->access->resolve($token);
        $query = PortalNotification::where('patient_id', $patient->id)->where('lab_id', $lab->id);
        return response()->json(['unread' => (clone $query)->whereNull('read_at')->count(),
            'items' => $query->latest()->limit(30)->get(['id', 'kind', 'title', 'body', 'created_at', 'read_at'])]);
    }

    public function read(Request $request, string $token)
    {
        [, $patient, $lab] = $this->access->resolve($token);
        $data = $request->validate(['ids' => 'required|array|max:30', 'ids.*' => 'required|uuid']);
        PortalNotification::where('patient_id', $patient->id)->where('lab_id', $lab->id)->whereIn('id', $data['ids'])->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }
}
