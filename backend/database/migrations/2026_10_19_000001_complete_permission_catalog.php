<?php

use App\Services\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (PermissionCatalog::NAMES as $name) {
            // Add only missing definitions; retain every existing role and direct grant.
            if (DB::table('permissions')->where(['name' => $name, 'guard_name' => 'api'])->exists()) continue;
            $id = DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('roles')->whereNull('lab_id_fk')->where('guard_name', 'api')->whereIn('name', ['Admin', 'Lab'])->get(['id', 'name']) as $role) {
                if ($role->name === 'Lab' && \Illuminate\Support\Str::startsWith($name, ['super admin ', 'subscriptions '])) continue;
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $id]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permission definitions may have acquired user-selected assignments; retain them.
    }
};
