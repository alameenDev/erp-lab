<?php
namespace App\Observers;
use App\Models\{User,CollectorPushSubscription};
use Illuminate\Support\Facades\Schema;
class CollectorAccessObserver
{
    public function saved(User $user):void {
        if(Schema::hasTable('collector_push_subscriptions') && ($user->collector_portal_only || $user->getOriginal('collector_portal_only')) && $user->wasChanged(['password','status','collector_portal_only'])) {
            CollectorPushSubscription::where('collector_id',$user->id)->delete();
            if($user->exists)$user->tokens()->delete();
        }
    }
    public function deleted(User $user):void { if(Schema::hasTable('collector_push_subscriptions'))CollectorPushSubscription::where('collector_id',$user->id)->delete(); }
}
