<?php
namespace App\Services;

use App\Models\{LabSetting, PortalLabNotificationSetting, PortalNotification, PortalNotificationCampaign, User};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PortalCampaigns
{
    private array $scopes = [];

    private function labUsers(int $lab): array
    {
        if (isset($this->scopes[$lab])) return $this->scopes[$lab];
        $ids = $frontier = [$lab];
        while ($frontier) {
            $frontier = User::whereIn('creator_id', $frontier)->whereNotIn('role_id', [1, 2, 3])
                ->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }
        return $this->scopes[$lab] = $ids;
    }
    /** Only current opt-ins with usable patient access are selectable. */
    public function audience(int $lab, string $kind = 'offer'): Builder
    {
        $query = DB::table('portal_push_subscriptions as s')
            ->join('patients as p', 'p.id', '=', 's.patient_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->join('portal_access_tokens as a', function ($join) {
                $join->on('a.id', '=', 's.portal_access_token_id')->on('a.patient_id_fk', '=', 'p.id');
            })->where('s.lab_id', $lab)->whereIn('p.creator_id', $this->labUsers($lab))->whereNull('p.deleted_at')->whereNull('u.deleted_at')
            ->where(fn ($q) => $q->whereNull('a.expires_at')->orWhere('a.expires_at', '>', now()));
        if ($kind === 'offer') $query->where('s.offers_enabled', true);
        elseif ($kind === 'result') $query->where('s.results_enabled', true);
        else $query->where(fn ($q) => $q->where('s.results_enabled', true)->orWhere('s.offers_enabled', true));
        $loyalty = LabSetting::where('lab_id_fk', $lab)->value('loyalty_config');
        if (is_string($loyalty)) $loyalty = json_decode($loyalty, true);
        if ($loyalty['require_otp'] ?? false) $query->whereNotNull('a.verified_at');
        return $query;
    }

    /** Expand campaigns in bounded batches; the existing outbox sends the push. */
    public function prepare(int $limit = 200): int
    {
        $prepared = 0;
        while ($prepared < $limit) {
            $count = DB::transaction(function () use ($limit, $prepared) {
                $campaign = PortalNotificationCampaign::whereIn('status', ['queued', 'processing'])->oldest()->lockForUpdate()->first();
                if (!$campaign) return null;
                if (!PortalLabNotificationSetting::allows($campaign->lab_id_fk, $campaign->kind) || $campaign->created_at->lt(now()->subDay())) {
                    $campaign->update(['status' => 'cancelled']);
                    return 0;
                }
                $query = $this->audience($campaign->lab_id_fk, $campaign->kind)->where('s.patient_id', '>', $campaign->last_patient_id)
                    ->where('s.patient_id', '<=', $campaign->max_patient_id);
                if ($campaign->patient_id) $query->where('s.patient_id', $campaign->patient_id);
                $batch = min(100, $limit - $prepared);
                $patients = $query->select('s.patient_id')->distinct()->orderBy('s.patient_id')->limit($batch)->pluck('patient_id');
                foreach ($patients as $patient) {
                    $notice = PortalNotification::firstOrCreate(['event_key' => hash('sha256', 'campaign:'.$campaign->id.':'.$patient)], [
                        'lab_id' => $campaign->lab_id_fk, 'patient_id' => $patient, 'campaign_id' => $campaign->id,
                        'kind' => $campaign->kind, 'title' => $campaign->title, 'body' => $campaign->body, 'expires_at' => now()->addDay(),
                    ]);
                    if ($notice->wasRecentlyCreated) {
                        app(PortalNotifications::class)->queue($notice);
                        $campaign->queued_patients++;
                    }
                    $campaign->last_patient_id = $patient;
                }
                $campaign->status = $patients->count() < $batch ? 'completed' : 'processing';
                $campaign->save();
                return $patients->count();
            });
            if ($count === null) break;
            $prepared += $count;
        }
        return $prepared;
    }
}
