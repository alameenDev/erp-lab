<?php

namespace App\Http\Controllers;

use App\Models\Referal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LabAnnouncementController extends Controller
{
    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && (int) $user->role_id === 1 && !$user->referral_portal_only, 403);
        return $user;
    }

    private function eligibleLabs()
    {
        return User::where('role_id', 2)->where('referral_portal_only', false)->whereDoesntHave('referals');
    }

    private function tenant(Request $request): int
    {
        // Never accept a tenant identity supplied by the browser.
        $request->validate(['lab_id' => 'prohibited']);
        $user = $request->user();
        $seen = [];
        for ($depth = 0; $user && $depth < 20; $depth++) {
            $id = (int) $user->id;
            abort_if(isset($seen[$id]) || $user->referral_portal_only
                || in_array((int) $user->role_id, [1, 3, 5], true)
                || Referal::where('referral_id_fk', $id)->exists(), 403);
            $seen[$id] = true;
            if ((int) $user->role_id === 2) return $id;
            $user = $user->creator_id ? User::find($user->creator_id) : null;
        }
        abort(403, 'تعذر تحديد المختبر المرتبط بالحساب.');
    }

    public function labs(Request $request)
    {
        $this->admin($request);
        return response()->json($this->eligibleLabs()->orderBy('name')->get(['id', 'name']));
    }

    public function send(Request $request)
    {
        $sender = $this->admin($request);
        $data = $request->validate([
            'request_id' => 'required|uuid',
            'title' => 'required|string|max:200', 'body' => 'required|string|max:10000',
            'level' => ['required', Rule::in(['info', 'success', 'warning', 'urgent'])],
            'audience' => ['required', Rule::in(['all', 'selected'])],
            'lab_ids' => 'required_if:audience,selected|prohibited_unless:audience,selected|array|min:1|max:5000',
            'lab_ids.*' => 'required|integer|min:1|distinct',
            'button_label' => 'nullable|required_with:button_url|string|max:80',
            'button_url' => 'nullable|required_with:button_label|url:http,https|max:2048',
            'expires_at' => 'nullable|date',
        ]);
        $ids = array_map('intval', $data['lab_ids'] ?? []);
        sort($ids, SORT_NUMERIC);
        $payload = [
            'title' => trim($data['title']), 'body' => trim($data['body']),
            'level' => $data['level'], 'audience' => $data['audience'],
            'button_label' => $data['button_label'] ?? null, 'button_url' => $data['button_url'] ?? null,
            'expires_at' => empty($data['expires_at']) ? null : Carbon::parse($data['expires_at'])->utc()->toDateTimeString(),
        ];
        abort_if($payload['title'] === '' || $payload['body'] === '', 422, 'أدخل عنوان الإشعار ونصه.');
        $hash = hash('sha256', json_encode([$payload, $ids], JSON_THROW_ON_ERROR));
        $notice = DB::transaction(function () use ($sender, $data, $ids, $payload, $hash) {
            // Serialize sends by this administrator: a timed-out retry cannot duplicate a broadcast.
            User::whereKey($sender->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('lab_announcements')->where('sender_id', $sender->id)
                ->where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'مفتاح الإرسال مستخدم لإشعار مختلف.');
                return $existing;
            }
            abort_if($payload['expires_at'] && Carbon::parse($payload['expires_at'], 'UTC')->lte(now()), 422, 'تاريخ الانتهاء يجب أن يكون في المستقبل.');
            $labs = $this->eligibleLabs();
            if ($data['audience'] === 'selected') $labs->whereIn('id', $ids);
            $targets = $labs->orderBy('id')->pluck('id');
            abort_if($targets->isEmpty() || ($data['audience'] === 'selected' && $targets->count() !== count($ids)), 422, 'اختر مختبرات صالحة لاستلام الإشعار.');
            $now = now()->utc();
            $id = DB::table('lab_announcements')->insertGetId($payload + [
                'sender_id' => $sender->id, 'request_id' => $data['request_id'], 'payload_hash' => $hash,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($targets->chunk(500) as $chunk) {
                DB::table('lab_announcement_targets')->insert($chunk->map(fn ($lab) => ['announcement_id' => $id, 'lab_id' => $lab])->all());
            }
            return DB::table('lab_announcements')->find($id);
        });
        return response()->json([
            'id' => (int) $notice->id,
            'target_count' => DB::table('lab_announcement_targets')->where('announcement_id', $notice->id)->count(),
            'message' => 'تم إرسال الإشعار للمختبرات المحددة.',
        ], 201);
    }

    public function history(Request $request)
    {
        $this->admin($request);
        $request->validate(['page' => 'nullable|integer|min:1']);
        return response()->json(DB::table('lab_announcements as a')->select('a.id', 'a.title', 'a.body', 'a.level', 'a.audience', 'a.button_label', 'a.button_url', 'a.created_at', 'a.expires_at', 'a.cancelled_at')
            ->selectSub(DB::table('lab_announcement_targets as t')->selectRaw('COUNT(*)')->whereColumn('t.announcement_id', 'a.id'), 'target_count')
            ->selectSub(DB::table('lab_announcement_reads as r')->selectRaw('COUNT(DISTINCT r.lab_id)')->whereColumn('r.announcement_id', 'a.id'), 'read_lab_count')
            ->orderByDesc('a.id')->paginate(15));
    }

    public function cancel(Request $request, string $id)
    {
        $this->admin($request);
        abort_unless(DB::table('lab_announcements')->where('id', $id)->exists(), 404);
        DB::table('lab_announcements')->where('id', $id)->whereNull('cancelled_at')->update(['cancelled_at' => now()->utc(), 'updated_at' => now()->utc()]);
        return response()->json(['message' => 'تم إيقاف عرض الإشعار. لا يمكن سحب ما سبق أن شاهده المستخدم.']);
    }

    private function feed(int $labId, int $userId): Builder
    {
        return DB::table('lab_announcements as a')
            ->join('lab_announcement_targets as t', 't.announcement_id', '=', 'a.id')->where('t.lab_id', $labId)
            ->leftJoin('lab_announcement_reads as r', function ($join) use ($labId, $userId) {
                $join->on('r.announcement_id', '=', 'a.id')->where('r.lab_id', $labId)->where('r.user_id', $userId);
            })
            ->whereNull('a.cancelled_at')
            ->where(fn ($query) => $query->whereNull('a.expires_at')->orWhere('a.expires_at', '>', now()->utc()))
            ->select('a.id', 'a.title', 'a.body', 'a.level', 'a.button_label', 'a.button_url', 'a.created_at', 'a.expires_at', 'r.read_at');
    }

    public function pending(Request $request)
    {
        $labId = $this->tenant($request);
        $query = $this->feed($labId, (int) $request->user()->id)->whereNull('r.read_at');
        return response()->json([
            'lab_id' => $labId,
            'unread_count' => (clone $query)->count(),
            // Oldest first; acknowledge each before advancing, so bursts never skip a notice.
            'data' => $query->orderBy('a.id')->limit(20)->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function inbox(Request $request)
    {
        $labId = $this->tenant($request);
        $request->validate(['page' => 'nullable|integer|min:1']);
        return response()->json($this->feed($labId, (int) $request->user()->id)->orderByDesc('a.id')->paginate(15))
            ->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, string $id)
    {
        $labId = $this->tenant($request);
        $userId = (int) $request->user()->id;
        abort_unless($this->feed($labId, $userId)->where('a.id', $id)->exists(), 404);
        DB::table('lab_announcement_reads')->insertOrIgnore([
            'announcement_id' => $id, 'lab_id' => $labId, 'user_id' => $userId, 'read_at' => now()->utc(),
        ]);
        return response()->json(['message' => 'تم تأكيد القراءة.']);
    }
}
