<?php

namespace Tests\Feature;

use App\Models\{AgeUnit, Invoice, Package, Patient, Test as LabTest, TestGroup, User, WorkRegisterEntry};
use App\Services\DailyWorkRegister;
use Carbon\Carbon;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DailyWorkRegisterTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-10-18 09:00:00', 'UTC'));
        $this->lab = User::create(['name' => 'مختبر السجل', 'email' => 'register@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $this->lab->givePermissionTo(Permission::findOrCreate('invoices create', 'api'));
        $person = User::create(['name' => 'محمد أحمد علي', 'email' => 'register-patient@example.test', 'password' => 'test-password', 'role_id' => 3, 'creator_id' => $this->lab->id]);
        $this->patient = Patient::create(['user_id' => $person->id, 'creator_id' => $this->lab->id, 'code' => 'REG-1', 'age' => 32,
            'age_unit_id_fk' => AgeUnit::where('unit_name', 'Years')->value('id')]);
        $this->actingAs($this->lab);
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function createInvoice(array $extra = []): Invoice
    {
        $id = $this->postJson('/api/invoices/create', array_merge(['patient_id_fk' => $this->patient->id,
            'total' => 25000, 'sub_total' => 25000, 'paid' => 10000], $extra))->assertOk()->json('id');
        return Invoice::findOrFail($id);
    }

    private function day(?string $date = null): array
    {
        return $this->getJson('/api/work-register'.($date ? '?date='.$date : ''))->assertOk()->json();
    }

    public function test_invoice_creation_captures_complete_items_referrals_and_amounts_after_commit(): void
    {
        $test = LabTest::create(['name' => 'CBC', 'lab_id_fk' => $this->lab->id, 'result_type_id_fk' => 1]);
        $group = TestGroup::create(['group_name' => 'Liver profile', 'lab_id_fk' => $this->lab->id]);
        $package = Package::create(['name' => 'Annual checkup', 'lab_id_fk' => $this->lab->id]);
        $doctor = User::create(['name' => 'د. زيد', 'email' => 'register-doctor@example.test', 'password' => 'test-password', 'role_id' => 5]);
        $refLab = User::create(['name' => 'مختبر محيل', 'email' => 'register-referral@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $invoice = $this->createInvoice(['referral_id_fk' => $doctor->id, 'from_lab_id_fk' => $refLab->id,
            'tests' => [['test_id_fk' => $test->id]], 'test_groups' => [['test_group_id_fk' => $group->id]], 'packages' => [['package_id_fk' => $package->id]]]);
        // Assert before GET: lazy history initialization must not hide a failed observer.
        $entry = WorkRegisterEntry::where('source_invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(['CBC'], $entry->tests);
        $this->assertSame(['Liver profile'], $entry->groups);
        $this->assertSame(['Annual checkup'], $entry->packages);
        $this->assertSame('محمد أحمد علي', $entry->patient_name);
        $this->assertSame(32, $entry->age);
        $this->assertSame('د. زيد', $entry->referral_doctor);
        $this->assertSame('مختبر محيل', $entry->referral_lab);
        $day = $this->day();
        $this->assertSame(['count' => 1, 'work_register_amount' => 0], $day['totals']);
        app(DailyWorkRegister::class)->capture($invoice->id);
        $this->assertCount(1, $this->day()['entries']);
    }

    public function test_register_edits_and_deletion_do_not_change_source_data_or_return_on_refresh(): void
    {
        $invoice = $this->createInvoice();
        $other = $this->createInvoice(); // Repeat visits by the same patient stay separate.
        $day = $this->day(); $entry = $day['entries'][0];
        $invoiceBefore = $invoice->fresh()->getAttributes();
        $patientBefore = $this->patient->fresh()->getAttributes();
        $paymentsBefore = $invoice->paidDetails()->get()->toArray();
        $testsBefore = $invoice->invoiceTestRels()->get()->toArray();
        $changed = $this->patchJson('/api/work-register/'.$entry['id'], ['date' => $day['date'], 'version' => $day['version'],
            'patient_name' => 'اسم داخل السجل فقط', 'age' => 7, 'age_unit' => 'Months', 'referral_doctor' => 'طبيب السجل',
            'tests' => ['TSH'], 'groups' => ['Hormones'], 'packages' => ['Package'], 'work_register_amount' => 12000, 'position' => 2])
            ->assertOk()->assertJsonPath('entries.1.id', $entry['id'])->assertJsonPath('entries.1.position', 2)->json();
        $this->assertSame($invoiceBefore, $invoice->fresh()->getAttributes());
        $this->assertSame($patientBefore, $this->patient->fresh()->getAttributes());
        $this->assertSame($paymentsBefore, $invoice->paidDetails()->get()->toArray());
        $this->assertSame($testsBefore, $invoice->invoiceTestRels()->get()->toArray());
        $this->assertSame(['count' => 2, 'work_register_amount' => 12000], $changed['totals']);
        $invoice->update(['total' => 70000, 'paid' => 60000]);
        $this->assertSame(12000, $this->day()['entries'][1]['work_register_amount'], 'later invoice changes never overwrite register edits');
        $deleted = $this->deleteJson('/api/work-register/'.$entry['id'], ['date' => $day['date'], 'version' => $changed['version']])
            ->assertOk()->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.position', 1)->json();
        $this->assertCount(1, $this->day()['entries']);
        $this->assertFalse($invoice->fresh()->trashed());
        $third = $this->createInvoice();
        $this->assertSame([$other->id, $third->id], array_column($this->day()['entries'], 'source_invoice_id'));
        $this->assertSame([1, 2], array_column($this->day()['entries'], 'position'));
        $other->delete();
        $this->assertCount(2, $this->day()['entries'], 'an archived register survives source invoice soft deletion');
        $this->assertSame(0, $deleted['totals']['work_register_amount']);
    }

    public function test_calendar_rollover_uses_baghdad_midnight_and_preserves_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 20:59:59', 'UTC'));
        $first = $this->createInvoice();
        $this->assertSame('2026-10-18', $this->day()['date']);
        Carbon::setTestNow(Carbon::parse('2026-10-18 21:00:00', 'UTC'));
        $this->assertSame(['count' => 0, 'work_register_amount' => 0], $this->day()['totals']);
        $second = $this->createInvoice();
        $today = $this->day();
        $this->assertSame('2026-10-19', $today['date']);
        $this->assertSame(1, $today['entries'][0]['position']);
        $this->assertSame($second->id, $today['entries'][0]['source_invoice_id']);
        $this->assertSame($first->id, $this->day('2026-10-18')['entries'][0]['source_invoice_id']);
    }

    public function test_missing_history_is_initialized_once_and_rollback_creates_no_entry(): void
    {
        $invoice = Invoice::withoutEvents(fn () => Invoice::create(['patient_id_fk' => $this->patient->id, 'lab_id_fk' => $this->lab->id, 'total' => 8000, 'paid' => 2000]));
        $this->assertSame(0, WorkRegisterEntry::count());
        $this->assertSame(0, $this->day()['totals']['work_register_amount']);
        $this->assertNull($this->day()['entries'][0]['work_register_amount']);
        $this->assertCount(1, $this->day()['entries']);
        DB::beginTransaction();
        $rolledBack = Invoice::create(['patient_id_fk' => $this->patient->id, 'lab_id_fk' => $this->lab->id, 'total' => 999]);
        DB::rollBack();
        $this->assertFalse(WorkRegisterEntry::where('source_invoice_id', $rolledBack->id)->exists());
        $this->assertSame($invoice->id, $this->day()['entries'][0]['source_invoice_id']);
    }

    public function test_tenant_permissions_validation_and_stale_writes_are_enforced(): void
    {
        $this->createInvoice();
        $day = $this->day(); $id = $day['entries'][0]['id'];
        $input = ['date' => $day['date'], 'version' => $day['version'], 'work_register_amount' => 1];
        $this->patchJson('/api/work-register/'.$id, $input)->assertOk();
        $this->patchJson('/api/work-register/'.$id, $input)->assertStatus(409);
        $this->deleteJson('/api/work-register/'.$id, $input)->assertStatus(409);
        $this->patchJson('/api/work-register/'.$id, array_merge($input, ['work_register_amount' => -1]))->assertUnprocessable();
        $this->getJson('/api/work-register?date=not-a-date')->assertUnprocessable();
        $foreign = User::create(['name' => 'Other lab', 'email' => 'register-other@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $this->actingAs($foreign);
        $this->assertEmpty($this->day()['entries']);
        $this->patchJson('/api/work-register/'.$id, $input)->assertNotFound();
        $this->deleteJson('/api/work-register/'.$id, $input)->assertNotFound();
        $staff = User::create(['name' => 'Reception', 'email' => 'register-staff@example.test', 'password' => 'test-password', 'role_id' => 6, 'creator_id' => $this->lab->id]);
        $staff->givePermissionTo(Permission::findOrCreate('patients view', 'api'));
        $this->actingAs($staff);
        $this->assertCount(1, $this->day()['entries']);
        $this->patchJson('/api/work-register/'.$id, $input)->assertForbidden();
        $this->deleteJson('/api/work-register/'.$id, $input)->assertForbidden();
        $staff->givePermissionTo(Permission::findOrCreate('invoices create', 'api'));
        $this->createInvoice(['work_register_amount' => 4321]);
        $this->assertSame(4322, $this->day()['totals']['work_register_amount']);
        $this->assertCount(2, $this->day()['entries'], 'staff invoices belong to the owning lab day');
        $this->assertSame($this->lab->id, WorkRegisterEntry::latest('id')->first()->lab_id_fk);
    }

    public function test_manual_amount_is_register_owned_and_excluded_from_invoice_and_payment_outputs(): void
    {
        $this->lab->givePermissionTo(Permission::findOrCreate('invoices edit', 'api'));
        $invoice = $this->createInvoice(['work_register_amount' => 1234,
            'payment_details' => [['amount' => 6000, 'payment_method_id_fk' => null]]]);
        $entry = WorkRegisterEntry::where('source_invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(1234, $entry->work_register_amount);
        $this->assertSame(25000, (int) $invoice->total);
        $this->assertSame(6000, (int) $invoice->paid);
        $this->assertSame(6000, (int) $invoice->paidDetails()->sum('amount'));
        $this->assertArrayNotHasKey('work_register_amount', $invoice->getAttributes());
        $this->getJson('/api/invoices/'.$invoice->id.'?for_edit=1')->assertOk()->assertJsonPath('work_register_amount', 1234);
        $normal = $this->getJson('/api/invoices/'.$invoice->id)->assertOk()->json();
        $this->assertArrayNotHasKey('work_register_amount', $normal);
        $this->assertArrayNotHasKey('work_register_amount', app(\App\Http\Controllers\InvoiceController::class)->patientResultData($invoice));
        $this->assertArrayNotHasKey('price', $this->day()['entries'][0]);
        $this->assertArrayNotHasKey('paid', $this->day()['entries'][0]);
        $this->assertSame(1234, $this->day()['totals']['work_register_amount']);
        // A second visit without a manual value must not inherit either invoice finances or the previous visit.
        $second = $this->createInvoice();
        $this->assertNull(app(DailyWorkRegister::class)->amountForInvoice($second->id));
        $this->assertSame(1234, $this->day()['totals']['work_register_amount']);
    }

    public function test_invoice_amount_edit_clear_zero_omission_and_concurrency(): void
    {
        $this->lab->givePermissionTo(Permission::findOrCreate('invoices edit', 'api'));
        $invoice = $this->createInvoice(['work_register_amount' => 1500]);
        $input = ['id' => $invoice->id, 'patient_id_fk' => $this->patient->id, 'total' => 25000, 'sub_total' => 25000,
            'payment_details' => [['amount' => 10000, 'payment_method_id_fk' => null]]];
        $day = $this->day(); $entry = $day['entries'][0];
        $this->putJson('/api/invoices/update', $input + ['work_register_amount' => 2222, 'work_register_amount_original' => 1500])->assertOk();
        $this->assertSame(2222, $this->day()['totals']['work_register_amount']);
        // An invoice write invalidates any older open register draft.
        $this->patchJson('/api/work-register/'.$entry['id'], ['date' => $day['date'], 'version' => $day['version'], 'work_register_amount' => 3333])->assertStatus(409);
        $day = $this->day();
        $this->patchJson('/api/work-register/'.$entry['id'], ['date' => $day['date'], 'version' => $day['version'], 'work_register_amount' => 3333])->assertOk();
        $before = $invoice->fresh()->getAttributes();
        $payments = $invoice->paidDetails()->get()->toArray();
        $this->putJson('/api/invoices/update', array_merge($input, ['total' => 99000, 'work_register_amount' => 4444, 'work_register_amount_original' => 2222]))
            ->assertUnprocessable()->assertJsonValidationErrors('work_register_amount');
        $this->assertSame($before, $invoice->fresh()->getAttributes(), 'a conflicting manual amount rolls back the whole invoice edit');
        $this->assertSame($payments, $invoice->paidDetails()->get()->toArray());
        $this->putJson('/api/invoices/update', array_merge($input, ['total' => 70000]))->assertOk();
        $this->assertSame(3333, $this->day()['totals']['work_register_amount'], 'unrelated edits leave the manual amount alone');
        $this->getJson('/api/invoices/'.$invoice->id.'?for_edit=1')->assertOk()->assertJsonPath('work_register_amount', 3333);
        $this->putJson('/api/invoices/update', $input + ['work_register_amount' => null, 'work_register_amount_original' => 3333])->assertOk();
        $this->assertNull($this->day()['entries'][0]['work_register_amount']);
        $this->putJson('/api/invoices/update', $input + ['work_register_amount' => 0, 'work_register_amount_original' => null])->assertOk();
        $this->assertSame(0, $this->day()['entries'][0]['work_register_amount']);
        $day = $this->day();
        $this->deleteJson('/api/work-register/'.$entry['id'], ['date' => $day['date'], 'version' => $day['version']])->assertOk();
        $this->putJson('/api/invoices/update', $input + ['work_register_amount' => 500, 'work_register_amount_original' => 0])->assertOk();
        $this->assertEmpty($this->day()['entries'], 'editing an invoice must not restore a deleted register row');
    }

    public function test_manual_amount_validation_and_legacy_values_never_become_manual_amounts(): void
    {
        foreach ([-1, 1.5, 'not-an-amount', 1000000000001] as $amount) {
            $this->postJson('/api/invoices/create', ['patient_id_fk' => $this->patient->id, 'work_register_amount' => $amount])
                ->assertUnprocessable()->assertJsonValidationErrors('work_register_amount');
        }
        $this->assertSame(0, Invoice::count());
        $invoice = $this->createInvoice();
        $entry = WorkRegisterEntry::where('source_invoice_id', $invoice->id)->firstOrFail();
        $entry->update(['price' => 80000, 'paid' => 60000]); // Pre-upgrade values are preserved, but never used.
        $this->assertNull($this->day()['entries'][0]['work_register_amount']);
        $this->assertSame(0, $this->day()['totals']['work_register_amount']);
        $this->assertSame(80000, (int) $entry->fresh()->price);
        $this->getJson('/api/invoices/'.$invoice->id.'?for_edit=1')->assertForbidden();
        $this->lab->givePermissionTo(Permission::findOrCreate('invoices edit', 'api'));
        $this->putJson('/api/invoices/update', ['id' => $invoice->id, 'patient_id_fk' => $this->patient->id, 'work_register_amount' => 400])->assertUnprocessable();
        // The manual write participates in the caller's transaction, including lazy capture.
        DB::beginTransaction();
        $new = Invoice::create(['patient_id_fk' => $this->patient->id, 'lab_id_fk' => $this->lab->id]);
        app(DailyWorkRegister::class)->setManualAmount($new->id, 999);
        DB::rollBack();
        $this->assertFalse(WorkRegisterEntry::where('source_invoice_id', $new->id)->exists());
        $foreign = User::create(['name' => 'Other lab', 'email' => 'register-amount-other@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $foreign->givePermissionTo(Permission::findOrCreate('invoices edit', 'api'));
        $this->actingAs($foreign);
        $this->getJson('/api/invoices/'.$invoice->id.'?for_edit=1')->assertForbidden();
        $this->putJson('/api/invoices/update', ['id' => $invoice->id, 'patient_id_fk' => $this->patient->id,
            'work_register_amount' => 800, 'work_register_amount_original' => null])->assertForbidden();
        $this->assertNull($entry->fresh()->work_register_amount);
    }

    public function test_barcode_reference_uses_current_daily_position_and_never_exposes_amounts(): void
    {
        $first = $this->createInvoice(['work_register_amount' => 12000]);
        $second = $this->createInvoice();
        $barcode = $second->barcode;
        $url = '/api/invoices/'.$second->id.'/barcode-register';
        $this->getJson($url)->assertOk()->assertExactJson(['work_register_number' => 2, 'work_register_date' => '2026-10-18'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $day = $this->day();
        $this->patchJson('/api/work-register/'.$day['entries'][1]['id'], ['date' => $day['date'], 'version' => $day['version'], 'position' => 1])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('work_register_number', 1);
        $this->assertSame($barcode, $second->fresh()->barcode);
        $day = $this->day();
        $this->deleteJson('/api/work-register/'.$day['entries'][0]['id'], ['date' => $day['date'], 'version' => $day['version']])->assertOk();
        $this->getJson($url)->assertOk()->assertExactJson(['work_register_number' => null, 'work_register_date' => null]);
        $this->assertCount(1, $this->day()['entries']);
        $this->getJson('/api/invoices/'.$first->id.'/barcode-register')->assertOk()->assertJsonPath('work_register_number', 1);
    }

    public function test_barcode_reference_initializes_history_in_order_and_uses_invoice_work_day(): void
    {
        $first = Invoice::withoutEvents(fn () => Invoice::create(['patient_id_fk' => $this->patient->id, 'lab_id_fk' => $this->lab->id, 'total' => 8000]));
        $second = Invoice::withoutEvents(fn () => Invoice::create(['patient_id_fk' => $this->patient->id, 'lab_id_fk' => $this->lab->id, 'total' => 9000]));
        // Printing the second historical visit first must still give it position 2.
        $this->getJson('/api/invoices/'.$second->id.'/barcode-register')->assertOk()->assertJsonPath('work_register_number', 2);
        $this->getJson('/api/invoices/'.$first->id.'/barcode-register')->assertOk()->assertJsonPath('work_register_number', 1);
        Carbon::setTestNow(Carbon::parse('2026-10-18 21:00:00', 'UTC'));
        $next = $this->createInvoice();
        $this->getJson('/api/invoices/'.$next->id.'/barcode-register')->assertOk()->assertExactJson(['work_register_number' => 1, 'work_register_date' => '2026-10-19']);
        $this->getJson('/api/invoices/'.$second->id.'/barcode-register')->assertOk()->assertExactJson(['work_register_number' => 2, 'work_register_date' => '2026-10-18']);
        $this->assertSame(0, $this->day('2026-10-18')['totals']['work_register_amount']);
    }

    public function test_barcode_register_access_is_scoped_to_invoice_tenant_including_staff(): void
    {
        $invoice = $this->createInvoice();
        $staff = User::create(['name' => 'Barcode Staff', 'email' => 'barcode-register-staff@example.test', 'password' => 'test-password', 'role_id' => 7, 'creator_id' => $this->lab->id]);
        $staff->givePermissionTo(Permission::findOrCreate('invoices create', 'api'));
        $this->actingAs($staff);
        $staffInvoice = $this->createInvoice();
        $this->getJson('/api/invoices/'.$invoice->id.'/barcode-register')->assertOk()->assertJsonPath('work_register_number', 1);
        $this->getJson('/api/invoices/'.$staffInvoice->id.'/barcode-register')->assertOk()->assertJsonPath('work_register_number', 2);
        $other = User::create(['name' => 'Foreign', 'email' => 'barcode-register-other@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $this->actingAs($other);
        $this->getJson('/api/invoices/'.$invoice->id.'/barcode-register')->assertForbidden();
        $this->assertSame(2, WorkRegisterEntry::count());
        $this->actingAs($this->patient->user);
        $this->getJson('/api/invoices/'.$invoice->id.'/barcode-register')->assertForbidden();
        $this->actingAs($this->lab);
        $invoice->delete();
        $this->getJson('/api/invoices/'.$invoice->id.'/barcode-register')->assertNotFound();
    }
}
