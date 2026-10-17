<?php

namespace Tests\Feature;

use App\Models\{Invoice, InvoiceTestRel, Package, Patient, Test as LabTest, TestGroup, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MedicalReportTestFilterTest extends TestCase
{
    use DatabaseMigrations;

    private User $lab;
    private Patient $patient;
    private LabTest $cbc;
    private LabTest $glucose;
    private LabTest $creatinine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->lab = User::create(['name' => 'Report Filter Lab', 'email' => 'filter-lab@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $this->lab->givePermissionTo(Permission::findOrCreate('invoices view', 'api'));
        $patientUser = User::create(['name' => 'Selected patient', 'email' => 'filter-patient@example.test', 'password' => 'test-password']);
        $this->patient = Patient::create(['user_id' => $patientUser->id, 'creator_id' => $this->lab->id, 'code' => 'FILTER-PATIENT']);
        $this->cbc = $this->testDefinition('Complete Blood Count', 'CBC');
        $this->glucose = $this->testDefinition('Glucose', 'GLU');
        $this->creatinine = $this->testDefinition('Creatinine', 'CRE');
        $this->actingAs($this->lab);
    }

    private function testDefinition(string $name, string $shortcut): LabTest
    {
        return LabTest::create(['name' => $name, 'shortcut' => $shortcut, 'lab_id_fk' => $this->lab->id, 'result_type_id_fk' => 1]);
    }

    private function invoice(array $rows, array $attributes = []): Invoice
    {
        $invoice = Invoice::create(array_merge(['lab_id_fk' => $this->lab->id, 'patient_id_fk' => $this->patient->id, 'total' => 1000, 'is_done' => false], $attributes));
        $invoice->invoiceTestRels()->createMany($rows);

        return $invoice;
    }

    private function filtered(array $ids, array $params = [])
    {
        return $this->getJson('/api/invoices?'.http_build_query(array_merge(['test_ids' => $ids, 'per_page' => 100], $params)));
    }

    public function test_all_selected_tests_match_direct_group_package_and_mixed_rows_before_pagination_and_stats(): void
    {
        $direct = $this->invoice([['test_id_fk' => $this->cbc->id], ['test_id_fk' => $this->glucose->id]], ['is_done' => true]);
        // Two separate visits by the same patient do not satisfy an AND filter.
        $this->invoice([['test_id_fk' => $this->cbc->id]]);
        $this->invoice([['test_id_fk' => $this->glucose->id]]);
        $group = TestGroup::create(['group_name' => 'Group', 'lab_id_fk' => $this->lab->id]);
        $group->tests()->attach([$this->cbc->id, $this->glucose->id]);
        $groupInvoice = $this->invoice([['test_group_id_fk' => $group->id]]);
        $nested = TestGroup::create(['group_name' => 'Nested', 'lab_id_fk' => $this->lab->id]);
        $nested->tests()->attach($this->cbc->id);
        $package = Package::create(['name' => 'Package', 'lab_id_fk' => $this->lab->id]);
        $package->tests()->attach($this->glucose->id);
        $package->testGroups()->attach($nested->id);
        $packageInvoice = $this->invoice([['package_id_fk' => $package->id]]);
        $mixed = $this->invoice([['test_id_fk' => $this->glucose->id], ['test_group_id_fk' => $nested->id]]);
        $foreignLab = User::create(['name' => 'Other lab', 'email' => 'filter-other@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $this->invoice([['test_id_fk' => $this->cbc->id], ['test_id_fk' => $this->glucose->id]], ['lab_id_fk' => $foreignLab->id]);

        $ids = [$this->cbc->id, $this->glucose->id];
        $response = $this->filtered($ids)->assertOk()->assertJsonPath('pagination.total', 4)->assertJsonPath('stats.total', 4);
        $this->assertEqualsCanonicalizing([$direct->id, $groupInvoice->id, $packageInvoice->id, $mixed->id], array_column($response->json('data'), 'id'));
        $this->filtered($ids, ['per_page' => 2, 'page' => 2])->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination.current_page', 2)->assertJsonPath('stats.total', 4)->assertJsonPath('stats.total_amount', 4000);
        $this->filtered($ids, ['is_done' => 'done', 'patient_name' => 'Selected patient'])->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $direct->id)->assertJsonPath('stats.total', 1);
        $this->filtered([$this->cbc->id])->assertOk()->assertJsonPath('pagination.total', 5);
        $this->filtered([])->assertOk()->assertJsonPath('pagination.total', 6);
    }

    public function test_saved_membership_wins_over_catalog_edits_and_supports_encoded_snapshots(): void
    {
        $group = TestGroup::create(['group_name' => 'Changed group', 'lab_id_fk' => $this->lab->id]);
        $group->tests()->attach($this->creatinine->id);
        $package = Package::create(['name' => 'Changed package', 'lab_id_fk' => $this->lab->id]);
        $package->testGroups()->attach($group->id);
        $snapshot = [['id' => (string) $this->cbc->id], ['test_id_fk' => $this->glucose->id]];
        $groupInvoice = $this->invoice([['test_group_id_fk' => $group->id, 'test_group_tests' => $snapshot]]);
        $packageInvoice = $this->invoice([['package_id_fk' => $package->id, 'package_tests' => json_encode($snapshot)]]);
        $this->invoice([['test_group_id_fk' => $group->id, 'test_group_tests' => []]]);
        $this->invoice([['package_id_fk' => $package->id, 'package_tests' => []]]);
        $response = $this->filtered([$this->cbc->id, $this->glucose->id])->assertOk()->assertJsonPath('pagination.total', 2);
        $this->assertEqualsCanonicalizing([$groupInvoice->id, $packageInvoice->id], array_column($response->json('data'), 'id'));
        $this->filtered([$this->creatinine->id])->assertOk()->assertJsonPath('pagination.total', 0);
        foreach ([null, 'null', 'invalid legacy value'] as $legacy) {
            $this->invoice([['test_group_id_fk' => $group->id, 'test_group_tests' => $legacy]]);
        }
        $this->filtered([$this->creatinine->id])->assertOk()->assertJsonPath('pagination.total', 3);
    }

    public function test_options_and_validation_use_report_permissions_and_the_current_lab_scope(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 'filter-staff@example.test', 'password' => 'test-password', 'role_id' => 3, 'creator_id' => $this->lab->id]);
        $staff->givePermissionTo(Permission::findOrCreate('invoices view', 'api'));
        $staffTest = LabTest::create(['name' => 'Staff test', 'lab_id_fk' => $staff->id]);
        $foreign = User::create(['name' => 'Private lab', 'email' => 'filter-private@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $foreignTest = LabTest::create(['name' => 'Private test', 'lab_id_fk' => $foreign->id]);
        $this->cbc->delete();
        $this->actingAs($staff);
        $options = $this->getJson('/api/invoices/test-filter-options')->assertOk()->json();
        $this->assertEqualsCanonicalizing([$this->cbc->id, $this->glucose->id, $this->creatinine->id, $staffTest->id], array_column($options, 'id'));
        $this->assertArrayNotHasKey('price', $options[0]);
        $this->filtered([$foreignTest->id])->assertUnprocessable()->assertJsonValidationErrors('test_ids');
        $this->filtered([$this->cbc->id, $this->cbc->id])->assertUnprocessable()->assertJsonValidationErrors('test_ids.0');
        $this->getJson('/api/invoices?test_ids=invalid')->assertUnprocessable()->assertJsonValidationErrors('test_ids');
        $this->getJson('/api/invoices?'.http_build_query(['test_ids' => range(1, 51)]))->assertUnprocessable()->assertJsonValidationErrors('test_ids');
        $this->actingAs($foreign)->getJson('/api/invoices/test-filter-options')->assertUnauthorized();
    }

    public function test_patient_medical_records_apply_the_same_test_filter_without_leaking_other_patients(): void
    {
        $matching = $this->invoice([['test_id_fk' => $this->cbc->id], ['test_id_fk' => $this->glucose->id]]);
        $this->invoice([['test_id_fk' => $this->creatinine->id]]);
        $otherPatient = Patient::create(['user_id' => $this->lab->id, 'creator_id' => $this->lab->id, 'code' => 'OTHER-FILTER-PATIENT']);
        $this->invoice([['test_id_fk' => $this->cbc->id]], ['patient_id_fk' => $otherPatient->id]);
        $url = '/api/invoices/patient-medical-records/'.$this->patient->id;
        $this->getJson($url.'?'.http_build_query(['test_ids' => [$this->cbc->id, $this->glucose->id]]))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $matching->id);
        $this->getJson($url)->assertOk()->assertJsonCount(2);
        $this->getJson($url.'?test_ids=invalid')->assertUnprocessable();
    }
}
