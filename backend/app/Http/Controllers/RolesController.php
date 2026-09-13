<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index(Request $request)
    {
        // Use Auth facade which doesn't trigger an additional query
        // if the user is already authenticated
        if (!Auth::check()) {
            return response()->json(data: [
                'success' => false,
                'message' => 'Unauthorized',
                'user' => Auth::user(),
            ]);
        }
        $user = Auth::user();
        if (!$user) {
            return response()->json(data: [
                'success' => false,
                'message' => 'User not found',
                'user' => $user,
            ]);
        }
        // Add this before checking permissions
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        if (!$user->hasPermissionTo('roles view')) {
            return response()->json(data: [
                'success' => false,
                'message' => 'Unauthorized',
            ]);
        }
        $userRoleId = $user->role_id;

        $rolesQuery = Role::query();

        // Multi-tenant filtering for custom roles
        $users_ids = $this->getTenantUserIds();

        if ($userRoleId === 1) {
            // Admin: see role 2 (Lab) + custom roles
            $rolesQuery->where(function ($q) use ($users_ids) {
                $q->where('id', 2)
                  ->orWhereIn('lab_id_fk', $users_ids);
            })->with('permissions');
        } elseif ($userRoleId === 2) {
            // Lab: see system roles (except Admin and Lab) + their own custom roles
            $rolesQuery->where(function ($q) use ($users_ids) {
                $q->where(function ($sub) {
                    $sub->whereNotIn('id', [2, 1])
                        ->whereNull('lab_id_fk');
                })->orWhereIn('lab_id_fk', $users_ids);
            })->with('permissions');
        } else {
            // Others: see system roles (except 1,2,3,4) + their own custom roles
            $rolesQuery->where(function ($q) use ($users_ids) {
                $q->where(function ($sub) {
                    $sub->whereNotIn('id', [1, 2, 3, 4])
                        ->whereNull('lab_id_fk');
                })->orWhereIn('lab_id_fk', $users_ids);
            })->with('permissions');
        }

        $roles = $rolesQuery->latest()->get();

        return response()->json($roles, 200);
    }
}
