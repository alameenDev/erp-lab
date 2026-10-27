<?php
namespace App\Http\Controllers;

use App\Models\OwnerPushSubscription;
use App\Rules\BrowserPushSubscription;
use App\Services\{OwnerAccess, OwnerNotifications, PortalPushTransport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;

class OwnerNotificationController extends Controller
{
    private function owner(Request $r)
    {
        abort_unless($r->user()?->owner_portal_only, 403);
        return $r->user();
    }
    public function config(Request $r)
    {
        $this->owner($r); $keys = app(PortalPushTransport::class)->keys();
        return response()->json(['enabled' => (bool) $keys, 'public_key' => $keys['publicKey'] ?? null,
            'timezone' => 'Asia/Baghdad', 'daily_time' => '00:05', 'worker_last_seen' => Cache::get('owner_push_worker_at')])->header('Cache-Control', 'private, no-store');
    }
    public function index(Request $r, OwnerNotifications $service)
    {
        $owner = $this->owner($r);
        $r->validate(['lab_id' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1', 'kind' => 'nullable|in:daily_summary,invoice_amount,invoice_deleted,test']);
        $q = $service->visible($owner);
        if ($r->integer('lab_id')) { app(OwnerAccess::class)->selected($owner, $r->integer('lab_id')); $q->where('lab_id', $r->integer('lab_id')); }
        if ($r->filled('kind')) $q->where('kind', $r->kind);
        $unread = (clone $q)->whereNull('read_at')->count();
        $page = $q->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        return response()->json(['data' => $page->items(), 'unread' => $unread,
            'pagination' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]])->header('Cache-Control', 'private, no-store');
    }
    public function show(Request $r, string $id, OwnerNotifications $service)
    {
        return response()->json($service->visible($this->owner($r))->findOrFail($id))->header('Cache-Control', 'private, no-store');
    }
    public function read(Request $r, string $id, OwnerNotifications $service)
    {
        $notice = $service->visible($this->owner($r))->findOrFail($id);
        if (! $notice->read_at) $notice->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }
    private function device(Request $r)
    {
        $owner = $this->owner($r);
        $r->validate(['endpoint' => 'required|string|max:2048']);
        return OwnerPushSubscription::where('owner_id', $owner->id)->where('endpoint_hash', hash('sha256', $r->endpoint))->first();
    }
    private function state(?OwnerPushSubscription $sub): array
    {
        return ['subscribed' => (bool) $sub, 'invoice_enabled' => $sub?->invoice_enabled ?? true, 'daily_enabled' => $sub?->daily_enabled ?? true];
    }
    public function status(Request $r)
    {
        $sub = $this->device($r);
        if ($sub) $sub->update(['auth_token_id' => $r->user()->currentAccessToken()?->id]);
        return response()->json($this->state($sub))->header('Cache-Control', 'private, no-store');
    }
    public function subscribe(Request $r)
    {
        $owner = $this->owner($r);
        $data = $r->validate(['subscription' => ['required', new BrowserPushSubscription], 'invoice_enabled' => 'required|boolean', 'daily_enabled' => 'required|boolean']);
        abort_unless(app(PortalPushTransport::class)->keys(), 503, 'خدمة الإشعارات تحتاج إلى تفعيل من مدير النظام.');
        $sub = DB::transaction(function () use ($r, $owner, $data) {
            $hash = hash('sha256', $data['subscription']['endpoint']);
            $sub = OwnerPushSubscription::where('endpoint_hash', $hash)->lockForUpdate()->first();
            // Explicit opt-in on a shared browser transfers this endpoint, never its old deliveries.
            if ($sub && (int) $sub->owner_id !== (int) $owner->id) { $sub->delete(); $sub = null; }
            $sub ??= new OwnerPushSubscription;
            $sub->fill(['owner_id' => $owner->id, 'auth_token_id' => $owner->currentAccessToken()?->id,
                'endpoint_hash' => $hash, 'subscription' => ['endpoint' => $data['subscription']['endpoint'], 'keys' => $data['subscription']['keys']],
                'invoice_enabled' => $data['invoice_enabled'], 'daily_enabled' => $data['daily_enabled']])->save();
            return $sub;
        });
        return response()->json($this->state($sub));
    }
    public function unsubscribe(Request $r)
    {
        $this->device($r)?->delete();
        return response()->json(['ok' => true]);
    }
    public function test(Request $r, OwnerNotifications $service)
    {
        $sub = $this->device($r); abort_unless($sub, 422, 'فعّل إشعارات هذا الجهاز أولاً.');
        abort_unless(app(PortalPushTransport::class)->keys(), 503, 'خدمة الإشعارات غير مفعلة.');
        $notice = $service->create($r->user(), null, 'test:'.Str::uuid(), 'test', 'إشعارات المالك جاهزة', 'هذه رسالة تجريبية من بوابة المالك.', [], $sub->id);
        return response()->json(['id' => $notice->id, 'message' => 'أُضيف الإشعار إلى قائمة الإرسال. قد يستغرق وصوله دقيقة.'], 202);
    }
}
