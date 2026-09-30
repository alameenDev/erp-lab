<?php

namespace Tests\Feature;

use App\Models\{Category, Invoice, InvoiceTestRel, Package, Patient, Test as LabTest, TestGroup, User};
use App\Services\DefaultTestResults;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DefaultTestResultsTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(): array
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name' => 'Lab', 'email' => 'defaults@example.test', 'password' => 'test-password-123', 'role_id' => 2]);
        foreach (['tests create', 'tests edit', 'tests list', 'invoices create', 'invoices edit', 'medical reports update'] as $permission) {
            $lab->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($lab);
        $patient = Patient::create(['user_id' => $lab->id, 'creator_id' => $lab->id, 'code' => 'DEFAULT-1']);
        $category = Category::create(['name' => 'General', 'lab_id_fk' => $lab->id]);
        $test = LabTest::create(['name' => 'True / False', 'category_id_fk' => $category->id, 'lab_id_fk' => $lab->id,
            'result_type_id_fk' => 4, 'selection_type_options' => ['True', 'False'], 'default_result' => 'True']);
        return [$lab, $patient, $test];
    }

    public function test_default_can_be_created_changed_cleared_and_invalid_options_are_rejected(): void
    {
        [$lab, , $test] = $this->fixture();
        $input = ['name' => 'Choice', 'category_id_fk' => $test->category_id_fk, 'result_type_id_fk' => 4,
            'selection_type_options' => ['True', 'False'], 'default_result' => 'True'];
        $response = $this->postJson('/api/tests/create', $input);
        $this->assertSame(201, $response->status(), $response->getContent());
        $id = $response->json('test.id');
        $this->assertDatabaseHas('tests', ['id' => $id, 'default_result' => 'True']);
        $input['id'] = $id;
        $input['default_result'] = 'False';
        $this->putJson('/api/tests/update', $input)->assertOk();
        $this->assertSame('False', LabTest::findOrFail($id)->default_result);
        $resource = (new \App\Http\Resources\TestResource(LabTest::findOrFail($id)))->resolve();
        $this->assertSame('False', $resource['default_result']);
        $input['default_result'] = 'Missing';
        $this->putJson('/api/tests/update', $input)->assertUnprocessable()->assertJsonValidationErrors('default_result');
        $input['default_result'] = null;
        $this->putJson('/api/tests/update', $input)->assertOk();
        $this->assertNull(LabTest::findOrFail($id)->default_result);
        $input['default_result'] = 'True';
        $this->putJson('/api/tests/update', $input)->assertOk();
        unset($input['default_result']);
        $input['selection_type_options'] = ['False'];
        $this->putJson('/api/tests/update', $input)->assertOk();
        $this->assertNull(LabTest::findOrFail($id)->default_result);
        $input['result_type_id_fk'] = 1;
        $input['default_result'] = 'False';
        $this->putJson('/api/tests/update', $input)->assertUnprocessable();
    }

    public function test_new_invoice_persists_editable_defaults_for_standalone_group_and_package_tests(): void
    {
        [$lab, $patient, $test] = $this->fixture();
        $group = TestGroup::create(['group_name' => 'Group', 'lab_id_fk' => $lab->id]);
        $package = Package::create(['name' => 'Package', 'lab_id_fk' => $lab->id]);
        $group->tests()->attach($test->id);
        $package->tests()->attach($test->id);
        $snapshot = (new \App\Http\Resources\TestResource($test))->resolve();
        $input = ['patient_id_fk' => $patient->id, 'total' => 0, 'sub_total' => 0,
            'tests' => [['test_id_fk' => $test->id, 'result' => null]],
            'packages' => [['package_id_fk' => $package->id, 'package_tests' => [array_merge($snapshot, ['result' => 'False'])]]],
            'test_groups' => [['test_group_id_fk' => $group->id, 'test_group_tests' => json_encode([$snapshot])]]];
        $response = $this->postJson('/api/invoices/create', $input);
        $this->assertSame(200, $response->status(), $response->getContent());
        $id = $response->json('id');
        $rel = InvoiceTestRel::where('invoice_id_fk', $id)->where('test_id_fk', $test->id)->firstOrFail();
        $this->assertSame('True', $rel->result);
        $this->assertFalse($rel->is_done);
        $this->assertFalse((bool) Invoice::findOrFail($id)->is_done);
        $groupRel = InvoiceTestRel::where('invoice_id_fk', $id)->whereNotNull('test_group_id_fk')->firstOrFail();
        $packageRel = InvoiceTestRel::where('invoice_id_fk', $id)->whereNotNull('package_id_fk')->firstOrFail();
        $this->assertSame('True', $this->items($groupRel->test_group_tests)[0]['result']);
        $this->assertSame('False', $this->items($packageRel->package_tests)[0]['result']);
        $input['id'] = $id;
        $input['tests'][0]['result'] = 'False';
        $this->putJson('/api/invoices/update', $input)->assertOk();
        $this->assertDatabaseHas('invoice_test_rels', ['invoice_id_fk' => $id, 'test_id_fk' => $test->id, 'result' => 'False']);
        $this->postJson('/api/invoices/update-result', ['id' => $id,
            'tests' => [['test_id_fk' => $test->id, 'result' => 'True', 'is_done' => false]]])->assertOk();
        $this->postJson('/api/invoices/update-result', ['id' => $id,
            'tests' => [['test_id_fk' => $test->id, 'result' => 'False', 'is_done' => false]]])->assertOk();
        $this->assertDatabaseHas('invoice_test_rels', ['invoice_id_fk' => $id, 'test_id_fk' => $test->id, 'result' => 'False', 'is_done' => false]);
    }

    public function test_editing_invoice_seeds_only_new_analyses_and_preserves_old_blank_results(): void
    {
        [$lab, $patient, $test] = $this->fixture();
        $newTest = $test->replicate();
        $newTest->name = 'New choice';
        $newTest->save();
        $invoice = Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id, 'is_done' => false]);
        InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'test_id_fk' => $test->id, 'result' => null, 'is_done' => false]);
        $input = ['id' => $invoice->id, 'patient_id_fk' => $patient->id, 'total' => 0, 'sub_total' => 0,
            'tests' => [['test_id_fk' => $test->id, 'result' => null], ['test_id_fk' => $newTest->id, 'result' => null]]];
        $this->putJson('/api/invoices/update', $input)->assertOk();
        $this->assertDatabaseHas('invoice_test_rels', ['invoice_id_fk' => $invoice->id, 'test_id_fk' => $test->id, 'result' => null]);
        $this->assertDatabaseHas('invoice_test_rels', ['invoice_id_fk' => $invoice->id, 'test_id_fk' => $newTest->id, 'result' => 'True']);
        $input['tests'][1]['result'] = null;
        $this->putJson('/api/invoices/update', $input)->assertOk();
        $this->assertDatabaseHas('invoice_test_rels', ['invoice_id_fk' => $invoice->id, 'test_id_fk' => $newTest->id, 'result' => null]);
    }

    public function test_existing_nested_results_zero_values_and_tenant_boundaries_are_respected(): void
    {
        [$lab, $patient, $test] = $this->fixture();
        $group = TestGroup::create(['group_name' => 'Group', 'lab_id_fk' => $lab->id]);
        $invoice = Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id]);
        InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'test_group_id_fk' => $group->id,
            'test_group_tests' => json_encode([['id' => $test->id, 'result' => null]])]);
        $input = ['tests' => [['test_id_fk' => $test->id, 'result' => 0]],
            'test_groups' => [['test_group_id_fk' => $group->id, 'tests' => [['id' => $test->id, 'result' => null]]]]];
        $request = new Request($input);
        app(DefaultTestResults::class)->prepare($request, [$lab->id], $invoice);
        $this->assertSame(0, $request->input('tests.0.result'));
        $this->assertNull($request->input('test_groups.0.tests.0.result'));
        $request = new Request(['tests' => [['test_id_fk' => $test->id]]]);
        app(DefaultTestResults::class)->prepare($request, []);
        $this->assertNull($request->input('tests.0.result'));
        $test->update(['default_result' => null]);
        app(DefaultTestResults::class)->prepare($request, [$lab->id]);
        $this->assertNull($request->input('tests.0.result'));
    }

    private function items(mixed $value): array
    {
        while (is_string($value)) $value = json_decode($value, true);
        return $value;
    }
}
