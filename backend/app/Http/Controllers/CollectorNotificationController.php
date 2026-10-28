<?php
namespace App\Http\Controllers;
use App\Models\{CollectorPushSubscription, CollectorNotification};
use App\Services\{CollectorAccess, PortalPushTransport};
use App\Rules\BrowserPushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Cache};
class CollectorNotificationController extends Controller
{
    private function collector(Request $r) { app(CollectorAccess::class)->lab($r->user()); return $r->user(); }
    public function config(Request $r) { $this->collector($r);$keys=app(PortalPushTransport::class)->keys();return response()->json(['enabled'=>(bool)$keys,'public_key'=>$keys['publicKey']??null,'worker_last_seen'=>Cache::get('collector_push_worker_at')]); }
    private function visible(Request $r) { $u=$this->collector($r);return CollectorNotification::where('collector_id',$u->id)->where('lab_id',app(CollectorAccess::class)->lab($u)->id); }
    public function index(Request $r) { $q=$this->visible($r);return response()->json(['unread'=>(clone $q)->whereNull('read_at')->count(),'data'=>$q->latest('created_at')->limit(100)->get()])->header('Cache-Control','private, no-store'); }
    public function read(Request $r,string $id) { $this->visible($r)->findOrFail($id)->update(['read_at'=>now()]);return response()->json(['ok'=>true]); }
    private function device(Request $r)
    {
        $collector = $this->collector($r);
        $r->validate(['endpoint' => 'required|string|max:2048']);
        return CollectorPushSubscription::where('collector_id', $collector->id)->where('endpoint_hash', hash('sha256', $r->endpoint))->first();
    }
    private function state(?CollectorPushSubscription $sub): array
    {
        return ['subscribed' => (bool) $sub];
    }
    public function status(Request $r)
    {
        $sub = $this->device($r);
        if ($sub) $sub->update(['auth_token_id' => ($r->user()->currentAccessToken()->id ?? null)]);
        return response()->json($this->state($sub))->header('Cache-Control', 'private, no-store');
    }
    public function subscribe(Request $r)
    {
        $collector = $this->collector($r);
        $data = $r->validate(['subscription' => ['required', new BrowserPushSubscription]]);
        abort_unless(app(PortalPushTransport::class)->keys(), 503, 'خدمة الإشعارات تحتاج إلى تفعيل من مدير النظام.');
        $sub = DB::transaction(function () use ($r, $collector, $data) {
            $hash = hash('sha256', $data['subscription']['endpoint']);
            $sub = CollectorPushSubscription::where('endpoint_hash', $hash)->lockForUpdate()->first();
            // Explicit opt-in on a shared browser transfers this endpoint, never its old deliveries.
            if ($sub && (int) $sub->collector_id !== (int) $collector->id) { $sub->delete(); $sub = null; }
            $sub ??= new CollectorPushSubscription;
            $sub->fill(['collector_id' => $collector->id, 'auth_token_id' => ($collector->currentAccessToken()->id ?? null),
                'endpoint_hash' => $hash, 'subscription' => ['endpoint' => $data['subscription']['endpoint'], 'keys' => $data['subscription']['keys']]])->save();
            return $sub;
        });
        return response()->json($this->state($sub));
    }
    public function unsubscribe(Request $r)
    {
        $this->device($r)?->delete();
        return response()->json(['ok' => true]);
    }
}
