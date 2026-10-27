<?php

namespace App\Http\Controllers;

use App\Models\{Referal, User};
use App\Services\{AccountAccess, OwnerAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OwnerTeamController extends Controller
{
    private function lab(Request $request): User
    {
        $request->validate(['lab_id' => 'required|integer|min:1']);
        return app(OwnerAccess::class)->selected($request->user(), $request->integer('lab_id'))->first();
    }

    private function staff(User $lab)
    {
        return User::whereIn('id', app(AccountAccess::class)->tenantUserIds($lab))->where('id', '!=', $lab->id)
            ->whereNotIn('role_id', [1, 2, 3, 4, 5])->where('referral_portal_only', false)->where('owner_portal_only', false);
    }

    public function index(Request $request)
    {
        $lab = $this->lab($request);
        $access = app(AccountAccess::class);
        $roles = $access->roles($lab)->whereNotIn('id', [1, 2, 3, 4, 5])->with('permissions')->get()
            ->filter(fn ($r) => $access->canAssign($lab, $r))->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'permissions' => $r->permissions->pluck('name')])->values();
        return response()->json(['staff' => $this->staff($lab)->with('role:id,name')->orderBy('name')->get(['id', 'name', 'email', 'status', 'role_id']), 'roles' => $roles]);
    }

    public function store(Request $request) { return $this->save($request); }
    public function update(Request $request, int $id) { return $this->save($request, $id); }

    private function save(Request $request, ?int $id = null)
    {
        $lab = $this->lab($request);
        $staff = $id ? $this->staff($lab)->findOrFail($id) : new User;
        $data = $request->validate([
            'name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($staff->id)],
            'password' => ($id ? 'nullable' : 'required').'|string|min:10|max:128', 'status' => 'required|integer|in:0,1',
            'role_id' => 'required|integer|not_in:1,2,3,4,5',
        ]);
        $role = app(AccountAccess::class)->assignableRole($lab, $data['role_id']);
        DB::transaction(function () use ($staff, $data, $role, $lab) {
            $staff->fill(collect($data)->only(['name', 'email', 'status'])->all());
            if (! empty($data['password'])) $staff->password = $data['password'];
            if (! $staff->exists) $staff->fill(['creator_id' => $lab->id, 'email_verified_at' => now()]);
            $roleChanged = (int) $staff->role_id !== (int) $role->id;
            $staff->role_id = $role->id; $staff->save(); $staff->syncRoles([$role]);
            if ($roleChanged) $staff->syncPermissions([]);
            if ((int) $role->id === 6) Referal::firstOrCreate(['referral_id_fk' => $staff->id, 'lab_id_fk' => $lab->id], ['commission' => 0]);
            if (! empty($data['password']) || ! $staff->status || $roleChanged) $staff->tokens()->delete();
        });
        return response()->json(['id' => $staff->id, 'message' => 'تم حفظ حساب الموظف.'], $id ? 200 : 201);
    }
}
