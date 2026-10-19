<?php

namespace Tests\Feature;

use App\Models\{Subscription, User};
use App\Services\{AccountAccess, PermissionCatalog};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Auth, DB};
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class AccountPermissionsTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->lab = $this->account('owner', 2);
        Subscription::create(['lab_id_fk' => $this->lab->id, 'plan_name' => 'Test', 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addYear()]);
        $this->actingAs($this->lab);
    }

    private function account(string $name, int $role, ?int $creator = null): User
    {
        $user = User::create(['name' => $name, 'email' => $name.'@example.test', 'password' => 'test-password',
            'role_id' => $role, 'creator_id' => $creator, 'email_verified_at' => now(), 'status' => 1]);
        $user->assignRole(Role::findOrFail($role));
        return $user;
    }

    private function customRole(string $name, array $permissions = [], ?int $lab = null): Role
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'api', 'lab_id_fk' => $lab ?? $this->lab->id]);
        $role->syncPermissions($permissions);
        return $role;
    }

    private function login(User $user): string
    {
        Auth::shouldUse('web');
        Auth::forgetGuards();
        $response = $this->postJson('/api/user/login', ['email' => $user->email, 'password' => 'test-password'])->assertOk();
        $token = $response->json('token');
        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $this->withToken($token);
        return $token;
    }

    public function test_create_custom_role_and_account_then_login_and_keep_session_after_forbidden_request(): void
    {
        $permission = Permission::findByName('patients view', 'api');
        $role = $this->postJson('/api/roles/create', ['role' => 'Reception', 'permissions' => [$permission->id]])
            ->assertCreated()->json('role.id');
        $id = $this->postJson('/api/user/create', ['name' => 'Receptionist', 'email' => 'reception@example.test',
            'password' => 'test-password', 'role_id' => $role])->assertCreated()->json('user.id');
        $staff = User::findOrFail($id);
        $this->assertTrue($staff->can('patients view'));
        $this->assertFalse($staff->can('reports view'));
        $this->login($staff);
        $this->getJson('/api/user/access')->assertOk()->assertJsonPath('permissions', ['patients view']);
        $this->getJson('/api/reports/dashboard-stats')->assertForbidden();
        $this->getJson('/api/user/access')->assertOk()->assertJsonPath('user.id', $id);
        $this->getJson('/api/patients')->assertOk();
    }

    public function test_updating_user_role_synchronizes_effective_permissions_and_keeps_direct_grants(): void
    {
        $old = $this->customRole('Old', ['patients view']);
        $new = $this->customRole('New', ['samples view']);
        $staff = $this->account('staff', $old->id, $this->lab->id);
        $staff->givePermissionTo('tests view');
        $this->postJson('/api/user/update', ['id' => $staff->id, 'role_id' => $new->id])->assertOk();
        $staff = $staff->fresh();
        $this->assertEquals($new->id, $staff->role_id);
        $this->assertEquals([$new->id], $staff->roles->pluck('id')->all());
        $this->assertFalse($staff->can('patients view'));
        $this->assertTrue($staff->can('samples view'));
        $this->assertTrue($staff->can('tests view'));
    }

    public function test_saving_an_existing_legacy_role_repairs_a_stale_role_pivot(): void
    {
        $old = $this->customRole('Legacy old', ['patients view']);
        $new = $this->customRole('Legacy new', ['samples view']);
        $staff = $this->account('legacy', $old->id, $this->lab->id);
        $staff->update(['role_id' => $new->id]);
        $this->postJson('/api/user/update', ['id' => $staff->id, 'role_id' => $new->id])->assertOk();
        $this->assertTrue($staff->fresh()->can('samples view'));
        $this->assertFalse($staff->fresh()->can('patients view'));
    }

    public function test_authenticated_permission_denials_are_forbidden_not_unauthenticated(): void
    {
        $role = $this->customRole('No access');
        $staff = $this->account('no-access', $role->id, $this->lab->id);
        $this->actingAs($staff);
        foreach (['patients', 'invoices', 'tests', 'test_groups', 'samples', 'categories', 'packages',
            'cultures', 'antibiotics', 'roles', 'user/show', 'reports/dashboard-stats', 'reports', 'devices'] as $path) {
            $this->getJson('/api/'.$path)->assertForbidden();
        }
        $this->getJson('/api/user/access')->assertOk();
    }

    public function test_role_assignment_and_removal_keep_legacy_role_id_consistent(): void
    {
        $one = $this->customRole('One', ['patients view']);
        $two = $this->customRole('Two', ['samples view']);
        $staff = $this->account('staff', $one->id, $this->lab->id);
        $this->postJson('/api/roles/assign-role', ['user_id' => $staff->id, 'role_id' => $two->id])->assertOk();
        $this->assertTrue($staff->fresh()->can('samples view'));
        $this->assertFalse($staff->fresh()->can('patients view'));
        $this->postJson('/api/roles/remove-role', ['user_id' => $staff->id, 'role_id' => $two->id])->assertOk();
        $this->assertNull($staff->fresh()->role_id);
        $this->assertCount(0, $staff->fresh()->roles);
        $this->postJson('/api/roles/assign-role', ['user_id' => $staff->id, 'role_id' => $one->id])->assertOk();
        $this->assertTrue($staff->fresh()->can('patients view'));
    }

    public function test_empty_role_and_direct_permissions_are_returned_correctly_after_login_and_reload(): void
    {
        $empty = $this->customRole('Empty');
        $staff = $this->account('empty-staff', $empty->id, $this->lab->id);
        $this->login($staff);
        $this->getJson('/api/user/access')->assertOk()->assertJsonPath('permissions', []);
        $staff->givePermissionTo('samples view');
        Auth::forgetGuards();
        $this->getJson('/api/user/access')->assertOk()->assertJsonPath('permissions', ['samples view']);
        $this->getJson('/api/permissions?role_id=1')->assertOk()->assertJsonPath('permissions.0.permissions.0.full_name', 'samples view');
    }

    public function test_live_permission_revocation_is_visible_to_an_existing_token(): void
    {
        $role = $this->customRole('Live', ['patients view']);
        $staff = $this->account('live-staff', $role->id, $this->lab->id);
        $this->login($staff);
        $this->getJson('/api/patients')->assertOk();
        $role->syncPermissions([]);
        Auth::forgetGuards();
        $this->getJson('/api/user/access')->assertOk()->assertJsonPath('permissions', []);
        $this->getJson('/api/patients')->assertForbidden();
    }

    public function test_staff_created_by_another_staff_uses_the_lab_subscription_and_tenant(): void
    {
        $role = $this->customRole('Delegated', ['patients view', 'users view', 'users create']);
        $manager = $this->account('manager', $role->id, $this->lab->id);
        $child = $this->account('child', $role->id, $manager->id);
        $grandchild = $this->account('grandchild', $role->id, $child->id);
        $this->login($grandchild);
        $this->getJson('/api/user/access')->assertOk();
        $this->assertSame($this->lab->id, app(AccountAccess::class)->tenantId($grandchild));
        $this->assertContains($grandchild->id, app(AccountAccess::class)->tenantUserIds($manager));
    }

    public function test_user_editor_can_get_assignable_roles_without_roles_view(): void
    {
        $role = $this->customRole('User editor', ['users view', 'users create', 'users edit', 'patients view']);
        $staff = $this->account('editor', $role->id, $this->lab->id);
        $this->actingAs($staff)->getJson('/api/roles/show')->assertOk();
        $this->getJson('/api/roles')->assertForbidden();
        $this->getJson('/api/permissions?catalog=1')->assertForbidden();
    }

    public function test_cannot_create_or_change_accounts_without_the_required_permission(): void
    {
        $role = $this->customRole('Viewer', ['patients view']);
        $staff = $this->account('viewer', $role->id, $this->lab->id);
        $this->actingAs($staff)->postJson('/api/user/create', ['name' => 'Denied', 'email' => 'denied@example.test',
            'password' => 'test-password', 'role_id' => 7])->assertForbidden();
        $this->postJson('/api/roles/assign-role', ['user_id' => $this->lab->id, 'role_id' => 7])->assertForbidden();
        $this->postJson('/api/roles/remove-role', ['user_id' => $this->lab->id, 'role_id' => 2])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'denied@example.test']);
    }

    public function test_cross_lab_roles_and_users_and_privileged_roles_are_rejected(): void
    {
        $other = $this->account('other-owner', 2);
        $foreignRole = $this->customRole('Foreign', ['patients view'], $other->id);
        $foreignUser = $this->account('foreign-staff', $foreignRole->id, $other->id);
        $localRole = $this->customRole('Local', ['patients view']);
        $local = $this->account('local', $localRole->id, $this->lab->id);
        $this->postJson('/api/roles/assign-role', ['user_id' => $local->id, 'role_id' => $foreignRole->id])->assertNotFound();
        $this->postJson('/api/roles/assign-role', ['user_id' => $foreignUser->id, 'role_id' => $localRole->id])->assertForbidden();
        foreach ([1, 2] as $id) {
            $this->postJson('/api/user/create', ['name' => 'No', 'email' => "no$id@example.test", 'password' => 'test-password', 'role_id' => $id])->assertForbidden();
            $this->postJson('/api/user/update', ['id' => $local->id, 'role_id' => $id])->assertForbidden();
        }
        $this->putJson('/api/roles/update', ['role_id' => $foreignRole->id, 'permissions' => []])->assertNotFound();
        $this->postJson('/api/roles/assign-role', ['user_id' => $this->lab->id, 'role_id' => $localRole->id])->assertForbidden();
    }

    public function test_delegation_cannot_grant_permissions_the_actor_does_not_have(): void
    {
        $role = $this->customRole('Delegator', ['roles create', 'roles edit', 'users edit', 'patients view']);
        $staff = $this->account('delegator', $role->id, $this->lab->id);
        $high = $this->customRole('Higher', ['reports view']);
        $low = $this->account('lower', 7, $this->lab->id);
        $this->actingAs($staff);
        $this->postJson('/api/roles/create', ['role' => 'Escalation', 'permissions' => [Permission::findByName('reports view', 'api')->id]])->assertUnprocessable();
        $this->postJson('/api/roles/assign-role', ['user_id' => $low->id, 'role_id' => $high->id])->assertForbidden();
        $this->postJson('/api/user/update', ['id' => $this->lab->id, 'password' => 'changed-password'])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Escalation']);
    }

    public function test_role_validation_empty_selection_and_delete_in_use_are_transaction_safe(): void
    {
        $role = $this->customRole('Validation', ['patients view']);
        $this->postJson('/api/roles/create', ['role' => 'Validation', 'permissions' => []])->assertUnprocessable();
        $this->putJson('/api/roles/update', ['role_id' => $role->id, 'role' => 'Invalid', 'permissions' => [999999]])->assertUnprocessable();
        $this->assertSame('Validation', $role->fresh()->name);
        $this->putJson('/api/roles/update', ['role_id' => $role->id, 'permissions' => []])->assertOk();
        $this->assertCount(0, $role->fresh()->permissions);
        $staff = $this->account('assigned', $role->id, $this->lab->id);
        $this->deleteJson('/api/roles/delete', ['role_id' => $role->id])->assertUnprocessable();
        $this->assertSame(0, DB::transactionLevel());
        $this->postJson('/api/roles/remove-role', ['user_id' => $staff->id, 'role_id' => $role->id])->assertOk();
        $this->deleteJson('/api/roles/delete', ['role_id' => $role->id])->assertOk();
    }

    public function test_permission_catalog_contains_actual_features_and_hides_system_permissions_from_lab(): void
    {
        $names = collect($this->getJson('/api/permissions?catalog=1')->assertOk()->json('permissions'))->flatMap(fn ($group) => $group['permissions'])->pluck('full_name');
        foreach (['lab settings edit', 'doctors view', 'promo codes create', 'devices view', 'inventory view', 'accounting reports view'] as $name) {
            $this->assertContains($name, $names);
        }
        $this->assertNotContains('super admin dashboard', $names);
        $this->assertNotContains('subscriptions edit', $names);
        foreach (PermissionCatalog::NAMES as $name) $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'api']);
    }

    public function test_missing_catalog_upgrade_is_idempotent_and_does_not_expand_custom_roles(): void
    {
        $role = $this->customRole('Unchanged', ['patients view']);
        Permission::findByName('doctors edit', 'api')->delete();
        $migration = require database_path('migrations/2026_10_19_000001_complete_permission_catalog.php');
        $migration->up(); $migration->up();
        $this->assertEquals(['patients view'], $role->fresh()->permissions->pluck('name')->all());
        $this->assertSame(1, Permission::where('name', 'doctors edit')->where('guard_name', 'api')->count());
    }

    public function test_new_feature_permissions_enforce_view_and_write_separately(): void
    {
        $role = $this->customRole('Feature viewer', ['doctors view', 'promo codes view']);
        $staff = $this->account('feature-viewer', $role->id, $this->lab->id);
        $this->actingAs($staff)->getJson('/api/doctors')->assertOk();
        $this->getJson('/api/promo-codes')->assertOk();
        $this->postJson('/api/doctors', ['name' => 'Denied'])->assertForbidden();
        $this->postJson('/api/promo-codes', [])->assertForbidden();
        $this->postJson('/api/lab-settings', ['lab_display_name' => 'Denied'])->assertForbidden();
        $this->getJson('/api/lab-settings')->assertOk();
    }

    public function test_main_dashboard_permission_does_not_grant_financial_reports(): void
    {
        $role = $this->customRole('Dashboard only', ['main dashboard view']);
        $staff = $this->account('dashboard-staff', $role->id, $this->lab->id);
        $this->actingAs($staff)->getJson('/api/reports/dashboard-stats')->assertOk();
        $this->getJson('/api/reports')->assertForbidden();
    }

    public function test_inactive_account_and_expired_subscription_are_still_denied(): void
    {
        $role = $this->customRole('Active check', ['patients view']);
        $staff = $this->account('disabled', $role->id, $this->lab->id);
        $staff->update(['status' => 0]);
        $this->actingAs($staff)->getJson('/api/user/access')->assertUnauthorized();
        $staff->update(['status' => 1]);
        Subscription::where('lab_id_fk', $this->lab->id)->update(['end_date' => today()->subDay()]);
        Auth::shouldUse('web'); Auth::forgetGuards();
        $this->postJson('/api/user/login', ['email' => $staff->email, 'password' => 'test-password'])
            ->assertForbidden()->assertJsonPath('subscription_expired', true);
    }
}
