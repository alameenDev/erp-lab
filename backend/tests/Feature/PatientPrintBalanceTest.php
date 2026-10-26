<?php

namespace Tests\Feature;

use App\Models\{Invoice, Patient, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientPrintBalanceTest extends TestCase
{
    use DatabaseMigrations;

    private User $lab;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->lab = $this->user('Lab', 2);
        $this->lab->givePermissionTo('invoices print');
        $person = $this->user('Patient', 3, $this->lab->id);
        $this->patient = Patient::create(['user_id' => $person->id, 'creator_id' => $this->lab->id, 'code' => 'DEBT-1']);
        $this->actingAs($this->lab);
    }

    private function user(string $name, int $role, ?int $creator = null): User
    {
        return User::create(['name' => $name, 'email' => $name.'@balance.test', 'password' => 'test-password-123', 'role_id' => $role, 'creator_id' => $creator]);
    }

    private function invoice(int $total = 10000, ?User $owner = null): Invoice
    {
        return Invoice::create(['lab_id_fk' => ($owner ?? $this->lab)->id, 'patient_id_fk' => $this->patient->id,
            'sub_total' => $total + 1000, 'discount' => 1000, 'total' => $total, 'paid' => 999999]);
    }

    private function pay(Invoice $invoice, int $amount, bool $deleted = false): void
    {
        $payment = $invoice->paidDetails()->create(['amount' => $amount, 'lab_id_fk' => $invoice->lab_id_fk]);
        if ($deleted) DB::table('invoice_paid_details')->where('id', $payment->id)->update(['deleted_at' => now()]);
    }

    public function test_balance_uses_net_totals_and_live_payments_without_netting_unrelated_credits(): void
    {
        $current = $this->invoice(15000);
        $this->pay($current, 3000); $this->pay($current, 2000); $this->pay($current, 50000, true);
        $old = $this->invoice(7000);
        DB::table('invoices')->where('id', $old->id)->update(['created_at' => '2020-01-01 00:00:00']);
        $paid = $this->invoice(); $this->pay($paid, 10000);
        $credit = $this->invoice(); $this->pay($credit, 30000);
        $deleted = $this->invoice(90000); $deleted->delete();
        $this->invoice(0);
        $before = DB::table('invoices')->orderBy('id')->get()->toJson();
        $r = $this->getJson('/api/invoices/'.$current->id.'/patient-balance')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('unpaid_invoice_count', 2)->json();
        $this->assertEquals(17000, $r['outstanding_amount']);
        $this->assertEquals(10000, $r['current_invoice_amount']);
        $this->assertEquals(7000, $r['other_invoices_amount']);
        $this->assertSame($before, DB::table('invoices')->orderBy('id')->get()->toJson());
    }

    public function test_paid_current_invoice_still_reports_old_debt_and_refreshes_after_settlement(): void
    {
        $current = $this->invoice(); $this->pay($current, 10000);
        $old = $this->invoice(5000);
        $r = $this->getJson('/api/invoices/'.$current->id.'/patient-balance')->assertOk()->json();
        $this->assertEquals(0, $r['current_invoice_amount']); $this->assertEquals(5000, $r['other_invoices_amount']);
        $this->pay($old, 5000);
        $this->getJson('/api/invoices/'.$current->id.'/patient-balance')->assertOk()
            ->assertJsonPath('outstanding_amount', 0)->assertJsonPath('unpaid_invoice_count', 0);
    }

    public function test_tenant_scope_includes_deleted_staff_but_excludes_other_patients_and_independent_labs(): void
    {
        $staff = $this->user('Staff', 6, $this->lab->id);
        $former = $this->user('Former', 6, $staff->id);
        $current = $this->invoice(5000, $staff); $this->invoice(7000, $former); $former->delete();
        $foreign = $this->invoice(99999, $this->user('OtherLab', 2));
        $this->invoice(99999, $this->user('ChildLab', 2, $this->lab->id));
        $person = $this->user('OtherPatient', 3, $this->lab->id);
        $other = Patient::create(['user_id' => $person->id, 'creator_id' => $this->lab->id, 'code' => 'DEBT-2']);
        $this->invoice(99999)->update(['patient_id_fk' => $other->id]);
        $staff->givePermissionTo('medical reports results view'); $this->actingAs($staff);
        $this->getJson('/api/invoices/'.$current->id.'/patient-balance')->assertOk()->assertJsonPath('outstanding_amount', 12000);
        $this->getJson('/api/invoices/'.$foreign->id.'/patient-balance')->assertNotFound();
        $admin = $this->user('Admin', 1); $this->actingAs($admin);
        $this->getJson('/api/invoices/'.$current->id.'/patient-balance')->assertOk()->assertJsonPath('outstanding_amount', 12000);
        $this->getJson('/api/invoices/'.$foreign->id.'/patient-balance')->assertOk()->assertJsonPath('outstanding_amount', 99999);
    }

    public function test_permissions_and_portal_accounts_are_enforced(): void
    {
        $invoice = $this->invoice();
        $staff = $this->user('Restricted', 6, $this->lab->id); $this->actingAs($staff);
        $this->getJson('/api/invoices/'.$invoice->id.'/patient-balance')->assertForbidden();
        $staff->givePermissionTo('invoices create');
        $this->getJson('/api/invoices/'.$invoice->id.'/patient-balance')->assertOk();
        foreach ([3, 5, 4] as $role) {
            $portal = $this->user('Portal'.$role, $role, $this->lab->id);
            $portal->givePermissionTo('invoices print');
            if ($role === 4) $portal->forceFill(['referral_portal_only' => true])->save();
            $this->actingAs($portal);
            $this->getJson('/api/invoices/'.$invoice->id.'/patient-balance')->assertForbidden();
        }
    }

    public function test_missing_deleted_and_unassigned_patient_invoices_are_not_misaggregated(): void
    {
        $invoice = $this->invoice(); $invoice->update(['patient_id_fk' => null]);
        $this->invoice(99999)->update(['patient_id_fk' => null]);
        $this->getJson('/api/invoices/'.$invoice->id.'/patient-balance')->assertOk()->assertJsonPath('outstanding_amount', 0);
        $invoice->delete();
        $this->getJson('/api/invoices/'.$invoice->id.'/patient-balance')->assertNotFound();
        $this->getJson('/api/invoices/999999/patient-balance')->assertNotFound();
    }
}
