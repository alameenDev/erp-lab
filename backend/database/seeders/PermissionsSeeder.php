<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions
        $permissions = \App\Services\PermissionCatalog::NAMES;

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        // Create and assign permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Create and assign permissions to roles
        $roles = [
            'Admin',
            'Lab',
            'Patient',
            'Branch Lab',
            'Doctor',
            'Sample Collector',
            'User',
        ];

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
            if ($role->name === 'Admin') {
                $role->givePermissionTo($permissions);
            } elseif ($role->name === 'Lab') {
                $role->givePermissionTo(array_values(array_filter($permissions,
                    fn ($name) => ! \Illuminate\Support\Str::startsWith($name, ['super admin ', 'subscriptions ']))));
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
