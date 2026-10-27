<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class OwnerAccountController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless((int) $request->user()->role_id === 1 && ! $request->user()->owner_portal_only, 403);
    }

    public function index(Request $request)
    {
        $this->admin($request);
        $request->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $owners = User::where('owner_portal_only', true)
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%')))
            ->orderByDesc('id')->paginate(20, ['id', 'name', 'email', 'phone_number', 'status']);
        $links = DB::table('owner_labs')->whereIn('owner_id', $owners->pluck('id'))->get()->groupBy('owner_id');
        $owners->getCollection()->transform(function ($owner) use ($links) {
            $owner->lab_ids = ($links[$owner->id] ?? collect())->pluck('lab_id')->values();
            return $owner;
        });
        $labs = User::where('role_id', 2)->where('referral_portal_only', false)->where('owner_portal_only', false)->orderBy('name')->get(['id', 'name', 'status']);
        return response()->json(['owners' => $owners, 'labs' => $labs]);
    }

    public function store(Request $request) { return $this->save($request); }
    public function update(Request $request, int $id) { return $this->save($request, $id); }

    private function save(Request $request, ?int $id = null)
    {
        $this->admin($request);
        $owner = $id ? User::where('owner_portal_only', true)->findOrFail($id) : new User;
        $data = $request->validate([
            'name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($owner->id)],
            'password' => ($id ? 'nullable' : 'required').'|string|min:10|max:128', 'phone_number' => 'nullable|string|max:30',
            'status' => 'required|integer|in:0,1', 'lab_ids' => 'present|array|max:100',
            'lab_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('role_id', 2)->where('referral_portal_only', false)->where('owner_portal_only', false))],
        ]);
        DB::transaction(function () use ($data, $owner, $request) {
            $old = $owner->exists ? ['name' => $owner->name, 'email' => $owner->email, 'status' => $owner->status, 'lab_ids' => DB::table('owner_labs')->where('owner_id', $owner->id)->pluck('lab_id')->all()] : null;
            $owner->fill(collect($data)->only(['name', 'email', 'phone_number', 'status'])->all());
            if (! empty($data['password'])) $owner->password = $data['password'];
            $owner->forceFill(['owner_portal_only' => true, 'role_id' => null, 'creator_id' => null, 'email_verified_at' => now()])->save();
            $owner->syncRoles([]); $owner->syncPermissions([]);
            DB::table('owner_labs')->where('owner_id', $owner->id)->delete();
            foreach ($data['lab_ids'] as $lab) DB::table('owner_labs')->insert(['owner_id' => $owner->id, 'lab_id' => $lab, 'created_at' => now(), 'updated_at' => now()]);
            if (! empty($data['password']) || ! $owner->status) $owner->tokens()->delete();
            Activity::create(['log_name' => 'إدارة المالك', 'event' => $old ? 'updated' : 'created', 'description' => 'إدارة حساب المالك وربط المختبرات',
                'causer_type' => User::class, 'causer_id' => $request->user()->id, 'subject_type' => User::class, 'subject_id' => $owner->id,
                'audit_source' => 'server', 'audit_tenant_id' => $request->user()->id,
                'properties' => ['actor_name' => $request->user()->name, 'status' => 'success', 'old_value' => $old,
                    'new_value' => ['name' => $owner->name, 'email' => $owner->email, 'status' => $owner->status, 'lab_ids' => $data['lab_ids']]]]);
        });
        return response()->json(['id' => $owner->id, 'message' => 'تم حفظ حساب المالك والمختبرات المرتبطة.'], $id ? 200 : 201);
    }
}
