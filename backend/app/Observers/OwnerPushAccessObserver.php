<?php
namespace App\Observers;

use App\Models\{OwnerPushSubscription, User};
use Illuminate\Support\Facades\{DB, Schema};

class OwnerPushAccessObserver
{
    public function saved(User $user): void
    {
        if (! Schema::hasTable('owner_push_subscriptions')) return;
        if (($user->owner_portal_only || $user->getOriginal('owner_portal_only')) &&
            $user->wasChanged(['password', 'status', 'owner_portal_only'])) {
            OwnerPushSubscription::where('owner_id', $user->id)->delete();
        }
        if ($user->owner_portal_only) DB::table('owner_digest_settings')->insertOrIgnore([
            'owner_id' => $user->id, 'start_date' => now('Asia/Baghdad')->toDateString(),
        ]);
    }
    public function deleted(User $user): void
    {
        if (Schema::hasTable('owner_push_subscriptions')) OwnerPushSubscription::where('owner_id', $user->id)->delete();
    }
}
