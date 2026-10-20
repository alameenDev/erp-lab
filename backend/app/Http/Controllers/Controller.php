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

        return app(\App\Services\AccountAccess::class)->tenantUserIds($user);
    }
}
