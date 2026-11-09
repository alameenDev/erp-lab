<?php

namespace Tests\Feature;

use App\Models\{Category, Invoice, InvoiceTestRel, Package, Patient, Test as LabTest, TestGroup, User};
use App\Http\Resources\TestResource;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TestFieldVisibilityTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(): array
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name'=>'Visibility lab', 'email'=>'visibility@example.test', 'password'=>'test-password', 'role_id'=>2]);
        foreach (['tests create', 'tests edit', 'tests list', 'invoices create', 'invoices edit', 'medical reports update'] as $permission) {
            $lab->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($lab);
        $patient = Patient::create(['user_id'=>$lab->id, 'creator_id'=>$lab->id, 'code'=>'VISIBILITY']);
        $category = Category::create(['name'=>'Synthetic', 'lab_id_fk'=>$lab->id]);
        $test = LabTest::create(['name'=>'Analysis', 'category_id_fk'=>$category->id, 'lab_id_fk'=>$lab->id, 'result_type_id_fk'=>1, 'unit'=>'mg/dL']);
        $test->testReferenceRanges()->create(['from'=>1, 'to'=>6, 'notes'=>'Stored normal range']);
        return [$lab, $patient, $test];
    }

    public function test_catalog_defaults_independent_flags_partial_updates_and_validation(): void
    {
        [, , $test] = $this->fixture();
        $input = ['name'=>'New analysis', 'category_id_fk'=>$test->category_id_fk, 'result_type_id_fk'=>1, 'unit'=>'unit'];
        $id = $this->postJson('/api/tests/create', $input)->assertCreated()->json('test.id');
        $this->assertTrue(LabTest::findOrFail($id)->show_unit);
        $this->assertTrue(LabTest::findOrFail($id)->show_reference_range);
        foreach ([[false, true], [true, false], [false, false], [true, true]] as [$unit, $range]) {
            $this->putJson('/api/tests/update', $input + ['id'=>$id, 'show_unit'=>$unit, 'show_reference_range'=>$range])->assertOk();
            $this->putJson('/api/tests/update', $input + ['id'=>$id])->assertOk();
            $resource = TestResource::make(LabTest::findOrFail($id))->resolve();
            $this->assertSame($unit, $resource['show_unit']);
            $this->assertSame($range, $resource['show_reference_range']);
            $this->assertSame('unit', $resource['unit']);
        }
        $this->postJson('/api/tests/create', $input + ['show_unit'=>false, 'show_reference_range'=>false])->assertCreated();
        foreach (['show_unit', 'show_reference_range'] as $field) {
            $this->putJson('/api/tests/update', $input + ['id'=>$id, $field=>'invalid'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_live_visibility_reaches_standalone_groups_packages_and_saved_snapshots_without_erasing_results(): void
    {
        [$lab, $patient, $test] = $this->fixture();
        $group = TestGroup::create(['group_name'=>'Group', 'lab_id_fk'=>$lab->id]);
        $group->tests()->attach($test->id);
        $package = Package::create(['name'=>'Package', 'lab_id_fk'=>$lab->id]);
        $package->testGroups()->attach($group->id);
        $package->tests()->attach($test->id);
        $invoice = Invoice::create(['lab_id_fk'=>$lab->id, 'patient_id_fk'=>$patient->id, 'is_done'=>false]);
        InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id, 'test_id_fk'=>$test->id, 'result'=>'5']);
        InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id, 'test_group_id_fk'=>$group->id]);
        InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id, 'package_id_fk'=>$package->id]);
        $paths = ['tests.0', 'test_groups.0.tests.0', 'packages.0.tests.0'];
        $response = $this->getJson('/api/invoices/'.$invoice->id)->assertOk();
        foreach ($paths as $path) $response->assertJsonPath($path.'.show_unit', true)->assertJsonPath($path.'.show_reference_range', true);
        $row = ['id'=>$test->id, 'test_id_fk'=>$test->id, 'name'=>$test->name, 'result'=>'5', 'is_done'=>true, 'show_unit'=>true, 'show_reference_range'=>true];
        $this->postJson('/api/invoices/update-result', ['id'=>$invoice->id, 'tests'=>[$row],
            'test_groups'=>[['test_group_id_fk'=>$group->id, 'tests'=>[$row]]],
            'packages'=>[['package_id_fk'=>$package->id, 'tests'=>[$row]]]])->assertOk();
        foreach ([false, true] as $show) {
            $test->update(['show_unit'=>$show, 'show_reference_range'=>$show]);
            foreach (["/api/invoices/{$invoice->id}", "/api/invoices/public/{$invoice->id}"] as $url) {
                $response = $this->getJson($url)->assertOk();
                foreach ($paths as $path) {
                    $response->assertJsonPath($path.'.show_unit', $show)->assertJsonPath($path.'.show_reference_range', $show)
                        ->assertJsonPath($path.'.result', '5')->assertJsonPath($path.'.unit', 'mg/dL')
                        ->assertJsonPath($path.'.test_reference_ranges.0.notes', 'Stored normal range');
                }
            }
        }
        $this->assertSame('mg/dL', $test->fresh()->unit);
        $this->assertDatabaseHas('test_reference_ranges', ['test_id'=>$test->id, 'notes'=>'Stored normal range']);
    }

    public function test_other_labs_cannot_change_the_visibility(): void
    {
        [, , $test] = $this->fixture();
        $other = User::create(['name'=>'Other lab', 'email'=>'other-visibility@example.test', 'password'=>'test-password', 'role_id'=>2]);
        $other->givePermissionTo(Permission::findOrCreate('tests edit', 'api'));
        $this->actingAs($other)->putJson('/api/tests/update', ['id'=>$test->id, 'name'=>$test->name, 'category_id_fk'=>$test->category_id_fk,
            'result_type_id_fk'=>1, 'show_unit'=>false])->assertNotFound();
        $this->assertTrue($test->fresh()->show_unit);
    }
}
