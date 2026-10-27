<?php
namespace App\Services;

use App\Models\{Invoice, OwnerNotification, OwnerPushSubscription, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;

/** Durable owner inbox and outbox. No network I/O in invoice requests. */
class OwnerNotifications
{
    public function visible(User $owner)
    {
        $labs = app(OwnerAccess::class)->labs($owner)->pluck('id');
        return OwnerNotification::where('owner_id', $owner->id)
            ->where(fn ($q) => $q->whereNull('lab_id')->orWhereIn('lab_id', $labs));
    }

    public function create(User $owner, ?int $lab, string $event, string $kind, string $title, string $body, array $data, ?int $device = null): OwnerNotification
    {
        return DB::transaction(function () use ($owner, $lab, $event, $kind, $title, $body, $data, $device) {
            $key = hash('sha256', $owner->id.':'.$event);
            $id = (string) Str::uuid();
            $inserted = DB::table('owner_notifications')->insertOrIgnore([
                'id' => $id, 'owner_id' => $owner->id, 'lab_id' => $lab, 'event_key' => $key, 'kind' => $kind,
                'title' => Str::limit($title, 175), 'body' => Str::limit($body, 480),
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'expires_at' => now()->addHours(48), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $notice = OwnerNotification::where('event_key', $key)->firstOrFail();
            if ($inserted) {
                $query = OwnerPushSubscription::where('owner_id', $owner->id);
                if ($device) $query->whereKey($device);
                elseif ($kind === 'daily_summary') $query->where('daily_enabled', true);
                else $query->where('invoice_enabled', true);
                foreach ($query->pluck('id') as $subscription) DB::table('owner_push_deliveries')->insertOrIgnore([
                    'notification_id' => $notice->id, 'subscription_id' => $subscription,
                    'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $notice;
        });
    }

    public function invoiceChanged(object $row): void
    {
        if ($row->event !== 'deleted' && ($row->total_delta === null || abs((float) $row->total_delta) < 0.005)) return;
        $lab = User::find($row->tenant_id);
        if (! $lab) return;
        $owners = User::where('owner_portal_only', true)->where('status', 1)
            ->whereIn('id', DB::table('owner_labs')->where('lab_id', $lab->id)->select('owner_id'))->get();
        $deleted = $row->event === 'deleted';
        $amount = fn ($v) => $v === null ? 'غير مسجل' : number_format((float) $v, 0).' د.ع';
        $title = ($deleted ? 'حذف فاتورة' : 'تعديل مبلغ فاتورة').' · '.$lab->name;
        $body = 'فاتورة #'.$row->invoice_id.' · '.($row->actor_name ?: 'مستخدم غير مسجل').' · '.
            ($deleted ? 'القيمة: '.$amount($row->before_total) : 'من '.$amount($row->before_total).' إلى '.$amount($row->after_total));
        foreach ($owners as $owner) $this->create($owner, $lab->id, 'archive:'.$row->id, $deleted ? 'invoice_deleted' : 'invoice_amount', $title, $body, [
            'archive_id' => $row->id, 'invoice_id' => $row->invoice_id, 'lab_name' => $lab->name,
            'actor_name' => $row->actor_name, 'before_total' => $row->before_total, 'after_total' => $row->after_total,
            'total_delta' => $row->total_delta, 'occurred_at' => Carbon::parse($row->occurred_at, config('app.timezone'))->toIso8601String(),
        ]);
    }

    public function daily(?string $date = null): int
    {
        $now = Carbon::now('Asia/Baghdad');
        if ($date === null && $now->format('H:i') < '00:05') return 0;
        if ($date !== null && (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || Carbon::parse($date, 'Asia/Baghdad')->format('Y-m-d') !== $date || $date >= $now->toDateString())) {
            throw new \InvalidArgumentException('Choose a completed Baghdad calendar day (YYYY-MM-DD).');
        }
        $dates = $date ? [$date] : collect([3, 2, 1])->map(fn ($days) => $now->copy()->subDays($days)->toDateString())->all();
        $created = 0;
        User::where('owner_portal_only', true)->where('status', 1)->chunkById(100, function ($owners) use ($dates, &$created) {
            foreach ($owners as $owner) {
                DB::table('owner_digest_settings')->insertOrIgnore(['owner_id' => $owner->id, 'start_date' => now('Asia/Baghdad')->toDateString()]);
                $start = DB::table('owner_digest_settings')->where('owner_id', $owner->id)->value('start_date');
                foreach (app(OwnerAccess::class)->labs($owner) as $lab) foreach ($dates as $day) {
                    if ($day < $start) continue;
                    $event = 'daily:'.$lab->id.':'.$day;
                    if (OwnerNotification::where('event_key', hash('sha256', $owner->id.':'.$event))->exists()) continue;
                    $data = $this->summary($owner, $lab, $day);
                    $s = $data['summary'];
                    $body = $day.' · '.$s['patient_count'].' مريض · المدفوع '.number_format($s['paid']).' د.ع · المتبقي '.number_format($s['balance']).' د.ع · '.$s['referral_count'].' إحالة';
                    $this->create($owner, $lab->id, $event, 'daily_summary', 'ملخص يوم العمل · '.$lab->name, $body, $data);
                    $created++;
                }
            }
        });
        return $created;
    }

    public function summary(User $owner, User $lab, string $day): array
    {
        $ids = app(OwnerAccess::class)->accounts(collect([$lab]));
        $report = new OwnerAccountingReport($owner, ['from' => $day, 'to' => $day], $ids);
        $data = $report->overview();
        $q = $report->query();
        $referrals = (clone $q)->where(fn ($r) => $r->whereNotNull('invoices.from_lab_id_fk')
            ->orWhereHas('referral', fn ($u) => $u->whereIn('role_id', [2, 4, 5])))->count();
        $start = Carbon::parse($day, 'Asia/Baghdad')->startOfDay()->setTimezone(config('app.timezone'));
        $end = $start->copy()->addDay();
        // Cash totals come from the payment ledger, never the invoice's cached paid field.
        // Exclude payments entered after this day, including when catching up after downtime.
        $paid = DB::table('invoice_paid_details')->whereNull('deleted_at')->where('created_at', '<', $end)
            ->select('invoice_id_fk')->selectRaw('SUM(amount) AS amount')->groupBy('invoice_id_fk');
        $money = $report->scope()->where('invoices.created_at', '>=', $start)->where('invoices.created_at', '<', $end)
            ->leftJoinSub($paid, 'digest_paid', 'digest_paid.invoice_id_fk', '=', 'invoices.id')
            ->selectRaw('COALESCE(SUM(COALESCE(digest_paid.amount,0)),0) AS paid, COALESCE(SUM(GREATEST(0, COALESCE(invoices.total,0)-COALESCE(digest_paid.amount,0))),0) AS balance')->first();
        $archive = DB::table('invoice_change_archive')->where('tenant_id', $lab->id)->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->selectRaw("SUM(CASE WHEN event = 'deleted' THEN 1 ELSE 0 END) AS deleted_count, SUM(CASE WHEN event = 'deleted' THEN before_total ELSE 0 END) AS deleted_total,
                SUM(CASE WHEN event = 'updated' AND total_delta > 0 THEN total_delta ELSE 0 END) AS increases,
                SUM(CASE WHEN event = 'updated' AND total_delta < 0 THEN -total_delta ELSE 0 END) AS decreases")->first();
        return ['day' => $day, 'timezone' => 'Asia/Baghdad', 'lab_name' => $lab->name, 'generated_at' => now()->toIso8601String(),
            'summary' => collect($data['summary'])->only(['invoice_count', 'patient_count', 'analysis_count', 'total', 'collections_in_period'])->all() + [
                'paid' => (float) $money->paid, 'balance' => (float) $money->balance, 'referral_count' => $referrals,
                'doctor_referrals' => collect($data['doctor_referrals'])->sum('invoice_count'), 'lab_referrals' => collect($data['lab_referrals'])->sum('invoice_count'),
                'completed' => (clone $q)->where('invoices.is_done', true)->count(),
                'pending' => (clone $q)->where(fn ($r) => $r->where('invoices.is_done', false)->orWhereNull('invoices.is_done'))->count(),
                'deleted_count' => (int) $archive->deleted_count, 'deleted_total' => (float) $archive->deleted_total,
                'increases' => (float) $archive->increases, 'decreases' => (float) $archive->decreases,
            ],
            'note' => 'المدفوع والمتبقي لفواتير هذا اليوم، والتحصيل هو الدفعات المسجلة خلال اليوم ويشمل تسديد ديون سابقة. الإحالة تُحسب مرة لكل فاتورة حتى لو تضمنت طبيباً ومختبراً. الملخص لقطة محفوظة حسب البيانات المتاحة عند إعداده؛ المحذوفات معروضة بصورة منفصلة.',
        ];
    }

    public function deliver(int $limit = 20): int
    {
        Cache::put('owner_push_worker_at', now()->toIso8601String(), now()->addDay());
        $transport = app(PortalPushTransport::class);
        if (! $transport->keys()) return 0;
        $processed = 0;
        while ($processed < $limit) {
            $job = DB::transaction(function () {
                $row = DB::table('owner_push_deliveries')->where(function ($q) {
                    $q->where(fn ($p) => $p->whereIn('status', ['pending', 'retry'])->where('next_attempt_at', '<=', now()))
                        ->orWhere(fn ($p) => $p->where('status', 'processing')->where('updated_at', '<', now()->subMinutes(5)));
                })->orderBy('id')->lockForUpdate()->first();
                if (! $row) return null;
                $row->claim = (string) Str::uuid(); $row->attempts++;
                DB::table('owner_push_deliveries')->where('id', $row->id)->update(['claim' => $row->claim, 'status' => 'processing', 'attempts' => $row->attempts, 'updated_at' => now()]);
                return $row;
            });
            if (! $job) break;
            $processed++;
            $notice = OwnerNotification::find($job->notification_id);
            $sub = OwnerPushSubscription::find($job->subscription_id);
            $owner = $sub ? User::find($sub->owner_id) : null;
            $eligible = $notice && $sub && $owner && $owner->status && $owner->owner_portal_only &&
                (int) $notice->owner_id === (int) $owner->id && $notice->expires_at->isFuture() &&
                (! $notice->lab_id || app(OwnerAccess::class)->labs($owner)->contains('id', $notice->lab_id)) &&
                ($notice->kind === 'test' || ($notice->kind === 'daily_summary' ? $sub->daily_enabled : $sub->invoice_enabled));
            $status = 'cancelled';
            if ($eligible && $job->attempts <= 5) {
                try {
                    $result = $transport->send($sub->subscription, [
                        'id' => $notice->id, 'owner_id' => $owner->id, 'title' => $notice->title, 'body' => $notice->body,
                        'url' => '/owner-portal/notifications?notice='.$notice->id,
                    ]);
                    if ($result === 'expired') { $sub->delete(); continue; }
                    $status = $result === 'accepted' ? 'accepted' : ($job->attempts < 5 ? 'retry' : 'failed');
                } catch (\Throwable $error) { $status = $job->attempts < 5 ? 'retry' : 'failed'; }
            } elseif ($eligible) $status = 'failed';
            // A late worker may not overwrite a newer lease or a removed device.
            DB::table('owner_push_deliveries')->where('id', $job->id)->where('claim', $job->claim)->update([
                'status' => $status, 'next_attempt_at' => $status === 'retry' ? now()->addMinutes(2 ** min($job->attempts, 5)) : null, 'updated_at' => now(),
            ]);
        }
        return $processed;
    }
}
