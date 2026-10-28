<?php
namespace App\Services;

use App\Models\{Lab, Referal, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectorAccess
{
    public function lab(User $collector): User
    {
        abort_unless($collector->collector_portal_only && $collector->status === 1, 403, 'هذا الحساب غير مخصص لسحب العينات.');
        $id = DB::table('collector_accounts')->where('user_id', $collector->id)->value('lab_id');
        return User::where('role_id',2)->where('status',1)->where('owner_portal_only',false)->where('collector_portal_only',false)->findOrFail($id);
    }
    public function manager(Request $r, string $permission = 'sample logistics view'): User
    {
        $user = $r->user();
        abort_if($user->collector_portal_only || $user->owner_portal_only || $user->referral_portal_only,403);
        abort_unless(in_array((int)$user->role_id,[1,2]) || $user->can($permission),403,'ليست لديك صلاحية إدارة سحب العينات.');
        $id = (int)$user->role_id === 1 ? $r->integer('lab_id') : app(AccountAccess::class)->tenantId($user);
        abort_unless($id,422,'اختر المختبر أولاً.');
        return User::where('role_id',2)->where('collector_portal_only',false)->where('owner_portal_only',false)->findOrFail($id);
    }
    public function accounts(User $lab): array { return app(AccountAccess::class)->tenantUserIds($lab); }
    public function sources(User $lab)
    {
        $ids = $this->accounts($lab);
        return User::where('collector_portal_only',false)->where('owner_portal_only',false)
            ->where(fn ($q) => $q->whereIn('role_id',[2,4])->orWhereIn('id', DB::table('referral_lab_profiles')->select('user_id_fk')))
            ->where(fn ($q) => $q->whereIn('id', Referal::whereIn('lab_id_fk',$ids)->select('referral_id_fk'))
                ->orWhereIn('id', Lab::whereIn('parent_lab_id_fk',$ids)->select('user_id_fk')))->where('id','!=',$lab->id);
    }
    public function source(User $lab, int $id): User { return $this->sources($lab)->findOrFail($id); }
    public function assignedSources(User $collector)
    {
        $lab = $this->lab($collector);
        return $this->sources($lab)->whereIn('id', DB::table('collector_lab_assignments')->where('lab_id',$lab->id)->where('collector_id',$collector->id)->select('source_lab_id'));
    }
    public function assertSource(User $collector, ?int $id): void
    {
        if ($id) $this->assignedSources($collector)->findOrFail($id);
    }
    public function collectors(User $lab)
    {
        return User::where('collector_portal_only',true)->whereIn('id', DB::table('collector_accounts')->where('lab_id',$lab->id)->select('user_id'));
    }
    public function activeSubscription(User $lab): bool
    {
        return \App\Models\Subscription::where('lab_id_fk',$lab->id)->whereIn('status',['active','trial'])->where('end_date','>=',now('Asia/Baghdad')->toDateString())->exists();
    }
}
