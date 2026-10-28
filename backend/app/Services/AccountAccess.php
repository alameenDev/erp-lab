<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Shared tenant and delegation rules for account/role administration. */
class AccountAccess
{
    public function tenantId(User $user): int
    {
        $seen = [];
        while (! in_array((int) $user->role_id, [1, 2], true) && $user->creator_id) {
            abort_if(isset($seen[$user->id]), 403, 'Invalid account ownership.');
            $seen[$user->id] = true;
            $user = User::withTrashed()->find($user->creator_id) ?? abort(403, 'Account owner not found.');
        }
        return (int) $user->id;
    }

    public function tenantUserIds(User $user): array
    {
        $ids = [$this->tenantId($user)];
        $next = $ids;
        while ($next) {
            $next = User::withTrashed()->whereIn('creator_id', $next)
                ->where(fn ($q) => $q->whereNull('role_id')->orWhereNotIn('role_id', [1, 2]))
                ->whereNotIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids = array_merge($ids, $next);
        }
        return $ids;
    }

    public function permissions(User $actor): Collection
    {
        $query = Permission::where('guard_name', 'api');
        if ((int) $actor->role_id !== 1) {
            $query->where('name', 'not like', 'super admin %')->where('name', 'not like', 'subscriptions %');
            if ((int) $actor->role_id !== 2) {
                $query->whereIn('id', $actor->getAllPermissions()->pluck('id'));
            }
        }
        return $query->orderBy('name')->get();
    }

    public function roles(User $actor): Builder
    {
        $query = Role::where('guard_name', 'api');
        if ((int) $actor->role_id !== 1) {
            $ids = $this->tenantUserIds($actor);
            $query->where(fn ($q) => $q->whereNull('lab_id_fk')->orWhereIn('lab_id_fk', $ids));
        }
        return $query;
    }

    public function canAssign(User $actor, Role $role): bool
    {
        if ((int) $actor->role_id === 1) return true;
        if (in_array((int) $role->id, [1, 2], true)) return false;
        if ($role->lab_id_fk && ! in_array((int) $role->lab_id_fk, $this->tenantUserIds($actor), true)) return false;
        return $role->permissions->pluck('id')->diff($this->permissions($actor)->pluck('id'))->isEmpty();
    }

    public function assignableRole(User $actor, int $id): Role
    {
        $role = $this->roles($actor)->with('permissions')->findOrFail($id);
        abort_unless($this->canAssign($actor, $role), 403, 'لا يمكنك إسناد هذا الدور أو منح صلاحيات أعلى من صلاحياتك.');
        return $role;
    }

    public function canEditRole(User $actor, Role $role): bool
    {
        if ((int) $actor->role_id === 1) return true;
        if ((int) $actor->role_id === 2) return $role->lab_id_fk !== null && in_array((int) $role->lab_id_fk, $this->tenantUserIds($actor), true);
        return $role->lab_id_fk !== null && $this->canAssign($actor, $role);
    }

    public function editableRole(User $actor, int $id): Role
    {
        $role = $this->roles($actor)->with('permissions')->findOrFail($id);
        abort_unless($this->canEditRole($actor, $role), 403, 'يمكنك تعديل الأدوار الخاصة بمختبرك فقط، ضمن صلاحياتك.');
        return $role;
    }

    public function manageUser(User $actor, User $target, bool $changeRole = false): void
    {
        abort_if($changeRole && $target->collector_portal_only,403,'عدّل حساب المندوب من إدارة سحب العينات.');
        abort_if($changeRole && $actor->is($target), 403, 'لا يمكنك تغيير دور حسابك من هذه النافذة.');
        if ((int) $actor->role_id === 1) return;
        abort_unless(in_array((int) $target->id, $this->tenantUserIds($actor), true), 403);
        if ($actor->is($target)) return;
        abort_if(in_array((int) $target->role_id, [1, 2], true), 403);
        if ((int) $actor->role_id === 2) return;
        abort_unless($target->getAllPermissions()->pluck('id')->diff($this->permissions($actor)->pluck('id'))->isEmpty(), 403);
    }

    public function selectedPermissions(User $actor, array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $permissions = $this->permissions($actor)->whereIn('id', $ids)->values();
        if ($permissions->count() !== count($ids)) {
            throw ValidationException::withMessages(['permissions' => 'تتضمن القائمة صلاحيات غير موجودة أو لا يمكنك منحها.']);
        }
        return $permissions;
    }
}
