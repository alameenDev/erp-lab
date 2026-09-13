<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Get the tenant-scoped user IDs for the current authenticated user.
     * For lab owners (role_id=2): returns their ID + all users they created.
     * For staff/custom roles: resolves up to the lab owner via creator_id, then returns the full scope.
     * Admin (role_id=1) should bypass this entirely.
     */
    protected function getTenantUserIds(): array
    {
        $user = Auth::user();
        if (! $user || $user->role_id == 1) {
            return [];
        }

        // Determine the lab owner ID
        // If user is a lab owner (role_id=2), they are the anchor
        // Otherwise, their creator_id points to the lab owner (or another staff who was created by the lab)
        $labOwnerId = $user->role_id == 2 ? $user->id : ($user->creator_id ?? $user->id);

        // If creator is also not a lab owner, walk up one more level
        if ($labOwnerId !== $user->id) {
            $creator = User::find($labOwnerId);
            if ($creator && $creator->role_id != 2 && $creator->creator_id) {
                $labOwnerId = $creator->creator_id;
            }
        }

        // Get all users created by the lab owner
        $users_ids = User::where('creator_id', $labOwnerId)
            ->pluck('id')
            ->toArray();
        $users_ids[] = $labOwnerId;

        return $users_ids;
    }
}
