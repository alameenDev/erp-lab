<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    // role crud operations
    public function index()
    {
        $authUser = Auth::user();
        if (!$authUser->hasPermissionTo('roles view')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Role::with('permissions');

        // Multi-tenant filtering: Non-admin users see system roles + their own custom roles
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();

            // Show system roles (lab_id_fk is NULL) + custom roles created by user's lab
            $query->where(function ($q) use ($users_ids) {
                $q->whereNull('lab_id_fk')
                  ->orWhereIn('lab_id_fk', $users_ids);
            });
        }

        $roles = $query->latest()->get();

        return response()->json(data: [
            'success' => true,
            'message' => 'Roles fetched successfully',
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('roles create')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(data: [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ]);
        }

        $authUser = Auth::user();

        // Create role with lab association (non-admin users create lab-specific roles)
        $roleData = [
            'name' => $request->role,
            'guard_name' => 'api',
        ];

        // Non-admin users create roles associated with their lab
        if ($authUser && $authUser->role_id != 1) {
            $roleData['lab_id_fk'] = $authUser->id;
        }

        $role = Role::create($roleData);

        if ($request->has('permissions')) {
            $role->givePermissionTo($request->permissions);
        }

        return response()->json(data: [
            'success' => true,
            'message' => 'Role created successfully',
            'role' => $role,
        ]);
    }

    public function assignRole(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('roles edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(data: [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ]);
        }
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            DB::beginTransaction();

            $user = User::findOrFail($request->user_id);

            // Tenant check: non-admin users can only assign roles to users they created
            $authUser = Auth::user();
            if ($authUser->role_id != 1) {
                if ($user->creator_id !== $authUser->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You can only manage roles for users you created',
                    ], 403);
                }
            }

            $role = Role::findOrFail($request->role_id);

            // Prevent non-admin from assigning admin role
            if ($role->id == 1 && $authUser->role_id != 1) {
                DB::rollBack();

                return response()->json(['success' => false, 'message' => 'Cannot assign admin role'], 403);
            }

            // Remove existing roles first
            $user->roles()->detach();

            // Assign new role using Spatie's method
            $user->assignRole($role);

            // Update role_id if you need to maintain the legacy system
            $user->role_id = $role->id;
            $user->save();

            // Refresh user model to ensure permissions are loaded
            $user->load(['roles.permissions', 'permissions']);

            DB::commit();

            // Check if permissions were properly assigned
            $userPermissions = $user->getAllPermissions();
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json(data: [
                'success' => true,
                'message' => 'Role assigned successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $role->name,
                    'permissions' => $userPermissions->pluck('name'),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(data: [
                'success' => false,
                'message' => 'Failed to assign role',
                'error' => $e->getMessage(),
            ], status: 500);
        }
    }

    public function removeRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(data: [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ]);
        }
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::find($request->user_id);

        // Tenant check: non-admin users can only remove roles from users they created
        $authUser = Auth::user();
        if ($authUser->role_id != 1) {
            if ($user->creator_id !== $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only manage roles for users you created',
                ], 403);
            }
        }

        $user->removeRole($request->role_id);
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(data: [
            'success' => true,
            'message' => 'Role removed successfully',
            'role' => $user,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('roles edit')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'role_id' => 'required',
            'permissions' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json(
                data: [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ],
                status: 422
            );
        }

        $authUser = Auth::user();
        $role = Role::findById($request->role_id, 'api');

        // Multi-tenant check: Non-admin users can only update their own custom roles
        if ($authUser && $authUser->role_id != 1) {
            $users_ids = $this->getTenantUserIds();

            // Can only update custom roles belonging to user's lab (not system roles)
            if ($role->lab_id_fk === null || !in_array($role->lab_id_fk, $users_ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update roles created by your lab',
                ], 403);
            }
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        if ($request->has('role') && $request->role != $role->name) {
            $name_exists = Role::where('name', $request->role)->exists();
            if ($name_exists) {
                return response()->json(data: [
                    'success' => false,
                    'message' => 'Role already exists',
                ]);
            }
            $role->update(['name' => $request->role, 'guard_name' => 'api']);
        }
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(data: [
            'success' => true,
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions'),
        ]);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermissionTo('roles delete')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'role_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(data: [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ]);
        }
        DB::beginTransaction();
        try {
            $authUser = Auth::user();
            $role = Role::findById($request->role_id, 'api');

            // Multi-tenant check: Non-admin users can only delete their own custom roles
            if ($authUser && $authUser->role_id != 1) {
                $users_ids = $this->getTenantUserIds();

                // Can only delete custom roles belonging to user's lab (not system roles)
                if ($role->lab_id_fk === null || !in_array($role->lab_id_fk, $users_ids)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only delete roles created by your lab',
                    ], 403);
                }
            }

            // Check if role is in use
            if ($role->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete role as it is assigned to users',
                ], 422);
            }

            // remove all permissions
            // $role->syncPermissions([]);

            // Delete the role
            $role->delete();

            DB::commit();
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
