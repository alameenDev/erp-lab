<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditActivity;
use App\Models\{Invoice, InvoiceTestRel, Package, Patient, Test as LabTest, TestGroup, User};
use App\Services\{AuditSnapshot, AuditTrail};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Route};
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityAuditTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private Invoice $invoice;
    private LabTest $test;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->lab = User::create(['name' => 'Audit lab', 'email' => 'audit@example.test', 'password' => 'private-password', 'role_id' => 2]);
        $this->lab->givePermissionTo(['activities view', 'invoices create', 'invoices edit', 'invoices delete', 'medical reports update', 'invoices send whatsapp']);
        $person = User::create(['name' => 'Synthetic patient', 'email' => 'audit-patient@example.test', 'password' => 'private-password', 'role_id' => 6]);
        $patient = Patient::create(['user_id' => $person->id, 'creator_id' => $this->lab->id, 'code' => 'AUDIT-1']);
        $this->test = LabTest::create(['name' => 'CBC', 'unit' => 'g/dL', 'lab_id_fk' => $this->lab->id]);
        $this->invoice = Invoice::create(['lab_id_fk' => $this->lab->id, 'patient_id_fk' => $patient->id, 'barcode' => 'AUDIT-BARCODE-1', 'total' => 20000, 'paid' => 0]);
        InvoiceTestRel::create(['invoice_id_fk' => $this->invoice->id, 'test_id_fk' => $this->test->id, 'price' => 20000, 'result' => '10', 'is_done' => false]);
        $this->actingAs($this->lab);
    }

    private function invoiceLog(string $event = 'updated'): Activity
    {
        return Activity::where('audit_invoice_id', $this->invoice->id)->where('event', $event)->latest('id')->firstOrFail();
    }

    public function test_result_upserts_are_recorded_with_exact_before_after_and_invoice_context(): void
    {
        $action = (string) Str::uuid();
        $this->withHeaders(['X-Audit-Action-Id' => $action, 'X-Audit-Label' => rawurlencode('حفظ النتيجة')])
            ->postJson('/api/invoices/update-result', ['id' => $this->invoice->id, 'tests' => [
                ['test_id_fk' => $this->test->id, 'result' => '15', 'price' => 20000, 'is_done' => true],
            ]])->assertOk();
        $log = $this->invoiceLog();
        $change = collect($log->properties['changes'])->firstWhere('field', 'analyses.test_'.$this->test->id.'_1.result');
        $this->assertSame('10', $change['before']);
        $this->assertSame('15', $change['after']);
        $this->assertStringContainsString('CBC', $change['label']);
        $this->assertSame('AUDIT-BARCODE-1', $log->properties['invoice']['barcode']);
        $this->assertSame('Synthetic patient', $log->properties['invoice']['patient_name']);
        $this->assertSame($action, $log->properties['action_id']);
        $this->assertSame('حفظ النتيجة', $log->properties['button']);
        $this->assertSame(1, Activity::where('audit_invoice_id', $this->invoice->id)->where('event', 'updated')->count());
        $this->getJson('/api/activity/show/'.$log->id)->assertOk()->assertJsonPath('invoice.id', $this->invoice->id)->assertJsonPath('status', 'success');
    }

    public function test_invoice_deletion_keeps_all_removed_results_including_groups_and_packages(): void
    {
        $group = TestGroup::create(['group_name' => 'Virology', 'lab_id_fk' => $this->lab->id]);
        $package = Package::create(['name' => 'Checkup', 'lab_id_fk' => $this->lab->id]);
        foreach ([['test_group_id_fk', $group->id, 'test_group_tests'], ['package_id_fk', $package->id, 'package_tests']] as [$key, $id, $field]) {
            InvoiceTestRel::create(['invoice_id_fk' => $this->invoice->id, $key => $id, $field => [['id' => $this->test->id, 'name' => 'Nested CBC', 'result' => '7.2', 'unit' => 'g/dL']]]);
        }
        $this->deleteJson('/api/invoices/delete', ['id' => $this->invoice->id])->assertOk();
        $log = $this->invoiceLog('deleted');
        $this->assertCount(3, $log->properties['old_value']['analyses']);
        $this->assertNull($log->properties['new_value']);
        $this->assertStringContainsString('7.2', $log->properties->toJson());
        $this->assertStringContainsString('Virology', $log->properties->toJson());
        $this->assertSame(0, InvoiceTestRel::where('invoice_id_fk', $this->invoice->id)->count());
        $this->getJson('/api/activity/show?invoice_id='.$this->invoice->id)->assertOk()->assertJsonPath('data.0.event', 'deleted');
    }

    public function test_payment_and_whatsapp_status_have_separate_audit_entries(): void
    {
        $method = \App\Models\PaymentMethod::create(['name' => 'Cash', 'lab_id_fk' => $this->lab->id])->id;
        $this->postJson('/api/invoices/add-payment', ['invoice_id' => $this->invoice->id, 'amount' => 5000, 'payment_method_id_fk' => $method])->assertOk();
        $paymentLog = $this->invoiceLog();
        $this->assertStringContainsString('payments.', json_encode($paymentLog->properties['changes']));
        $this->assertStringContainsString('5000', json_encode($paymentLog->properties['changes']));
        $this->postJson('/api/invoices/send', ['id' => $this->invoice->id, 'with_background' => false])->assertOk();
        $sentLog = $this->invoiceLog();
        $this->assertNotSame($paymentLog->id, $sentLog->id);
        $this->assertStringContainsString('sent_to_patient', json_encode($sentLog->properties['changes']));
        $this->assertSame('بدء إرسال النتيجة بالواتساب', $sentLog->description);
    }

    public function test_nested_group_results_and_membership_changes_are_not_lost_by_bulk_writes(): void
    {
        $group = TestGroup::create(['group_name' => 'Virology', 'lab_id_fk' => $this->lab->id]);
        $group->tests()->attach($this->test->id);
        $old = ['id' => $this->test->id, 'name' => 'Nested CBC', 'result' => '10', 'is_done' => true, 'is_print_alone' => false];
        InvoiceTestRel::create(['invoice_id_fk' => $this->invoice->id, 'test_group_id_fk' => $group->id, 'test_group_tests' => [$old]]);
        $this->postJson('/api/invoices/update-result', ['id' => $this->invoice->id, 'test_groups' => [
            ['test_group_id_fk' => $group->id, 'tests' => [array_merge($old, ['result' => '18'])]],
        ]])->assertOk();
        $change = collect($this->invoiceLog()->properties['changes'])->firstWhere('field', 'analyses.test_group_'.$group->id.'_1.test_group_tests.0.result');
        $this->assertSame('10', $change['before']);
        $this->assertSame('18', $change['after']);
        $this->assertStringContainsString('Nested CBC', $change['label']);
        $this->lab->givePermissionTo('test groups edit');
        $this->putJson('/api/test_groups/update', ['id' => $group->id, 'group_name' => 'Virology', 'test_ids' => [], 'is_print_alone' => 0])->assertOk();
        $log = Activity::where('subject_type', TestGroup::class)->where('subject_id', $group->id)->latest('id')->firstOrFail();
        $removed = collect($log->properties['changes'])->firstWhere('field', 'tests.'.$this->test->id.'.name');
        $this->assertSame('CBC', $removed['before']);
        $this->assertNull($removed['after']);
    }

    public function test_device_receipt_is_attributed_to_the_device_without_recording_its_secret(): void
    {
        $device = \App\Models\LabDevice::create(['name' => 'Audit analyzer', 'lab_id_fk' => $this->lab->id, 'api_token' => str_repeat('d', 64)]);
        $this->app['auth']->forgetGuards();
        $this->withHeader('X-Device-Token', $device->api_token)->postJson('/api/device/results', [
            'specimen_barcode' => $this->invoice->barcode, 'raw_message' => 'synthetic instrument message',
            'parsed_results' => [['test_code' => 'CBC', 'value' => '10', 'unit' => 'g/dL']],
        ])->assertCreated();
        $log = Activity::where('audit_source', 'device')->where('subject_type', \App\Models\DeviceResult::class)->latest('id')->firstOrFail();
        $this->assertSame($device->id, $log->causer_id);
        $this->assertSame($this->invoice->id, $log->audit_invoice_id);
        $this->assertSame('جهاز: Audit analyzer', $log->properties['actor_name']);
        $this->assertStringNotContainsString($device->api_token, Activity::all()->toJson());
    }

    public function test_failed_or_rolled_back_writes_do_not_claim_committed_changes(): void
    {
        Route::post('/api/audit-test/rollback', function () {
            DB::beginTransaction();
            $this->invoice->update(['total' => 99999]);
            return response()->json(['message' => 'Rejected'], 422);
        })->middleware(['auth:sanctum', AuditActivity::class]);
        $this->postJson('/api/audit-test/rollback')->assertStatus(422);
        $this->assertEquals(20000, $this->invoice->fresh()->total);
        $this->assertSame(0, Activity::where('event', 'updated')->count());
        $this->assertSame('failed', Activity::latest('id')->first()->event);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_log_read_detail_export_and_forged_click_invoice_are_tenant_scoped(): void
    {
        $other = User::create(['name' => 'Private other lab', 'email' => 'other-audit@example.test', 'password' => 'private-password', 'role_id' => 2]);
        $other->givePermissionTo('activities view');
        $this->postJson('/api/invoices/send', ['id' => $this->invoice->id])->assertOk();
        $log = $this->invoiceLog();
        $this->actingAs($other);
        $this->getJson('/api/activity/show')->assertOk()->assertJsonPath('pagination.total', 0);
        $this->getJson('/api/activity/show/'.$log->id)->assertNotFound();
        $export = $this->get('/api/activity/export')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('AUDIT-BARCODE-1', $export);
        $uuid = (string) Str::uuid();
        $payload = ['actions' => [['action_id' => $uuid, 'label' => 'Delete', 'page' => '/invoices?token=secret', 'invoice_id' => $this->invoice->id, 'old_value' => 'forged']]];
        $this->postJson('/api/activity/actions', $payload)->assertOk();
        $this->postJson('/api/activity/actions', $payload)->assertOk();
        $click = Activity::where('audit_request_id', $uuid)->firstOrFail();
        $this->assertNull($click->audit_invoice_id);
        $this->assertNull($click->properties['invoice']);
        $this->assertSame('/invoices', $click->properties['page']);
        $this->assertArrayNotHasKey('old_value', $click->properties);
        $this->assertSame(1, Activity::where('audit_request_id', $uuid)->count());
        $this->assertSame('clicked', $click->event);
        $other->revokePermissionTo('activities view');
        $this->getJson('/api/activity/show')->assertForbidden();
        $this->get('/api/activity/export')->assertForbidden();
    }

    public function test_sensitive_values_are_hidden_while_password_change_is_identified(): void
    {
        Route::post('/api/audit-test/password', function () {
            $this->lab->update(['password' => 'new-private-password']);
            return response()->json(['ok' => true]);
        })->middleware(['auth:sanctum', AuditActivity::class]);
        $this->postJson('/api/audit-test/password')->assertOk();
        $log = Activity::where('subject_type', User::class)->where('subject_id', $this->lab->id)->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('private-password', $log->properties->toJson());
        $this->assertStringNotContainsString('$2y$', $log->properties->toJson());
        $this->assertNotNull(collect($log->properties['changes'])->firstWhere('field', 'password'));
        $clean = AuditSnapshot::clean(['api_key' => 'secret-value', 'nested' => ['token' => 'secret-token'], 'password' => 'secret-pass']);
        $this->assertSame(AuditSnapshot::HIDDEN, $clean['api_key']);
        $this->assertSame(AuditSnapshot::HIDDEN, $clean['nested']['token']);
    }

    public function test_filtered_export_includes_values_and_protects_spreadsheet_formulas(): void
    {
        $this->invoice->update(['notes' => '=HYPERLINK("evil")']);
        $this->deleteJson('/api/invoices/delete', ['id' => $this->invoice->id])->assertOk();
        $response = $this->getJson('/api/activity/show?event=deleted&invoice_id='.$this->invoice->id.'&per_page=10')->assertOk();
        $this->assertSame(1, $response->json('pagination.total'));
        $csv = $this->get('/api/activity/export?event=deleted&invoice_id='.$this->invoice->id)->assertOk()->streamedContent();
        $this->assertStringContainsString('AUDIT-BARCODE-1', $csv);
        $this->assertStringContainsString('CBC', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
