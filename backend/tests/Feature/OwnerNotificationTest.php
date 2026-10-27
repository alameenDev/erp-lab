<?php
namespace Tests\Feature;

use App\Models\{Invoice, OwnerNotification, OwnerPushSubscription, Patient, User};
use App\Services\{AuditTrail, InvoiceChangeArchive, OwnerNotifications, PortalPushTransport};
use Carbon\Carbon;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OwnerNotificationTest extends TestCase
{
    use DatabaseMigrations;
    private User $owner;
    private User $lab;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-27 12:00', 'Asia/Baghdad')->utc());
        $this->seed(ReferenceDataSeeder::class);
        $this->lab = $this->user('lab', 2); $this->owner = $this->user('owner');
        $this->owner->forceFill(['owner_portal_only' => true])->save();
        DB::table('owner_labs')->insert(['owner_id' => $this->owner->id, 'lab_id' => $this->lab->id]);
        $patient = $this->user('patient', 3);
        $this->patient = Patient::create(['user_id' => $patient->id, 'creator_id' => $this->lab->id, 'code' => 'NOTICE-P']);
        $this->actingAs($this->owner);
        $this->mock(PortalPushTransport::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test-public', 'privateKey' => 'test-private']);
            $mock->shouldReceive('send')->andReturn('accepted')->byDefault();
        });
    }
    private function user(string $name, ?int $role = null): User
    {
        return User::create(['name' => $name, 'email' => $name.'@notice.test', 'password' => 'long-owner-password', 'role_id' => $role, 'status' => 1, 'email_verified_at' => now()]);
    }
    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::create($attributes + ['lab_id_fk' => $this->lab->id, 'patient_id_fk' => $this->patient->id, 'total' => 10000, 'sub_total' => 10000, 'paid' => 999999, 'is_done' => false]);
    }
    private function payload(string $suffix = 'one'): array
    {
        $encode = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        return ['subscription' => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$suffix,
            'keys' => ['p256dh' => $encode("\x04".str_repeat('a', 64)), 'auth' => $encode(str_repeat('b', 16))]], 'invoice_enabled' => true, 'daily_enabled' => true];
    }
    private function subscribe(): OwnerPushSubscription
    {
        $this->postJson('/api/owner-portal/notifications/push/subscribe', $this->payload())->assertOk()->assertJsonPath('subscribed', true);
        return OwnerPushSubscription::firstOrFail();
    }
    private function mutate(Invoice $invoice, callable $mutation, int $status = 200): void
    {
        $request = Request::create('/api/invoices/update', 'PUT', ['id' => $invoice->id]);
        $request->setUserResolver(fn () => $this->lab);
        $audit = app(AuditTrail::class); $audit->begin($request); $mutation(); $audit->finish($status);
    }
    public function test_invoice_alerts_capture_committed_amount_changes_and_deletion_once_without_replaying_history(): void
    {
        $this->subscribe(); $i = $this->invoice();
        $this->mutate($i, fn () => $i->update(['total' => 12000]));
        $this->mutate($i, fn () => $i->update(['is_done' => true]));
        $this->assertSame(1, OwnerNotification::count());
        $notice = OwnerNotification::firstOrFail();
        $this->assertSame('invoice_amount', $notice->kind); $this->assertEquals(10000, $notice->data['before_total']);
        $this->assertEquals(12000, $notice->data['after_total']); $this->assertSame('lab', $notice->data['actor_name']);
        $this->assertStringNotContainsString('patient', $notice->body);
        $this->mutate($i, fn () => $i->delete());
        $this->assertSame(2, OwnerNotification::count());
        foreach (Activity::where('subject_type', Invoice::class)->get() as $a) app(InvoiceChangeArchive::class)->capture($a, true);
        $this->assertSame(2, OwnerNotification::count()); $this->assertSame(2, DB::table('owner_push_deliveries')->count());
        $legacy = Activity::create(['subject_type' => Invoice::class, 'subject_id' => 999, 'event' => 'deleted', 'description' => 'legacy', 'audit_tenant_id' => $this->lab->id, 'properties' => ['old_value' => ['total' => 5000]]]);
        app(InvoiceChangeArchive::class)->capture($legacy);
        $this->assertSame(2, OwnerNotification::count());
        DB::beginTransaction(); $j = $this->invoice(); $this->mutate($j, fn () => $j->update(['total' => 9000])); DB::rollBack();
        $this->assertSame(2, OwnerNotification::count());
    }
    public function test_inbox_and_push_recheck_owner_lab_access_and_cannot_cross_accounts(): void
    {
        $this->subscribe(); $i = $this->invoice(); $this->mutate($i, fn () => $i->delete()); $notice = OwnerNotification::firstOrFail();
        $this->getJson('/api/owner-portal/notifications')->assertOk()->assertJsonPath('unread', 1);
        $this->postJson('/api/owner-portal/notifications/'.$notice->id.'/read')->assertOk();
        $this->getJson('/api/owner-portal/notifications')->assertJsonPath('unread', 0);
        $other = $this->user('other'); $other->forceFill(['owner_portal_only' => true])->save(); $this->actingAs($other);
        $this->getJson('/api/owner-portal/notifications/'.$notice->id)->assertNotFound();
        $this->postJson('/api/owner-portal/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->getJson('/api/owner-portal/notifications?lab_id='.$this->lab->id)->assertNotFound();
        $this->actingAs($this->lab); $this->getJson('/api/owner-portal/notifications')->assertForbidden();
        DB::table('owner_labs')->delete();
        app(OwnerNotifications::class)->deliver();
        $this->assertDatabaseHas('owner_push_deliveries', ['status' => 'cancelled']);
        $this->actingAs($this->owner); $this->getJson('/api/owner-portal/notifications/'.$notice->id)->assertNotFound();
    }
    public function test_devices_are_encrypted_validated_and_explicitly_transferred_on_shared_browser(): void
    {
        $sub = $this->subscribe();
        $raw = DB::table('owner_push_subscriptions')->value('subscription');
        $this->assertStringNotContainsString('fcm.googleapis.com', $raw);
        $this->assertArrayNotHasKey('subscription', $sub->toArray());
        $bad = $this->payload(); $bad['subscription']['endpoint'] = 'https://127.0.0.1/private';
        $this->postJson('/api/owner-portal/notifications/push/subscribe', $bad)->assertUnprocessable();
        $i = $this->invoice(); $this->mutate($i, fn () => $i->delete());
        $other = $this->user('shared'); $other->forceFill(['owner_portal_only' => true])->save(); $this->actingAs($other);
        $endpoint = ['endpoint' => $this->payload()['subscription']['endpoint']];
        $this->postJson('/api/owner-portal/notifications/push/status', $endpoint)->assertOk()->assertJsonPath('subscribed', false);
        $this->postJson('/api/owner-portal/notifications/push/unsubscribe', $endpoint)->assertOk(); $this->assertSame(1, OwnerPushSubscription::count());
        $this->postJson('/api/owner-portal/notifications/push/subscribe', $this->payload())->assertOk();
        $this->assertSame($other->id, OwnerPushSubscription::firstOrFail()->owner_id);
        $this->assertSame(0, DB::table('owner_push_deliveries')->count());
    }
    public function test_daily_digest_is_per_lab_idempotent_uses_baghdad_dates_and_ledger_and_counts_referral_once(): void
    {
        $this->subscribe(); $doctor = $this->user('doctor', 5); $source = $this->user('source', 4);
        $i = $this->invoice(['referral_id_fk' => $doctor->id, 'from_lab_id_fk' => $source->id]);
        DB::table('invoices')->where('id', $i->id)->update(['created_at' => '2026-10-26 21:00:00']); // midnight Baghdad
        $i->paidDetails()->create(['amount' => 3000]);
        $later = $i->paidDetails()->create(['amount' => 2000]); DB::table('invoice_paid_details')->where('id', $later->id)->update(['created_at' => '2026-10-27 21:01:00']);
        $old = $this->invoice(); DB::table('invoices')->where('id', $old->id)->update(['created_at' => '2026-10-26 20:59:59']); $old->paidDetails()->create(['amount' => 4000]);
        $second = $this->user('second', 2); DB::table('owner_labs')->insert(['owner_id' => $this->owner->id, 'lab_id' => $second->id]);
        $this->travelTo(Carbon::parse('2026-10-28 00:04:00', 'Asia/Baghdad')->utc());
        $this->assertSame(0, app(OwnerNotifications::class)->daily());
        $this->travelTo(Carbon::parse('2026-10-28 00:05:00', 'Asia/Baghdad')->utc());
        $this->assertSame(2, app(OwnerNotifications::class)->daily()); $this->assertSame(0, app(OwnerNotifications::class)->daily());
        $n = OwnerNotification::where('lab_id', $this->lab->id)->firstOrFail(); $s = $n->data['summary'];
        foreach (['invoice_count' => 1, 'patient_count' => 1, 'paid' => 3000, 'balance' => 7000, 'collections_in_period' => 7000, 'referral_count' => 1, 'doctor_referrals' => 1, 'lab_referrals' => 1, 'pending' => 1] as $key => $value) $this->assertEquals($value, $s[$key], $key);
        $this->assertSame(0, OwnerNotification::where('lab_id', $second->id)->firstOrFail()->data['summary']['invoice_count']);
        $this->assertSame(2, DB::table('owner_push_deliveries')->count());
    }
    public function test_opt_out_password_disable_and_expired_devices_stop_push_but_keep_inbox(): void
    {
        $sub = $this->subscribe(); $i = $this->invoice(); $this->mutate($i, fn () => $i->delete());
        $sub->update(['invoice_enabled' => false]); app(OwnerNotifications::class)->deliver();
        $this->assertDatabaseHas('owner_push_deliveries', ['status' => 'cancelled']); $this->assertSame(1, OwnerNotification::count());
        $this->owner->password = 'changed-password-long'; $this->owner->save(); $this->assertSame(0, OwnerPushSubscription::count());
        $this->subscribe(); $this->owner->update(['status' => 0]); $this->assertSame(0, OwnerPushSubscription::count());
    }
    public function test_retry_backoff_expired_endpoint_and_closed_app_session_expiry(): void
    {
        $sub = $this->subscribe(); $i = $this->invoice(); $this->mutate($i, fn () => $i->delete());
        $this->mock(PortalPushTransport::class, function ($m) { $m->shouldReceive('keys')->andReturn(['publicKey' => 'k']); $m->shouldReceive('send')->once()->andReturn('retry'); });
        $this->assertSame(1, app(OwnerNotifications::class)->deliver()); $this->assertSame(0, app(OwnerNotifications::class)->deliver());
        $this->assertDatabaseHas('owner_push_deliveries', ['status' => 'retry', 'attempts' => 1]);
        $this->travel(9)->hours(); // Sanctum's 8-hour login expiry must not silence explicit device consent.
        $this->mock(PortalPushTransport::class, function ($m) { $m->shouldReceive('keys')->andReturn(['publicKey' => 'k']); $m->shouldReceive('send')->once()->andReturn('accepted'); });
        $this->assertSame(1, app(OwnerNotifications::class)->deliver()); $this->assertDatabaseHas('owner_push_deliveries', ['status' => 'accepted', 'attempts' => 2]);
        $j = $this->invoice(); $this->mutate($j, fn () => $j->delete());
        $this->mock(PortalPushTransport::class, function ($m) { $m->shouldReceive('keys')->andReturn(['publicKey' => 'k']); $m->shouldReceive('send')->once()->andReturn('expired'); });
        app(OwnerNotifications::class)->deliver(); $this->assertSame(0, OwnerPushSubscription::count());
    }
    public function test_explicit_logout_revokes_only_the_current_device_subscription(): void
    {
        $token = $this->owner->createToken('owner-phone');
        $other = $this->owner->createToken('owner-tablet');
        $this->subscribe()->update(['auth_token_id' => $token->accessToken->id]);
        $this->postJson('/api/owner-portal/notifications/push/subscribe', $this->payload('tablet'))->assertOk();
        OwnerPushSubscription::where('endpoint_hash', hash('sha256', $this->payload('tablet')['subscription']['endpoint']))->update(['auth_token_id' => $other->accessToken->id]);
        Auth::guard('web')->logout(); Auth::forgetGuards();
        $this->withToken($token->plainTextToken)->postJson('/api/user/logout')->assertOk();
        $this->assertSame(1, OwnerPushSubscription::count());
        $this->assertSame($other->accessToken->id, OwnerPushSubscription::firstOrFail()->auth_token_id);
    }
}
