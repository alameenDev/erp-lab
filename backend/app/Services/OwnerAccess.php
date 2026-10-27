<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class OwnerAccess
{
    public function labs(User $owner): Collection
    {
        abort_unless($owner->owner_portal_only, 403);
        return User::where('role_id', 2)->where('referral_portal_only', false)->where('owner_portal_only', false)
            ->whereIn('id', \DB::table('owner_labs')->where('owner_id', $owner->id)->select('lab_id'))
            ->orderBy('name')->get();
    }

    public function selected(User $owner, ?int $id = null): Collection
    {
        $labs = $this->labs($owner);
        if ($id) {
            abort_unless($labs->contains('id', $id), 404);
            return $labs->where('id', $id)->values();
        }
        return $labs;
    }

    public function accounts(Collection $labs): array
    {
        return $labs->flatMap(function ($lab) {
            $ids = app(AccountAccess::class)->tenantUserIds($lab);
            return User::withTrashed()->whereIn('id', $ids)->where(function ($q) use ($lab) {
                $q->where('id', $lab->id)->orWhere(fn ($staff) => $staff
                    ->where(fn ($role) => $role->whereNull('role_id')->orWhereNotIn('role_id', [1, 2, 3, 5]))
                    ->where('referral_portal_only', false)->where('owner_portal_only', false));
            })->pluck('id');
        })->unique()->values()->all();
    }
}
