<?php
namespace Tests\Feature;

use App\Models\{Invoice, InvoiceTestRel, LabSetting, Patient, PortalAccessToken, PortalLabNotificationSetting, PortalNotification, PortalNotificationCampaign, PortalPushDelivery, PortalPushSubscription, User};
use App\Services\{PortalCampaigns, PortalNotifications, PortalPushTransport, ReportReadiness};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LabNotificationTest extends TestCase
{
    use DatabaseMigrations;
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $transport = \Mockery::mock(PortalPushTransport::class);
        $transport->shouldReceive('keys')->andReturn(['publicKey' => 'public-test', 'privateKey' => 'PRIVATE_TEST_KEY']);
        $transport->shouldReceive('send')->andReturnUsing(function ($subscription, $payload) { $this->sent[] = $payload; return 'accepted'; });
        $this->app->instance(PortalPushTransport::class, $transport);
        config(['app.frontend_url' => 'https://lab.example.test']);
    }

    private function lab(): User
    {
        return User::create(['name' => 'Synthetic Lab', 'email' => uniqid().'@example.test', 'password' => 'test-password-123', 'role_id' => 2]);
    }

    private function subscriber(User $lab, bool $results = true, bool $offers = true): array
    {
        $user = User::create(['name' => 'Synthetic Patient', 'email' => uniqid().'@example.test', 'password' => 'test-password-123', 'role_id' => 3, 'creator_id' => $lab->id]);
        $patient = Patient::create(['user_id' => $user->id, 'creator_id' => $lab->id, 'code' => uniqid('P')]);
        $access = PortalAccessToken::create(['patient_id_fk' => $patient->id, 'token' => PortalAccessToken::generatePlainToken(), 'expires_at' => now()->addDay()]);
        $endpoint = 'https://web.push.apple.com/'.Str::random(20);
        $subscription = PortalPushSubscription::create(['patient_id' => $patient->id, 'lab_id' => $lab->id, 'portal_access_token_id' => $access->id,
            'endpoint_hash' => hash('sha256', $endpoint), 'subscription' => ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'test', 'auth' => 'test']],
            'results_enabled' => $results, 'offers_enabled' => $offers]);
        return [$patient, $access, $subscription];
    }

    private function invoice(User $lab, Patient $patient): array
    {
        $invoice = Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id, 'is_done' => false, 'barcode' => 'SYNTHETIC-14']);
        $test = DB::table('tests')->insertGetId(['name' => 'Synthetic test', 'lab_id_fk' => $lab->id]);
        $row = InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'test_id_fk' => $test, 'is_done' => false, 'result' => '0']);
        return [$invoice, $row];
    }

    private function campaign(array $replace = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'kind' => 'offer', 'audience' => 'all', 'title' => 'Lab announcement', 'body' => 'Synthetic message'], $replace);
    }

    public function test_settings_are_private_per_lab_and_only_owners_or_explicit_administrators_manage_them(): void
    {
        $one = $this->lab(); $two = $this->lab();
        $this->getJson('/api/lab-notifications/settings')->assertUnauthorized();
        $this->actingAs($one)->getJson('/api/lab-notifications/settings')->assertOk()->assertJsonPath('settings.results_enabled', true)->assertDontSee('PRIVATE_TEST_KEY');
        $settings = array_replace(PortalLabNotificationSetting::defaults(), ['result_title' => 'Custom lab title', 'enabled' => false]);
        $this->putJson('/api/lab-notifications/settings', $settings)->assertOk();
        $this->getJson('/api/lab-notifications/settings?lab_id='.$two->id)->assertForbidden();
        $this->putJson('/api/lab-notifications/settings', $settings + ['lab_id' => $two->id])->assertForbidden();
        $this->actingAs($two)->getJson('/api/lab-notifications/settings')->assertOk()->assertJsonPath('settings.enabled', true)
            ->assertJsonPath('settings.result_title', PortalLabNotificationSetting::defaults()['result_title']);
        $staff = User::create(['name' => 'Staff', 'email' => uniqid().'@example.test', 'password' => 'test-password-123', 'role_id' => 7, 'creator_id' => $one->id]);
        $this->actingAs($staff)->getJson('/api/lab-notifications/settings')->assertForbidden();
        $this->postJson('/api/lab-notifications/campaigns', $this->campaign())->assertForbidden();
        $staff->update(['role_id' => 1]);
        $this->actingAs($staff)->getJson('/api/lab-notifications/settings')->assertUnprocessable();
        $this->getJson('/api/lab-notifications/settings?lab_id='.$one->id)->assertOk()->assertJsonPath('settings.result_title', 'Custom lab title');
    }

    public function test_campaign_is_idempotent_and_expands_only_for_current_consents_in_the_selected_lab(): void
    {
        $lab = $this->lab(); $other = $this->lab();
        [$eligible, $access, $subscription] = $this->subscriber($lab);
        [$optedOut] = $this->subscriber($lab, true, false);
        [, $expired] = $this->subscriber($lab); $expired->update(['expires_at' => now()->subMinute()]);
        $this->subscriber($other);
        $this->actingAs($lab);
        $payload = $this->campaign();
        $id = $this->postJson('/api/lab-notifications/campaigns', $payload)->assertAccepted()->assertJsonPath('campaign.audience_count', 1)->json('campaign.id');
        $this->postJson('/api/lab-notifications/campaigns', $payload)->assertAccepted()->assertJsonPath('campaign.id', $id);
        $this->postJson('/api/lab-notifications/campaigns', array_replace($payload, ['body' => 'Changed payload']))->assertStatus(409);
        $this->assertDatabaseCount('portal_notification_campaigns', 1);
        $this->assertDatabaseCount('portal_notifications', 0);
        app(PortalNotifications::class)->deliver();
        $this->assertCount(1, $this->sent);
        $this->assertSame('https://lab.example.test/portal/'.$access->token, $this->sent[0]['url']);
        $this->assertDatabaseHas('portal_notifications', ['patient_id' => $eligible->id, 'lab_id' => $lab->id]);
        $this->assertDatabaseMissing('portal_notifications', ['patient_id' => $optedOut->id]);
        app(PortalNotifications::class)->deliver(); $this->assertCount(1, $this->sent);
        $this->getJson('/api/lab-notifications/history')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.counts.accepted', 1)
            ->assertDontSee($access->token)->assertDontSee($subscription->subscription['endpoint'])->assertDontSee('PRIVATE_TEST_KEY');
        $notice = PortalNotification::firstOrFail();
        $this->actingAs($other)->getJson('/api/lab-notifications/history/'.$notice->id)->assertNotFound();
        $this->postJson('/api/lab-notifications/history/'.$notice->id.'/retry')->assertNotFound();
        $this->postJson('/api/lab-notifications/campaigns/'.$id.'/cancel')->assertNotFound();
    }

    public function test_recipient_search_filters_otp_expired_and_moved_patients_and_keeps_staff_owned_patients(): void
    {
        $lab = $this->lab(); $other = $this->lab();
        [$patient, $access] = $this->subscriber($lab);
        LabSetting::create(['lab_id_fk' => $lab->id, 'loyalty_config' => ['require_otp' => true]]);
        $this->actingAs($lab)->getJson('/api/lab-notifications/patients?kind=offer')->assertOk()->assertJsonCount(0);
        $access->update(['verified_at' => now()]);
        $staff = User::create(['name' => 'Branch', 'email' => uniqid().'@example.test', 'password' => 'test-password-123', 'role_id' => 4, 'creator_id' => $lab->id]);
        $patient->update(['creator_id' => $staff->id]);
        $this->getJson('/api/lab-notifications/patients?kind=offer')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $patient->id)->assertDontSee($access->token);
        $patient->update(['creator_id' => $other->id]);
        $this->getJson('/api/lab-notifications/patients?kind=offer')->assertOk()->assertJsonCount(0);
        $this->postJson('/api/lab-notifications/campaigns', $this->campaign(['audience' => 'patient', 'patient_id' => $patient->id]))->assertUnprocessable();
    }

    public function test_cancel_and_opt_out_after_preparation_prevent_dispatch_and_test_is_single_patient_only(): void
    {
        $lab = $this->lab(); [$patient, , $subscription] = $this->subscriber($lab);
        $this->actingAs($lab);
        $id = $this->postJson('/api/lab-notifications/campaigns', $this->campaign())->assertAccepted()->json('campaign.id');
        app(PortalCampaigns::class)->prepare();
        $this->postJson('/api/lab-notifications/campaigns/'.$id.'/cancel')->assertOk();
        app(PortalNotifications::class)->deliver(); $this->assertSame([], $this->sent);
        $this->assertDatabaseHas('portal_push_deliveries', ['status' => 'cancelled', 'status_reason' => 'campaign_cancelled']);
        $this->postJson('/api/lab-notifications/campaigns', $this->campaign())->assertAccepted();
        app(PortalCampaigns::class)->prepare(); $subscription->update(['offers_enabled' => false]);
        app(PortalNotifications::class)->deliver(); $this->assertSame([], $this->sent);
        $this->assertDatabaseHas('portal_push_deliveries', ['status' => 'cancelled', 'status_reason' => 'patient_opted_out']);
        $this->postJson('/api/lab-notifications/campaigns', $this->campaign(['kind' => 'test']))->assertUnprocessable();
        $this->postJson('/api/lab-notifications/campaigns', $this->campaign(['kind' => 'test', 'audience' => 'patient', 'patient_id' => $patient->id]))->assertAccepted();
        app(PortalNotifications::class)->deliver(); $this->assertCount(1, $this->sent);
    }

    public function test_result_notice_requires_every_stored_analysis_and_nested_culture_then_sends_once(): void
    {
        $lab = $this->lab(); [$patient, $access] = $this->subscriber($lab);
        [$invoice, $row] = $this->invoice($lab, $patient);
        $groupId = DB::table('test_groups')->insertGetId(['lab_id_fk' => $lab->id, 'group_name' => 'Synthetic group']);
        $group = InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'test_group_id_fk' => $groupId, 'is_done' => true,
            'test_group_tests' => [['id' => 1, 'is_done' => true]], 'test_group_cultures' => [['id' => 2, 'is_done' => false]]]);
        $row->update(['is_done' => true]); $invoice->update(['is_done' => true]);
        $this->assertDatabaseCount('portal_notifications', 0);
        $this->getJson('/api/invoices/public/'.$invoice->id)->assertForbidden();
        $this->getJson('/api/portal/'.$access->token)->assertOk()->assertJsonPath('reports.0.status', 'pending')->assertJsonPath('reports.0.view_url', null);
        $group->update(['test_group_cultures' => [['id' => 2, 'is_done' => true]]]);
        PortalLabNotificationSetting::create(['lab_id_fk' => $lab->id] + array_replace(PortalLabNotificationSetting::defaults(), ['result_title' => 'Ready from this lab']));
        $invoice->update(['notes' => 'Approval saved']); // repair older invoices already marked done
        $this->assertDatabaseCount('portal_notifications', 1);
        $this->assertDatabaseHas('portal_notifications', ['invoice_id' => $invoice->id, 'title' => 'Ready from this lab']);
        app(PortalNotifications::class)->deliver();
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('بوابة المريض', $this->sent[0]['body']);
        $invoice->update(['notes' => 'Saved again']);
        $invoice->update(['is_done' => false]); $invoice->update(['is_done' => true]);
        app(PortalNotifications::class)->deliver(); $this->assertCount(1, $this->sent);
        $this->getJson('/api/portal/'.$access->token)->assertOk()->assertJsonPath('reports.0.status', 'ready');
    }

    public function test_partial_result_save_and_unfinished_package_do_not_mark_invoice_complete(): void
    {
        $lab = $this->lab(); [$patient] = $this->subscriber($lab);
        [$invoice, $row] = $this->invoice($lab, $patient);
        $packageId = DB::table('packages')->insertGetId(['lab_id_fk' => $lab->id, 'name' => 'Synthetic package']);
        $package = InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'package_id_fk' => $packageId, 'is_done' => false,
            'package_tests' => [['id' => 9, 'name' => 'Nested analysis', 'is_done' => false, 'is_print_alone' => false]]]);
        $lab->givePermissionTo(Permission::findOrCreate('medical reports update', 'api')); $this->actingAs($lab);
        $response = $this->postJson('/api/invoices/update-result', ['id' => $invoice->id,
            'tests' => [['test_id_fk' => $row->test_id_fk, 'is_done' => true, 'result' => '0']]]);
        $this->assertSame(200, $response->status(), $response->getContent());
        $this->assertFalse($invoice->fresh()->is_done);
        $this->assertDatabaseCount('portal_notifications', 0);
        $response = $this->postJson('/api/invoices/update-result', ['id' => $invoice->id,
            'packages' => [['package_id_fk' => $packageId, 'tests' => [['id' => 9, 'name' => 'Nested analysis', 'is_done' => true, 'result' => 'Negative', 'is_print_alone' => false]]]]]);
        $this->assertSame(200, $response->status(), $response->getContent());
        $this->assertTrue($invoice->fresh()->is_done);
        $this->assertDatabaseCount('portal_notifications', 1);
        $empty = Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id, 'is_done' => true]);
        $this->assertFalse(app(ReportReadiness::class)->isReady($empty));
    }

    public function test_withdrawn_approval_cancels_delivery_and_reapproval_resumes_an_unsent_notice(): void
    {
        $lab = $this->lab(); [$patient] = $this->subscriber($lab); [$invoice, $row] = $this->invoice($lab, $patient);
        $row->update(['is_done' => true]); $invoice->update(['is_done' => true]);
        $row->update(['is_done' => false]); // stale invoice-level flag cannot permit delivery
        app(PortalNotifications::class)->deliver(); $this->assertSame([], $this->sent);
        $this->assertDatabaseHas('portal_push_deliveries', ['status_reason' => 'report_not_ready']);
        $row->update(['is_done' => true]); $invoice->update(['notes' => 'Approved again']);
        app(PortalNotifications::class)->deliver(); $this->assertCount(1, $this->sent);
        $this->assertDatabaseCount('portal_notifications', 1);
    }

    public function test_imported_double_encoded_containers_are_supported_and_malformed_or_pending_items_are_not_ready(): void
    {
        $lab = $this->lab(); [$patient] = $this->subscriber($lab);
        [$invoice, $row] = $this->invoice($lab, $patient);
        $row->update(['is_done' => true]);
        $packageId = DB::table('packages')->insertGetId(['lab_id_fk' => $lab->id, 'name' => 'Imported package']);
        $container = InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'package_id_fk' => $packageId,
            'is_done' => true, 'package_tests' => json_encode([['is_done' => true, 'result' => '0']])]);
        $invoice->update(['is_done' => true]);
        $this->assertTrue(app(ReportReadiness::class)->isReady($invoice->fresh()));
        $container->update(['package_tests' => json_encode([['is_done' => false]])]);
        $this->assertFalse(app(ReportReadiness::class)->isReady($invoice->fresh()));
        $container->update(['package_tests' => 'malformed']);
        $this->assertFalse(app(ReportReadiness::class)->isReady($invoice->fresh()));
    }

    public function test_lab_pause_blocks_new_and_pending_push_without_deleting_patient_preferences(): void
    {
        $lab = $this->lab(); [$patient, $access, $subscription] = $this->subscriber($lab);
        [$invoice, $row] = $this->invoice($lab, $patient);
        $row->update(['is_done' => true]); $invoice->update(['is_done' => true]);
        $this->actingAs($lab)->putJson('/api/lab-notifications/settings', array_replace(PortalLabNotificationSetting::defaults(), ['enabled' => false]))->assertOk();
        app(PortalNotifications::class)->deliver(); $this->assertSame([], $this->sent);
        $this->assertDatabaseHas('portal_push_deliveries', ['status' => 'cancelled', 'status_reason' => 'lab_disabled']);
        $this->getJson('/api/portal/'.$access->token.'/app-config')->assertOk()->assertJsonPath('public_key', null)->assertJsonPath('lab_notifications_enabled', false);
        $this->postJson('/api/lab-notifications/campaigns', $this->campaign())->assertStatus(409);
        $this->assertTrue($subscription->fresh()->results_enabled);
        $this->assertTrue($subscription->fresh()->offers_enabled);
    }

    public function test_retry_only_requeues_failed_attempts_and_never_resends_accepted_devices(): void
    {
        $lab = $this->lab(); [$patient] = $this->subscriber($lab); [$invoice, $row] = $this->invoice($lab, $patient);
        $row->update(['is_done' => true]); $invoice->update(['is_done' => true]);
        $notice = PortalNotification::firstOrFail(); $delivery = PortalPushDelivery::firstOrFail();
        $delivery->update(['status' => 'failed', 'attempts' => 5]);
        $this->actingAs($lab)->postJson('/api/lab-notifications/history/'.$notice->id.'/retry')->assertOk()->assertJsonPath('queued', 1);
        app(PortalNotifications::class)->deliver(); $this->assertCount(1, $this->sent);
        $this->postJson('/api/lab-notifications/history/'.$notice->id.'/retry')->assertStatus(409);
        app(PortalNotifications::class)->deliver(); $this->assertCount(1, $this->sent);
        $this->getJson('/api/lab-notifications/settings')->assertOk()->assertJsonPath('health.worker_recent', true);
    }
}
