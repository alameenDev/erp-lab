<?php

namespace App\Services;

use App\Models\{Invoice, PortalLabNotificationSetting, PortalNotification, PortalNotificationCampaign, PortalPushDelivery, PortalPushSubscription};
use Illuminate\Support\Facades\{Cache, DB};

class PortalNotifications
{
    public function ready(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            if (!$invoice || !app(ReportReadiness::class)->isReady($invoice) || !$invoice->patient || !$invoice->lab) return;
            $lab = app(PatientPortalAccess::class)->lab($invoice->patient);
            if (!$lab || app(InventoryService::class)->labId($invoice->lab) !== (int) $lab->id
                || !PortalLabNotificationSetting::allows($lab->id, 'result')) return;
            $config = PortalLabNotificationSetting::forLab($lab->id);
            $notice = PortalNotification::firstOrCreate(['event_key' => hash('sha256', 'ready:'.$invoice->id)], [
                'patient_id' => $invoice->patient_id_fk, 'lab_id' => $lab->id, 'invoice_id' => $invoice->id,
                'kind' => 'result', 'title' => $config['result_title'], 'body' => $config['result_body'],
                'expires_at' => now()->addDay(),
            ]);
            if ($notice->wasRecentlyCreated) {
                $this->queue($notice);
            } elseif (!PortalPushDelivery::where('notification_id', $notice->id)->where('status', 'accepted')->exists()) {
                // If approval was withdrawn before dispatch, the next complete
                // approval can resume the same unsent notice, without duplicates.
                $resumed = PortalPushDelivery::where('notification_id', $notice->id)->where('status', 'cancelled')
                    ->where('status_reason', 'report_not_ready')->update([
                        'status' => 'queued', 'status_reason' => null, 'attempts' => 0, 'next_attempt_at' => now(),
                    ]);
                if ($resumed) $notice->update(['expires_at' => now()->addDay(), 'title' => $config['result_title'], 'body' => $config['result_body']]);
            }
        });
    }

    public function queue(PortalNotification $notice, ?PortalPushSubscription $only = null): void
    {
        if (!PortalLabNotificationSetting::allows($notice->lab_id, $notice->kind)) return;
        $subscriptions = PortalPushSubscription::where('patient_id', $notice->patient_id)->where('lab_id', $notice->lab_id);
        if ($only) $subscriptions->whereKey($only->id);
        elseif ($notice->kind === 'offer') $subscriptions->where('offers_enabled', true);
        elseif ($notice->kind === 'test') $subscriptions->where(fn ($q) => $q->where('results_enabled', true)->orWhere('offers_enabled', true));
        else $subscriptions->where('results_enabled', true);
        $subscriptions->each(function ($subscription) use ($notice) {
            PortalPushDelivery::firstOrCreate(['notification_id' => $notice->id, 'subscription_id' => $subscription->id],
                ['status' => 'queued', 'next_attempt_at' => now()]);
        });
    }

    public function deliver(int $limit = 20): int
    {
        Cache::put('portal_push_last_run_at', now()->toIso8601String(), now()->addDays(2));
        if (!app(PortalPushTransport::class)->keys()) return 0;
        app(PortalCampaigns::class)->prepare();
        PortalPushDelivery::where('status', 'processing')->where('attempts', '>=', 5)
            ->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'failed', 'status_reason' => 'attempts_exhausted', 'next_attempt_at' => null]);
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
            $status = 'cancelled'; $reason = 'subscription_inactive';
            $subscription = PortalPushSubscription::find($delivery->subscription_id);
            $notice = PortalNotification::find($delivery->notification_id);
            if ($subscription && $notice
                && (int) $notice->patient_id === (int) $subscription->patient_id && (int) $notice->lab_id === (int) $subscription->lab_id) {
                try {
                    if (($notice->expires_at ?? $notice->created_at->copy()->addDay())->isPast()) $reason = 'message_expired';
                    elseif (!PortalLabNotificationSetting::allows($notice->lab_id, $notice->kind)) $reason = 'lab_disabled';
                    elseif ($notice->campaign_id && PortalNotificationCampaign::whereKey($notice->campaign_id)->where('status', 'cancelled')->exists()) $reason = 'campaign_cancelled';
                    elseif (!$this->consented($notice, $subscription)) $reason = 'patient_opted_out';
                    else {
                        $access = \App\Models\PortalAccessToken::find($subscription->portal_access_token_id);
                        [$verified, $patient, $lab] = app(PatientPortalAccess::class)->resolve($access?->token ?? '');
                        $valid = (int) $patient->id === (int) $notice->patient_id && (int) $lab->id === (int) $notice->lab_id;
                        $reason = 'access_invalid';
                        if ($valid && $notice->invoice_id) {
                            $invoice = Invoice::whereKey($notice->invoice_id)->where('patient_id_fk', $patient->id)->first();
                            $valid = $invoice && app(ReportReadiness::class)->isReady($invoice)
                                && $invoice->lab && app(InventoryService::class)->labId($invoice->lab) === (int) $lab->id;
                            if (!$valid) $reason = 'report_not_ready';
                        }
                        if ($valid) {
                            $url = rtrim(config('app.frontend_url', config('app.url')), '/').'/portal/'.$verified->token;
                            $status = app(PortalPushTransport::class)->send($subscription->subscription, [
                                'title' => $notice->title, 'body' => $notice->body, 'url' => $url, 'tag' => 'portal-'.$notice->id,
                            ]);
                            $reason = $status === 'accepted' ? null : ($status === 'expired' ? 'subscription_expired' : 'provider_unavailable');
                        }
                    }
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                    $reason = 'access_invalid';
                } catch (\Throwable $e) {
                    // Never log provider endpoints, bearer URLs, or browser keys.
                    $status = 'retry'; $reason = 'provider_unavailable';
                }
            }
            if ($status === 'expired') $subscription?->delete();
            if ($status === 'retry') $status = $delivery->attempts >= 5 ? 'failed' : 'queued';
            $delivery->update(['status' => $status, 'status_reason' => $reason,
                'next_attempt_at' => $status === 'queued' ? now()->addMinutes(2 ** $delivery->attempts) : null]);
        }
        return $count;
    }

    private function consented(PortalNotification $notice, PortalPushSubscription $subscription): bool
    {
        if ($notice->kind === 'offer') return $subscription->offers_enabled;
        if ($notice->kind === 'test') return !$notice->campaign_id || $subscription->results_enabled || $subscription->offers_enabled;
        return $subscription->results_enabled;
    }
}
