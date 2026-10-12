<?php

namespace Tests\Feature;

use App\Models\{Invoice, Patient, Plan, Subscription, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SubscriptionRenewalTest extends TestCase
{
    use DatabaseMigrations;

    private User $admin;
    private User $lab;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(12, 0));
        $this->seed(ReferenceDataSeeder::class);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'renew-admin@example.test', 'password' => 'test-password', 'role_id' => 1]);
        $this->lab = User::create(['name' => 'Renewal lab', 'email' => 'renew-lab@example.test', 'password' => 'test-password',
            'role_id' => 2, 'status' => 1, 'email_verified_at' => now()]);
        $plan = Plan::create(['name' => 'Standard', 'name_ar' => 'القياسية', 'monthly_price' => 25000, 'yearly_price' => 250000]);
        $this->subscription = Subscription::create(['lab_id_fk' => $this->lab->id, 'plan_id_fk' => $plan->id,
            'plan_name' => 'Standard', 'status' => 'trial', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
            'price' => 25000, 'currency' => 'IQD', 'max_users' => 5, 'max_invoices_per_month' => 2000, 'notes' => 'Keep these notes']);
    }

    private function preview(): array
    {
        return $this->getJson('/api/subscriptions/'.$this->subscription->id.'/renewal')->assertOk()->json();
    }

    private function renew(array $preview, int $months = 1)
    {
        return $this->postJson('/api/subscriptions/'.$this->subscription->id.'/renew', ['months' => $months, 'version' => $preview['version']]);
    }

    public function test_admin_renews_expired_trial_and_restores_lab_login_with_an_audit_trail(): void
    {
        $login = ['email' => $this->lab->email, 'password' => 'test-password'];
        $this->postJson('/api/user/login', $login)->assertForbidden()->assertJsonPath('subscription_expired', true);
        $this->actingAs($this->admin);
        $before = $this->subscription->getAttributes();
        $preview = $this->preview();
        $this->assertSame('expired', $preview['subscription']['effective_status']);
        $this->assertSame('2026-10-12', $preview['renewal_start_date']);
        $this->assertSame('2026-11-11', $preview['options'][0]['end_date']);
        $this->renew($preview)->assertOk()->assertJsonPath('subscription.status', 'active');
        $renewed = $this->subscription->fresh();
        $this->assertSame('2026-10-12', $renewed->start_date->toDateString());
        $this->assertSame('2026-11-11', $renewed->end_date->toDateString());
        foreach (['lab_id_fk', 'plan_id_fk', 'plan_name', 'price', 'currency', 'max_users', 'max_invoices_per_month', 'notes'] as $field) {
            $this->assertEquals($before[$field], $renewed->$field);
        }
        $this->assertDatabaseCount('subscriptions', 1);
        $log = Activity::where('subject_type', Subscription::class)->where('subject_id', $renewed->id)->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('تجديد اشتراك المختبر', $log->description);
        $this->assertEquals($this->admin->id, $log->causer_id);
        $changes = collect($log->properties['changes']);
        $this->assertSame('trial', $changes->firstWhere('field', 'status')['before']);
        $this->assertSame('active', $changes->firstWhere('field', 'status')['after']);
        $this->assertNotNull($changes->firstWhere('field', 'end_date'));
        Auth::forgetGuards();
        $this->postJson('/api/user/login', $login)->assertOk();
        $this->actingAs($this->lab)->getJson('/api/subscription/my')->assertOk()->assertJsonPath('has_subscription', true)->assertJsonPath('expired', false);
    }

    public function test_active_subscription_preserves_remaining_days_and_duplicate_confirmations_do_not_extend_twice(): void
    {
        $this->subscription->update(['status' => 'active', 'end_date' => '2026-10-20']);
        $this->actingAs($this->admin);
        $preview = $this->preview();
        $this->assertTrue($preview['continues_current_term']);
        $this->assertSame('2026-10-21', $preview['renewal_start_date']);
        $this->renew($preview, 3)->assertOk();
        $this->renew($preview, 3)->assertStatus(409);
        $this->assertSame('2027-01-20', $this->subscription->fresh()->end_date->toDateString());
        $this->assertSame('2026-09-01', $this->subscription->fresh()->start_date->toDateString());
        $this->assertSame(1, Activity::where('subject_type', Subscription::class)->where('event', 'updated')->count());
    }

    public function test_preview_covers_expiry_day_month_end_leap_year_and_stale_edits(): void
    {
        $this->actingAs($this->admin);
        $this->subscription->update(['status' => 'active', 'end_date' => '2026-10-12']);
        $this->assertSame('2026-10-13', $this->preview()['renewal_start_date']);
        $oldPreview = $this->preview();
        $this->subscription->update(['max_users' => 9]);
        $this->renew($oldPreview)->assertStatus(409);
        $newPreview = $this->preview();
        $this->travel(1)->days();
        $this->renew($newPreview)->assertStatus(409);
        $this->travelTo(now()->setDate(2028, 1, 31));
        $preview = $this->preview();
        $this->assertSame('2028-02-28', $preview['options'][0]['end_date']);
        $this->renew($preview)->assertOk();
        $this->assertSame('2028-02-28', $this->subscription->fresh()->end_date->toDateString());
    }

    public function test_non_admins_and_anonymous_users_cannot_preview_or_renew(): void
    {
        $url = '/api/subscriptions/'.$this->subscription->id;
        $this->getJson($url.'/renewal')->assertUnauthorized();
        $this->postJson($url.'/renew', [])->assertUnauthorized();
        foreach ([2, 3, 4, 5, 6] as $role) {
            $user = User::create(['name' => 'Other user', 'email' => "renew-role-$role@example.test", 'password' => 'test-password', 'role_id' => $role]);
            $this->actingAs($user)->getJson($url.'/renewal')->assertForbidden();
            $this->postJson($url.'/renew', [])->assertForbidden();
        }
        $this->assertSame('trial', $this->subscription->fresh()->status);
    }

    public function test_invalid_duration_suspended_deleted_and_historical_subscriptions_are_rejected(): void
    {
        $this->actingAs($this->admin);
        $preview = $this->preview();
        foreach ([0, -1, 2, 13] as $months) $this->renew($preview, $months)->assertUnprocessable();
        $this->subscription->update(['status' => 'suspended']);
        $this->getJson('/api/subscriptions/'.$this->subscription->id.'/renewal')->assertUnprocessable();
        $this->renew($preview)->assertUnprocessable();
        $this->subscription->update(['status' => 'expired', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $this->renew($preview)->assertUnprocessable();
        $this->subscription->update(['start_date' => '2026-09-01']);
        $latest = $this->subscription->replicate();
        $latest->save();
        $this->renew($preview)->assertStatus(409);
        $latest->delete();
        $this->lab->delete();
        $this->renew($preview)->assertUnprocessable();
        $this->subscription->delete();
        $this->renew($preview)->assertNotFound();
    }

    public function test_expired_filters_and_lab_details_work_with_real_invoice_relations(): void
    {
        $this->actingAs($this->admin);
        $patient = Patient::create(['user_id' => $this->lab->id, 'creator_id' => $this->lab->id, 'code' => 'RENEW-1']);
        Invoice::create(['patient_id_fk' => $patient->id, 'lab_id_fk' => $this->lab->id, 'total' => 10000, 'paid' => 3000]);
        $this->getJson('/api/subscriptions?status=expired')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.effective_status', 'expired');
        $this->getJson('/api/subscriptions?status=trial')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/super-admin/labs?subscription_status=expired')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/super-admin/lab-detail/'.$this->lab->id)->assertOk()
            ->assertJsonPath('recent_invoices.0.patient.user.name', $this->lab->name);
        $this->renew($this->preview(), 12)->assertOk();
        $this->getJson('/api/subscriptions?status=expired')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/super-admin/labs?subscription_status=active')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/super-admin/lab-detail/'.$this->lab->id)->assertOk()->assertJsonPath('lab.subscription.effective_status', 'active');
    }

    public function test_old_inactive_record_cannot_hide_successful_renewal_from_lab(): void
    {
        $this->subscription->update(['status' => 'expired', 'end_date' => '2030-01-01']);
        $current = $this->subscription->replicate();
        $current->end_date = '2026-09-30';
        $current->save();
        $this->subscription = $current;
        $this->actingAs($this->admin);
        $this->renew($this->preview(), 6)->assertOk();
        $this->actingAs($this->lab)->getJson('/api/subscription/my')->assertOk()
            ->assertJsonPath('has_subscription', true)->assertJsonPath('subscription.id', $current->id);
    }
}
