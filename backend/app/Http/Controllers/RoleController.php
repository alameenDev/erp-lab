<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private AccountAccess $access) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('roles view'), 403);
        $roles = $this->access->roles($request->user())->with('permissions')->latest()->get();
        foreach ($roles as $role) {
            $role->can_edit = $this->access->canEditRole($request->user(), $role);
            $role->can_delete = $role->can_edit && (int) $role->id > 7;
        }
        return response()->json(['success' => true, 'roles' => $roles]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->can('roles create'), 403);
        $data = $request->validate([
            'role' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'api')],
            'permissions' => 'present|array',
            'permissions.*' => 'required|integer|distinct',
        ]);
        $permissions = $this->access->selectedPermissions($actor, $data['permissions']);
        $role = DB::transaction(function () use ($actor, $data, $permissions) {
            $role = Role::create(['name' => $data['role'], 'guard_name' => 'api',
                'lab_id_fk' => (int) $actor->role_id === 1 ? null : $this->access->tenantId($actor)]);
            $role->syncPermissions($permissions);
            return $role;
        });
        return response()->json(['success' => true, 'role' => $role->load('permissions')], 201);
    }

    public function update(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->can('roles edit'), 403);
        $data = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'role' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'api')->ignore($request->integer('role_id'))],
            'permissions' => 'sometimes|array',
            'permissions.*' => 'required|integer|distinct',
        ]);
        $role = $this->access->editableRole($actor, $data['role_id']);
        $permissions = array_key_exists('permissions', $data)
            ? $this->access->selectedPermissions($actor, $data['permissions']) : null;
        DB::transaction(function () use ($role, $data, $permissions) {
            if (isset($data['role'])) $role->update(['name' => $data['role']]);
            if ($permissions !== null) $role->syncPermissions($permissions);
        });
        return response()->json(['success' => true, 'role' => $role->load('permissions')]);
    }

    public function destroy(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->can('roles delete'), 403);
        $data = $request->validate(['role_id' => 'required|integer|exists:roles,id']);
        $role = $this->access->editableRole($actor, $data['role_id']);
        abort_if((int) $role->id <= 7, 422, 'لا يمكن حذف أدوار النظام الأساسية.');
        DB::transaction(function () use ($role) {
            Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            abort_if($role->users()->exists() || User::withTrashed()->where('role_id', $role->id)->exists(), 422,
                'لا يمكن حذف دور مرتبط بحسابات. غيّر دور هذه الحسابات أولاً.');
            $role->delete();
        });
        return response()->json(['success' => true, 'message' => 'تم حذف الدور.']);
    }

    public function assignRole(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->can('users edit'), 403);
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'role_id' => 'required|integer|exists:roles,id']);
        $user = User::findOrFail($data['user_id']);
        $this->access->manageUser($actor, $user, true);
        $role = $this->access->assignableRole($actor, $data['role_id']);
        DB::transaction(function () use ($user, $role) {
            Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            $user->syncRoles([$role]);
            $user->update(['role_id' => $role->id]);
        });
        return response()->json(['success' => true, 'user' => $user->load(['role', 'roles.permissions', 'permissions'])]);
    }

    public function removeRole(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->can('users edit'), 403);
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'role_id' => 'required|integer|exists:roles,id']);
        $user = User::findOrFail($data['user_id']);
        $this->access->manageUser($actor, $user, true);
        $role = $this->access->assignableRole($actor, $data['role_id']);
        DB::transaction(function () use ($user, $role) {
            $user->removeRole($role);
            if ((int) $user->role_id === (int) $role->id) $user->update(['role_id' => null]);
        });
        return response()->json(['success' => true, 'user' => $user->load(['roles.permissions', 'permissions'])]);
    }
}
