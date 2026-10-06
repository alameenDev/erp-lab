<?php

namespace App\Services;

use App\Models\{Invoice, PortalNotification, PortalPushDelivery, PortalPushSubscription};
use Illuminate\Support\Facades\DB;

class PortalNotifications
{
    public function ready(Invoice $invoice): void
    {
        if (!$invoice->is_done || !$invoice->patient || !$invoice->lab) return;
        $lab = app(PatientPortalAccess::class)->lab($invoice->patient);
        if (!$lab || app(InventoryService::class)->labId($invoice->lab) !== (int) $lab->id) return;
        DB::transaction(function () use ($invoice, $lab) {
            $notice = PortalNotification::firstOrCreate(['event_key' => hash('sha256', 'ready:'.$invoice->id)], [
                'patient_id' => $invoice->patient_id_fk, 'lab_id' => $lab->id, 'invoice_id' => $invoice->id,
                'kind' => 'result', 'title' => 'تقريرك الطبي جاهز',
                'body' => 'يوجد تقرير جديد في بوابتك. افتح البوابة للاطلاع على التفاصيل.',
            ]);
            if (!$notice->wasRecentlyCreated) return;
            $this->queue($notice);
        });
    }

    public function queue(PortalNotification $notice, ?PortalPushSubscription $only = null): void
    {
        $subscriptions = PortalPushSubscription::where('patient_id', $notice->patient_id)->where('lab_id', $notice->lab_id);
        if ($only) $subscriptions->whereKey($only->id);
        elseif ($notice->kind === 'offer') $subscriptions->where('offers_enabled', true);
        else $subscriptions->where('results_enabled', true);
        $subscriptions->each(function ($subscription) use ($notice) {
            PortalPushDelivery::firstOrCreate(['notification_id' => $notice->id, 'subscription_id' => $subscription->id],
                ['status' => 'queued', 'next_attempt_at' => now()]);
        });
    }

    public function deliver(int $limit = 20): int
    {
        if (!app(PortalPushTransport::class)->keys()) return 0;
        PortalPushDelivery::where('status', 'processing')->where('attempts', '>=', 5)
            ->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'failed', 'next_attempt_at' => null]);
        $count = 0;
        while ($count < $limit) {
            $delivery = DB::transaction(function () {
                $row = PortalPushDelivery::where('attempts', '<', 5)->where(function ($q) {
                    $q->where(fn ($queued) => $queued->where('status', 'queued')->where('next_attempt_at', '<=', now()))
                        ->orWhere(fn ($stale) => $stale->where('status', 'processing')->where('updated_at', '<', now()->subMinutes(5)));
                })->orderBy('id')->lockForUpdate()->first();
                if ($row) $row->update(['status' => 'processing', 'attempts' => $row->attempts + 1]);
                return $row;
            });
            if (!$delivery) break;
            $count++;
            $status = 'cancelled';
            $subscription = PortalPushSubscription::find($delivery->subscription_id);
            $notice = PortalNotification::find($delivery->notification_id);
            if ($subscription && $notice && $notice->created_at->gt(now()->subDay())
                && (int) $notice->patient_id === (int) $subscription->patient_id && (int) $notice->lab_id === (int) $subscription->lab_id
                && ($notice->kind === 'test' || ($notice->kind === 'offer' ? $subscription->offers_enabled : $subscription->results_enabled))) {
                try {
                    $access = \App\Models\PortalAccessToken::find($subscription->portal_access_token_id);
                    [$verified, $patient, $lab] = app(PatientPortalAccess::class)->resolve($access?->token ?? '');
                    $valid = (int) $patient->id === (int) $notice->patient_id && (int) $lab->id === (int) $notice->lab_id;
                    if ($notice->invoice_id) $valid = $valid && Invoice::whereKey($notice->invoice_id)
                        ->where('patient_id_fk', $patient->id)->where('is_done', true)->exists();
                    if ($valid) {
                        $url = rtrim(config('app.frontend_url', config('app.url')), '/').'/portal/'.$verified->token;
                        $status = app(PortalPushTransport::class)->send($subscription->subscription, [
                            'title' => $notice->title, 'body' => $notice->body, 'url' => $url,
                            'tag' => 'portal-'.$notice->id,
                        ]);
                    }
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    $status = 'cancelled'; // revoked/expired access or OTP no longer verified
                } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                    $status = 'cancelled';
                } catch (\Throwable $e) {
                    // Never log the provider endpoint, bearer URL, or browser keys.
                    $status = 'retry';
                }
            }
            if ($status === 'expired') $subscription?->delete();
            if ($status === 'retry') $status = $delivery->attempts >= 5 ? 'failed' : 'queued';
            $delivery->update(['status' => $status, 'next_attempt_at' => $status === 'queued' ? now()->addMinutes(2 ** $delivery->attempts) : null]);
        }
        return $count;
    }
}
