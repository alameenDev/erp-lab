<?php

namespace Tests\Feature;

use App\Models\{Invoice, InvoiceTestRel, OwnerNotification, Patient, Test as LabTest, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceMutationReasonsTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private User $owner;
    private Patient $patient;
    private LabTest $test;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->lab = User::create([
            'name' => 'Reason Lab', 'email' => 'reasons-lab@example.test',
            'password' => 'test-only-password', 'role_id' => 2,
        ]);
        foreach (['invoices create','invoices edit','invoices delete','tests delete','tests edit','medical reports update'] as $name) {
            $this->lab->givePermissionTo(Permission::findOrCreate($name, 'api'));
        }
        $this->owner = User::create([
            'name' => 'Clinic Owner', 'email' => 'reasons-owner@example.test', 'role_id' => null,
            'password' => 'test-only-password',
        ]);
        $this->owner->forceFill(['owner_portal_only' => true, 'status' => 1])->save();
        DB::table('owner_labs')->insert(['owner_id' => $this->owner->id, 'lab_id' => $this->lab->id]);
        $patientUser = User::create([
            'name' => 'Sample Patient', 'phone_number' => '07801234567',
            'email' => 'reasons-patient@example.test', 'password' => 'test-only-password', 'role_id' => 6,
        ]);
        $this->patient = Patient::create([
            'user_id' => $patientUser->id, 'creator_id' => $this->lab->id,
            'code' => 'PAT-REASON', 'age' => 36,
        ]);
        $this->test = LabTest::create(['lab_id_fk' => $this->lab->id, 'name' => 'CBC', 'result_type_id_fk' => 1]);
        $this->invoice = Invoice::create([
            'lab_id_fk' => $this->lab->id, 'patient_id_fk' => $this->patient->id,
            'barcode' => 'REASON-INV-1', 'sub_total' => 35000, 'total' => 35000,
            'discount' => null, 'is_done' => false,
        ]);
        InvoiceTestRel::create([
            'invoice_id_fk' => $this->invoice->id, 'test_id_fk' => $this->test->id,
            'price' => 35000, 'result' => '12.4',
        ]);
        $this->actingAs($this->lab);
    }

    private function edit(array $extra = [])
    {
        return $this->putJson('/api/invoices/update', array_merge([
            'id' => $this->invoice->id, 'patient_id_fk' => $this->patient->id,
            'total' => 35000, 'sub_total' => 35000,
            'tests' => [['test_id_fk' => $this->test->id, 'price' => 35000]],
        ], $extra));
    }

    public function test_invoice_deletion_is_blocked_without_a_reason_and_preserves_full_owner_context(): void
    {
        $this->deleteJson('/api/invoices/delete', ['id' => $this->invoice->id])
            ->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id, 'deleted_at' => null]);
        $this->assertSame(0, DB::table('invoice_change_archive')->count());
        $reason = 'حذف الفاتورة لأنها مكررة وفق طلب المراجع وتأكيد الاستقبال';
        $this->deleteJson('/api/invoices/delete', ['id' => $this->invoice->id, 'change_reason' => $reason])->assertOk();
        $archive = DB::table('invoice_change_archive')->where('invoice_id', $this->invoice->id)->firstOrFail();
        $snapshot = json_decode($archive->snapshot, true);
        $this->assertSame($reason, $snapshot['change_reason']);
        $this->assertSame('Sample Patient', $snapshot['before']['patient']['name']);
        $this->assertSame('07801234567', $snapshot['before']['patient']['phone']);
        $this->assertSame('CBC', array_values($snapshot['before']['analyses'])[0]['name']);
        $this->assertSame('12.4', array_values($snapshot['before']['analyses'])[0]['result']);
        $notice = OwnerNotification::where('kind', 'invoice_deleted')->firstOrFail();
        $this->assertSame($reason, $notice->data['change_reason']);
        $this->assertSame('Sample Patient', $notice->data['patient_name']);
        $this->assertSame('07801234567', $notice->data['patient_phone']);
        $this->assertSame('CBC', $notice->data['analyses'][0]['name']);
        $this->assertStringNotContainsString('Sample Patient', $notice->body);
        $this->assertStringNotContainsString($reason, $notice->body);
        $this->actingAs($this->owner);
        $this->getJson('/api/owner-portal/changes/'.$archive->id)->assertOk()
            ->assertJsonPath('snapshot.change_reason', $reason)
            ->assertJsonPath('snapshot.before.patient.phone', '07801234567');
        $this->getJson('/api/owner-portal/notifications/'.$notice->id)->assertOk()
            ->assertJsonPath('data.change_reason', $reason);
        $this->getJson('/api/owner-portal/changes')->assertOk()
            ->assertJsonPath('data.0.change_reason', $reason);
    }

    public function test_analyses_cannot_be_removed_or_repriced_and_discount_cannot_change_without_explanation(): void
    {
        $this->edit(['tests' => []])->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $this->assertSame(1, $this->invoice->fresh()->invoiceTestRels()->count());
        $this->edit(['tests' => [['test_id_fk' => $this->test->id, 'price' => 30000]]])
            ->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $this->edit(['discount' => 5000, 'discount_type_id_fk' => 3, 'total' => 30000])
            ->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $reason = 'خفضنا سعر التحليل بسبب خطأ في سعر الفاتورة الأصلية بموافقة المشرف';
        $this->edit([
            'tests' => [['test_id_fk' => $this->test->id, 'price' => 30000]],
            'discount' => 5000, 'discount_type_id_fk' => 3, 'total' => 30000,
            'change_reason' => $reason,
        ])->assertOk();
        $archive = DB::table('invoice_change_archive')->where('invoice_id', $this->invoice->id)->latest('id')->firstOrFail();
        $snapshot = json_decode($archive->snapshot, true);
        $this->assertSame($reason, $snapshot['change_reason']);
        $this->assertNotEmpty($snapshot['reason_operations']);
        $notice = OwnerNotification::where('kind', 'invoice_amount')->latest('created_at')->firstOrFail();
        $this->assertSame($reason, $notice->data['change_reason']);
        $this->assertSame('Sample Patient', $notice->data['patient_name']);
    }

    public function test_removing_a_test_with_unchanged_invoice_total_still_notifies_owner(): void
    {
        $reason = 'أزلنا التحليل المكرر بعدما تأكدنا من تسجيله مرتين لنفس المريض';
        $this->edit(['tests' => [], 'change_reason' => $reason])->assertOk();
        $notice = OwnerNotification::where('kind', 'invoice_amount')->firstOrFail();
        $this->assertSame($reason, $notice->data['change_reason']);
        $this->assertTrue(collect($notice->data['reason_operations'])
            ->contains(fn ($item) => str_contains($item, 'حذف تحليل')));
        $this->assertSame(35000, (int) $this->invoice->fresh()->total);
    }

    public function test_partial_invoice_edit_does_not_delete_any_analyses_or_reset_prices(): void
    {
        $this->putJson('/api/invoices/update', [
            'id' => $this->invoice->id,
            'patient_id_fk' => $this->patient->id,
            'notes' => 'تصحيح ملاحظة الاستقبال فقط',
        ])->assertOk();
        $this->assertSame(35000, (int) $this->invoice->fresh()->total);
        $this->assertSame(35000, (int) $this->invoice->fresh()->sub_total);
        $this->assertSame(1, $this->invoice->fresh()->invoiceTestRels()->count());
    }

    public function test_missing_an_analysis_list_when_other_lists_submitted_is_not_a_loophole(): void
    {
        $this->edit(['tests' => null])->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $this->assertSame(1, $this->invoice->fresh()->invoiceTestRels()->count());
    }

    public function test_ordinary_result_updates_do_not_require_manual_financial_reason(): void
    {
        $this->postJson('/api/invoices/update-result', [
            'id' => $this->invoice->id,
            'tests' => [['test_id_fk' => $this->test->id, 'result' => '14', 'is_done' => true]],
        ])->assertOk();
        $this->assertSame('14', (string) $this->invoice->fresh()->invoiceTestRels()->first()->result);
    }

    public function test_original_and_customer_price_changes_in_test_catalog_require_comment(): void
    {
        $category = \App\Models\Category::create(['name' => 'General Tests', 'lab_id_fk' => $this->lab->id]);
        $this->test->update([
            'category_id_fk' => $category->id, 'price' => 18000, 'for_customer_price' => 35000,
        ]);
        $payload = [
            'id' => $this->test->id, 'name' => $this->test->name,
            'category_id_fk' => $category->id, 'result_type_id_fk' => 1,
            'price' => 20000, 'for_customer_price' => 40000,
        ];
        $this->putJson('/api/tests/update', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $this->assertSame(18000, (int) $this->test->fresh()->price);
        $reason = 'تم اعتماد قائمة أسعار جديدة بعد مراجعة الإدارة والتكاليف';
        $this->putJson('/api/tests/update', [...$payload, 'change_reason' => $reason])->assertOk();
        $notice = OwnerNotification::where('kind', 'test')->firstOrFail();
        $this->assertSame('CBC', $notice->data['analysis_name']);
        $this->assertSame(18000, (int) $notice->data['original_price_before']);
        $this->assertSame(20000, (int) $notice->data['original_price_after']);
        $this->assertSame($reason, $notice->data['change_reason']);
    }

    public function test_catalog_test_deletion_requires_reason_and_creates_an_owner_notice(): void
    {
        $this->deleteJson('/api/tests/delete', ['id' => $this->test->id])
            ->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $this->assertNull($this->test->fresh()->deleted_at);
        $reason = 'حذفنا التحليل لأن هذا الاختبار لم يعد متاحاً بعد تحديث قائمة المختبر';
        $this->deleteJson('/api/tests/delete', ['id' => $this->test->id, 'change_reason' => $reason])->assertOk();
        $notice = OwnerNotification::where('kind', 'test')->firstOrFail();
        $this->assertSame($reason, $notice->data['change_reason']);
        $this->assertSame('CBC', $notice->data['analysis_name']);
    }
}
