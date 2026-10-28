<?php
namespace App\Services;
use App\Models\{CollectorNotification, CollectorPushSubscription, User};
use Illuminate\Support\Facades\{DB, Cache};
use Illuminate\Support\Str;
class CollectorNotifications
{
    public function create(int $collector,int $lab,string $event,string $title,string $body,?int $task=null,?int $submission=null): CollectorNotification
    {
        return DB::transaction(function()use($collector,$lab,$event,$title,$body,$task,$submission){
            $key=hash('sha256',$collector.':'.$event);$id=(string)Str::uuid();
            $new=DB::table('collector_notifications')->insertOrIgnore(['id'=>$id,'collector_id'=>$collector,'lab_id'=>$lab,'event_key'=>$key,'title'=>Str::limit($title,175),'body'=>Str::limit($body,480),'task_id'=>$task,'submission_id'=>$submission,'expires_at'=>now()->addHours(48),'created_at'=>now(),'updated_at'=>now()]);
            $n=CollectorNotification::where('event_key',$key)->firstOrFail();
            if($new)foreach(CollectorPushSubscription::where('collector_id',$collector)->pluck('id') as $sub)DB::table('collector_push_deliveries')->insertOrIgnore(['notification_id'=>$n->id,'subscription_id'=>$sub,'status'=>'pending','attempts'=>0,'next_attempt_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            return $n;
        });
    }
    public function deliver(int $limit = 20): int
    {
        Cache::put('collector_push_worker_at', now()->toIso8601String(), now()->addDay());
        $transport = app(PortalPushTransport::class);
        if (! $transport->keys()) return 0;
        $processed = 0;
        while ($processed < $limit) {
            $job = DB::transaction(function () {
                $row = DB::table('collector_push_deliveries')->where(function ($q) {
                    $q->where(fn ($p) => $p->whereIn('status', ['pending', 'retry'])->where('next_attempt_at', '<=', now()))
                        ->orWhere(fn ($p) => $p->where('status', 'processing')->where('updated_at', '<', now()->subMinutes(5)));
                })->orderBy('id')->lockForUpdate()->first();
                if (! $row) return null;
                $row->claim = (string) Str::uuid(); $row->attempts++;
                DB::table('collector_push_deliveries')->where('id', $row->id)->update(['claim' => $row->claim, 'status' => 'processing', 'attempts' => $row->attempts, 'updated_at' => now()]);
                return $row;
            });
            if (! $job) break;
            $processed++;
            $notice = CollectorNotification::find($job->notification_id);
            $sub = CollectorPushSubscription::find($job->subscription_id);
            $collector = $sub ? User::find($sub->collector_id) : null;
            $eligible = false;
            if ($notice && $sub && $collector && $collector->status && $collector->collector_portal_only && (int)$notice->collector_id === (int)$collector->id && $notice->expires_at->isFuture()) {
                try {
                    $lab = app(CollectorAccess::class)->lab($collector);
                    $eligible = $lab->id === (int)$notice->lab_id && app(CollectorAccess::class)->activeSubscription($lab);
                    if ($eligible && $notice->task_id) $eligible = \App\Models\CollectionTask::whereKey($notice->task_id)->where('collector_id',$collector->id)->exists();
                } catch (\Throwable $e) { $eligible = false; }
            }
            $status = 'cancelled';
            if ($eligible && $job->attempts <= 5) {
                try {
                    $result = $transport->send($sub->subscription, [
                        'id' => $notice->id, 'collector_id' => $collector->id, 'title' => $notice->title, 'body' => $notice->body,
                        'url' => '/collector-portal/notifications?notice='.$notice->id,
                    ]);
                    if ($result === 'expired') { $sub->delete(); continue; }
                    $status = $result === 'accepted' ? 'accepted' : ($job->attempts < 5 ? 'retry' : 'failed');
                } catch (\Throwable $error) { $status = $job->attempts < 5 ? 'retry' : 'failed'; }
            } elseif ($eligible) $status = 'failed';
            // A late worker may not overwrite a newer lease or a removed device.
            DB::table('collector_push_deliveries')->where('id', $job->id)->where('claim', $job->claim)->update([
                'status' => $status, 'next_attempt_at' => $status === 'retry' ? now()->addMinutes(2 ** min($job->attempts, 5)) : null, 'updated_at' => now(),
            ]);
        }
        return $processed;
    }
}
