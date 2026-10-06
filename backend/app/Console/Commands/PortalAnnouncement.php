<?php

namespace App\Console\Commands;

use App\Models\{PortalNotification, PortalPushSubscription};
use App\Services\PortalNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

// Server administrators can enqueue an announcement without adding controls to
// employee or referral screens. No delivery occurs to people who did not opt in.
class PortalAnnouncement extends Command
{
    protected $signature = 'portal:announce {--lab=} {--patient=} {--all-labs} {--title=} {--body=} {--campaign=}';
    protected $description = 'Queue an announcement for patients who opted in to offers';

    public function handle(): int
    {
        $lab = $this->option('lab');
        $title = trim((string) $this->option('title'));
        $body = trim((string) $this->option('body'));
        if ((!$lab && !$this->option('all-labs')) || ($lab && $this->option('all-labs')) || !$title || !$body
            || mb_strlen($title) > 120 || mb_strlen($body) > 500
            || ($lab && !ctype_digit((string) $lab)) || ($this->option('patient') && !ctype_digit((string) $this->option('patient')))) {
            $this->error('Choose --lab=ID or --all-labs, with --title (max 120) and --body (max 500).');
            return self::FAILURE;
        }
        $campaign = $this->option('campaign') ?: (string) Str::uuid();
        $query = PortalPushSubscription::where('offers_enabled', true)->select(['patient_id', 'lab_id'])->distinct();
        if ($lab) $query->where('lab_id', $lab);
        if ($this->option('patient')) $query->where('patient_id', $this->option('patient'));
        $count = 0;
        $query->orderBy('patient_id')->chunk(200, function ($rows) use ($campaign, $title, $body, &$count) {
            foreach ($rows as $row) {
                $notice = PortalNotification::firstOrCreate(['event_key' => hash('sha256', 'offer:'.$campaign.':'.$row->patient_id.':'.$row->lab_id)],
                    ['patient_id' => $row->patient_id, 'lab_id' => $row->lab_id, 'kind' => 'offer', 'title' => $title, 'body' => $body]);
                if ($notice->wasRecentlyCreated) { app(PortalNotifications::class)->queue($notice); $count++; }
            }
        });
        $this->info('Queued '.$count.' patient announcements. Campaign: '.$campaign);
        return self::SUCCESS;
    }
}
