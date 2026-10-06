<?php

namespace Tests\Feature;

use App\Models\{Category, Invoice, InvoiceTestRel, Package, Patient, Test as LabTest, TestGroup, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GroupReportPagesTest extends TestCase
{
    use DatabaseMigrations;

    public function test_group_page_setting_can_be_saved_and_changed_for_existing_direct_and_package_reports(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name' => 'Report Lab', 'email' => 'group-pages@example.test', 'password' => 'test-password-123', 'role_id' => 2]);
        foreach (['test groups create', 'test groups edit', 'test groups list'] as $permission) {
            $lab->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($lab);
        $patient = Patient::create(['user_id' => $lab->id, 'creator_id' => $lab->id, 'code' => 'PAGES-1']);
        $category = Category::create(['name' => 'General', 'lab_id_fk' => $lab->id]);
        $test = LabTest::create(['name' => 'Sample test', 'category_id_fk' => $category->id, 'lab_id_fk' => $lab->id, 'result_type_id_fk' => 1]);
        $input = ['group_name' => 'Isolated group', 'test_ids' => [$test->id], 'is_print_alone' => 1];
        $this->postJson('/api/test_groups/create', $input)->assertSuccessful();
        $group = TestGroup::where('lab_id_fk', $lab->id)->where('group_name', 'Isolated group')->firstOrFail();
        $this->assertTrue($group->is_print_alone);
        $package = Package::create(['name' => 'Package', 'lab_id_fk' => $lab->id]);
        $package->testGroups()->attach($group->id);
        $invoice = Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id, 'is_done' => true]);
        $approved = [['id' => $test->id, 'name' => $test->name, 'result' => '0', 'is_done' => true]];
        InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'test_group_id_fk' => $group->id, 'is_done' => true, 'test_group_tests' => $approved]);
        InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'package_id_fk' => $package->id, 'is_done' => true, 'package_tests' => $approved]);

        foreach ([1, 0, 1] as $flag) {
            $this->putJson('/api/test_groups/update', array_merge($input, ['id' => $group->id, 'is_print_alone' => $flag]))->assertOk();
            $this->assertSame((bool) $flag, $group->fresh()->is_print_alone);
            // Staff and public/WhatsApp reports share the same current setting,
            // even when the invoice was created before the setting was edited.
            foreach (["/api/invoices/{$invoice->id}", "/api/invoices/public/{$invoice->id}"] as $url) {
                $this->getJson($url)->assertOk()
                    ->assertJsonPath('test_groups.0.is_print_alone', (bool) $flag)
                    ->assertJsonPath('packages.0.test_groups.0.is_print_alone', (bool) $flag);
            }
        }
    }
}
