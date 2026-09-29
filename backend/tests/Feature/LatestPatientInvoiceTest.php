<?php

namespace Tests\Feature;

use App\Models\{Invoice, InvoiceTestRel, Patient, Test as LabTest, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LatestPatientInvoiceTest extends TestCase
{
    use DatabaseMigrations;

    public function test_latest_invoice_is_tenant_scoped_and_contains_only_a_reorder_summary(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name' => 'Lab', 'email' => 'lab@example.test', 'password' => 'secret-password-123', 'role_id' => 2]);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret-password-123', 'role_id' => 2]);
        $person = User::create(['name' => 'Patient', 'email' => 'patient@example.test', 'password' => 'secret-password-123', 'role_id' => 6]);
        $patient = Patient::create(['user_id' => $person->id, 'creator_id' => $lab->id, 'code' => 'P-1']);
        $test = LabTest::create(['name' => 'Vitamin D', 'unit' => 'ng/mL', 'lab_id_fk' => $lab->id]);
        Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id, 'is_done' => true]);
        $latest = Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id, 'is_done' => false, 'total' => 5000, 'paid' => 4000]);
        $latest->paidDetails()->create(['amount' => 2000, 'lab_id_fk' => $lab->id]);
        InvoiceTestRel::create(['invoice_id_fk' => $latest->id, 'test_id_fk' => $test->id, 'price' => 5000, 'result' => '19', 'is_done' => true]);
        Invoice::create(['lab_id_fk' => $other->id, 'patient_id_fk' => $patient->id, 'total' => 99999]);
        $this->actingAs($lab);
        $this->getJson('/api/invoices/patient-latest/'.$patient->id)->assertForbidden();
        $lab->givePermissionTo('invoices create');
        $response = $this->getJson('/api/invoices/patient-latest/'.$patient->id)->assertOk()
            ->assertJsonPath('invoice.id', $latest->id)->assertJsonPath('invoice.is_done', false)
            ->assertJsonPath('invoice.items.0.id', $test->id)->assertJsonPath('invoice.items.0.type', 'test')
            ->assertJsonMissingPath('invoice.items.0.result');
        $this->assertEquals(2000, $response->json('invoice.paid'));
        $test->delete();
        $this->getJson('/api/invoices/patient-latest/'.$patient->id)->assertOk()->assertJsonPath('invoice.items.0.deleted', true);
        $this->getJson('/api/invoices/patient-latest/999999')->assertOk()->assertJsonPath('invoice', null);
    }
}
