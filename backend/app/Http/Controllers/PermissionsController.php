<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

class PermissionsController extends Controller
{
    /**
     * Ensure only admin users can manage permissions.
     */
    private function authorizeAdmin()
    {
        $user = Auth::user();
        if (!$user || $user->role_id !== 1) {
            abort(403, 'Unauthorized');
        }
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $role_id = $request->role_id;

        // Non-admin users can only fetch their own role's permissions
        if ($user->role_id !== 1) {
            $role_id = $user->role_id;
        }

        if ($role_id) {
            $permissions = Permission::whereHas('roles', function ($query) use ($role_id) {
                $query->where('role_id', $role_id);
            })->get();
        } else {
            $permissions = Permission::all();
        }
        $groupedPermissions = $this->groupPermissions($permissions);

        return response()->json(data: [
            'success' => true,
            'message' => 'Permissions fetched successfully',
            'permissions' => $groupedPermissions,
        ]);
    }

    public function getPermissions()
    {
        $this->authorizeAdmin();
        $permissions = Permission::all();

        return response()->json(data: [
            'success' => true,
            'message' => 'Permissions fetched successfully',
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ]);
        }
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            $permission = Permission::create([
                'name' => $request->name,
                'guard_name' => 'api',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'permission' => $permission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $this->authorizeAdmin();
        try {
            $permission = Permission::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $permission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found',
            ], 404);
        }
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();
        $validator = Validator::make($request->all(), [
            'permission_id' => 'required|integer',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ]);
        }
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        DB::beginTransaction();
        try {
            $permission = Permission::findOrFail($request->permission_id);

            // Check if new name already exists
            $existingPermission = Permission::where('name', $request->name)
                ->where('id', '!=', $permission->id)
                ->first();

            if ($existingPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission name already exists',
                ], 422);
            }

            $permission->update([
                'name' => $request->name,
            ]);

            DB::commit();
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully',
                'permission' => $permission,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $this->authorizeAdmin();
        DB::beginTransaction();
        try {
            $permission = Permission::findOrFail($id);

            // Check if permission is in use
            if ($permission->roles()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete permission as it is assigned to roles',
                ], 422);
            }

            $permission->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function groupPermissions($permissions)
    {
        // Define common actions
        // $actions = ['view', 'create', 'edit', 'delete', 'print', 'export'];

        // Initialize grouped array
        $grouped = [];

        foreach ($permissions as $permission) {
            // Extract the module name from the permission
            $permissionName = $permission['name'];
            $module = '';
            $action = '';

            // Check if the permission matches any of our predefined controllers
            $controllers_list = [
                'main dashboard',
                'reports',
                'invoices',
                'roles',
                'medical reports',
                'patients',
                'price list',
                'tests',
                'test groups',
                'categories',
                'samples',
                'packages',
                'cultures',
                'test questions',
                'antibiotics',
                'users',
                'referrals',
                'contracts',
                'payments',
                'activities',
                'roles',
            ];

            // Find which controller this permission belongs to
            foreach ($controllers_list as $controller) {
                if (strpos($permissionName, $controller) === 0) {
                    $module = $controller;
                    $action = trim(str_replace($controller, '', $permissionName));
                    break;
                }
            }

            // If no match found, use the default approach
            if (empty($module)) {
                $parts = explode(' ', $permissionName);
                $action = array_pop($parts);
                $module = implode(' ', $parts);
            }

            // If this is a new module, initialize its array
            if (!isset($grouped[$module])) {
                $grouped[$module] = [
                    'name' => $module,
                    'permissions' => [],
                ];
            }

            // Add the permission to the module's permissions array
            $grouped[$module]['permissions'][] = [
                'id' => $permission['id'],
                'name' => $action,
                'full_name' => $permission['name'],
                'guard_name' => $permission['guard_name'],
            ];
        }

        // Sort modules alphabetically
        ksort($grouped);

        return array_values($grouped);
    }

    // Usage in your controller
    public function getGroupedPermissions()
    {
        $this->authorizeAdmin();
        $permissions = Permission::all();
        $groupedPermissions = $this->groupPermissions($permissions);

        return response()->json([
            'success' => true,
            'data' => $groupedPermissions,
        ]);
    }
}
