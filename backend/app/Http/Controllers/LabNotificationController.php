<?php
namespace App\Http\Controllers;

use App\Models\{PortalLabNotificationSetting, PortalNotification, PortalNotificationCampaign, PortalPushDelivery, User};
use App\Services\{PortalCampaigns, PortalPushTransport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Validation\Rule;

class LabNotificationController extends Controller
{
    private function lab(Request $request): User
    {
        $actor = $request->user();
        abort_unless(in_array((int) $actor->role_id, [1, 2], true), 403, 'إدارة الإشعارات متاحة لصاحب المختبر فقط.');
        $request->validate(['lab_id' => 'nullable|integer|min:1']);
        if ((int) $actor->role_id === 2) {
            abort_if($request->filled('lab_id') && (int) $request->lab_id !== (int) $actor->id, 403);
            return $actor;
        }
        abort_unless($request->filled('lab_id'), 422, 'اختر المختبر أولاً.');
        return User::where('role_id', 2)->findOrFail($request->lab_id);
    }

    public function labs(Request $request)
    {
        abort_unless((int) $request->user()->role_id === 1, 403);
        return response()->json(User::where('role_id', 2)->orderBy('name')->get(['id', 'name']));
    }

    public function show(Request $request)
    {
        $lab = $this->lab($request);
        $audience = app(PortalCampaigns::class);
        $lastRun = Cache::get('portal_push_last_run_at');
        $delivery = PortalPushDelivery::query()->join('portal_notifications as n', 'n.id', '=', 'portal_push_deliveries.notification_id')->where('n.lab_id', $lab->id);
        return response()->json([
            'lab' => ['id' => $lab->id, 'name' => $lab->labSetting?->lab_display_name ?: $lab->name],
            'settings' => PortalLabNotificationSetting::forLab($lab->id), 'defaults' => PortalLabNotificationSetting::defaults(),
            'health' => ['push_ready' => (bool) app(PortalPushTransport::class)->keys(), 'last_worker_run' => $lastRun,
                'worker_recent' => $lastRun && \Carbon\Carbon::parse($lastRun)->gt(now()->subMinutes(3))],
            'stats' => [
                'patients' => $audience->audience($lab->id, 'test')->distinct()->count('s.patient_id'),
                'subscriptions' => $audience->audience($lab->id, 'test')->count(),
                'results' => $audience->audience($lab->id, 'result')->distinct()->count('s.patient_id'),
                'offers' => $audience->audience($lab->id, 'offer')->distinct()->count('s.patient_id'),
                'queued' => (clone $delivery)->whereIn('portal_push_deliveries.status', ['queued', 'processing'])->count(),
                'accepted' => (clone $delivery)->where('portal_push_deliveries.status', 'accepted')->count(),
                'failed' => (clone $delivery)->where('portal_push_deliveries.status', 'failed')->count(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $lab = $this->lab($request);
        $data = $request->validate(['enabled' => 'required|boolean', 'results_enabled' => 'required|boolean',
            'announcements_enabled' => 'required|boolean', 'result_title' => 'required|string|max:120', 'result_body' => 'required|string|max:500']);
        $setting = PortalLabNotificationSetting::updateOrCreate(['lab_id_fk' => $lab->id], $data);
        return response()->json(['settings' => $setting->only(array_keys(PortalLabNotificationSetting::defaults()))]);
    }

    public function patients(Request $request)
    {
        $lab = $this->lab($request);
        $data = $request->validate(['q' => 'nullable|string|max:100', 'kind' => ['required', Rule::in(['offer', 'test'])]]);
        $query = app(PortalCampaigns::class)->audience($lab->id, $data['kind']);
        if (!empty($data['q'])) {
            $search = '%'.$data['q'].'%';
            $query->where(fn ($q) => $q->where('u.name', 'like', $search)->orWhere('p.code', 'like', $search));
        }
        return response()->json($query->select('p.id', 'p.code', 'u.name')->selectRaw('COUNT(*) as devices')
            ->groupBy('p.id', 'p.code', 'u.name')->orderBy('u.name')->limit(30)->get());
    }

    public function send(Request $request)
    {
        $lab = $this->lab($request);
        $data = $request->validate([
            'request_id' => 'required|uuid', 'kind' => ['required', Rule::in(['offer', 'test'])],
            'audience' => ['required', Rule::in(['all', 'patient'])], 'patient_id' => 'nullable|required_if:audience,patient|integer|min:1',
            'title' => 'required|string|max:120', 'body' => 'required|string|max:500',
        ]);
        abort_if($data['kind'] === 'test' && $data['audience'] !== 'patient', 422, 'اختر مريضاً واحداً للإشعار التجريبي.');
        $data['patient_id'] = $data['audience'] === 'patient' ? (int) $data['patient_id'] : null;
        $hash = hash('sha256', json_encode(array_intersect_key($data, array_flip(['kind', 'audience', 'patient_id', 'title', 'body']))));
        $campaign = DB::transaction(function () use ($lab, $data, $hash, $request) {
            User::whereKey($lab->id)->lockForUpdate()->firstOrFail();
            $existing = PortalNotificationCampaign::where('lab_id_fk', $lab->id)->where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'طلب الإرسال مستخدم لرسالة مختلفة. حدّث الصفحة وحاول مجدداً.');
                return $existing;
            }
            abort_unless(app(PortalPushTransport::class)->keys(), 409, 'خدمة إشعارات الجهاز غير مجهزة حالياً.');
            abort_unless(PortalLabNotificationSetting::allows($lab->id, $data['kind']), 409, 'هذا النوع من الإشعارات متوقف في إعدادات المختبر.');
            $query = app(PortalCampaigns::class)->audience($lab->id, $data['kind']);
            if ($data['patient_id']) $query->where('s.patient_id', $data['patient_id']);
            $count = (clone $query)->distinct()->count('s.patient_id');
            abort_unless($count, 422, 'لا يوجد مشتركون مؤهلون لهذا الاختيار. يجب تفعيل إشعارات الجهاز والسماح بنوع الرسالة أولاً.');
            return PortalNotificationCampaign::create([
                'lab_id_fk' => $lab->id, 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name,
                'request_id' => $data['request_id'], 'payload_hash' => $hash, 'kind' => $data['kind'], 'audience' => $data['audience'],
                'patient_id' => $data['patient_id'], 'title' => $data['title'], 'body' => $data['body'],
                'audience_count' => $count, 'max_patient_id' => (clone $query)->max('s.patient_id'),
            ]);
        });
        return response()->json(['campaign' => $campaign, 'message' => 'أُضيف الطلب لطابور الإرسال. ستظهر المحاولات في السجل.'], 202);
    }

    public function campaigns(Request $request)
    {
        $lab = $this->lab($request);
        return response()->json(PortalNotificationCampaign::where('lab_id_fk', $lab->id)->latest()->limit(15)->get());
    }

    public function cancel(Request $request, string $id)
    {
        $lab = $this->lab($request);
        DB::transaction(function () use ($lab, $id) {
            $campaign = PortalNotificationCampaign::where('lab_id_fk', $lab->id)->lockForUpdate()->findOrFail($id);
            $campaign->update(['status' => 'cancelled']);
            PortalPushDelivery::whereIn('notification_id', PortalNotification::where('campaign_id', $campaign->id)->select('id'))
                ->where('status', 'queued')->update(['status' => 'cancelled', 'status_reason' => 'campaign_cancelled', 'next_attempt_at' => null]);
        });
        return response()->json(['message' => 'أُوقف ما تبقى من الحملة. لا يمكن سحب إشعار أُرسل أو بدأ إرساله.']);
    }

    public function history(Request $request)
    {
        $lab = $this->lab($request);
        $data = $request->validate(['kind' => ['nullable', Rule::in(['result', 'offer', 'test'])],
            'status' => ['nullable', Rule::in(['queued', 'processing', 'accepted', 'failed', 'cancelled', 'expired'])],
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'page' => 'nullable|integer|min:1']);
        $query = PortalNotification::where('lab_id', $lab->id)->with(['patient.user', 'invoice']);
        if (!empty($data['kind'])) $query->where('kind', $data['kind']);
        if (!empty($data['status'])) $query->whereHas('deliveries', fn ($q) => $q->where('status', $data['status']));
        if (!empty($data['from'])) $query->whereDate('created_at', '>=', $data['from']);
        if (!empty($data['to'])) $query->whereDate('created_at', '<=', $data['to']);
        foreach (['queued', 'processing', 'accepted', 'failed', 'cancelled', 'expired'] as $status) {
            $query->withCount(['deliveries as '.$status.'_count' => fn ($q) => $q->where('status', $status)]);
        }
        $page = $query->latest()->orderByDesc('id')->paginate(15);
        $page->setCollection($page->getCollection()->map(fn ($notice) => [
            'id' => $notice->id, 'kind' => $notice->kind, 'title' => $notice->title, 'body' => $notice->body,
            'patient_name' => $notice->patient?->user?->name ?? 'مريض محذوف', 'patient_code' => $notice->patient?->code,
            'invoice_id' => $notice->invoice_id, 'invoice_barcode' => $notice->invoice?->barcode,
            'created_at' => $notice->created_at, 'read_at' => $notice->read_at,
            'counts' => collect(['queued', 'processing', 'accepted', 'failed', 'cancelled', 'expired'])
                ->mapWithKeys(fn ($status) => [$status => (int) $notice->{$status.'_count'}]),
        ]));
        return response()->json($page);
    }

    public function details(Request $request, string $id)
    {
        $lab = $this->lab($request);
        $notice = PortalNotification::where('lab_id', $lab->id)->findOrFail($id);
        return response()->json($notice->deliveries()->orderBy('id')->get(['id', 'status', 'status_reason', 'attempts', 'next_attempt_at', 'updated_at']));
    }

    public function retry(Request $request, string $id)
    {
        $lab = $this->lab($request);
        $notice = PortalNotification::where('lab_id', $lab->id)->findOrFail($id);
        abort_unless(PortalLabNotificationSetting::allows($lab->id, $notice->kind), 409, 'فعّل هذا النوع من الإشعارات أولاً.');
        abort_if($notice->campaign_id && PortalNotificationCampaign::whereKey($notice->campaign_id)->where('status', 'cancelled')->exists(), 409, 'الحملة متوقفة.');
        $count = DB::transaction(function () use ($notice) {
            $count = $notice->deliveries()->where('status', 'failed')->update(['status' => 'queued', 'status_reason' => null, 'attempts' => 0, 'next_attempt_at' => now()]);
            if ($count) $notice->update(['expires_at' => now()->addDay()]);
            return $count;
        });
        abort_unless($count, 409, 'لا توجد محاولات فاشلة يمكن إعادة جدولتها.');
        return response()->json(['queued' => $count]);
    }
}
