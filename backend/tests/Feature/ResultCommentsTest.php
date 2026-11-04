<?php

namespace Tests\Feature;

use App\Models\{Category, Invoice, InvoiceTestRel, LabSetting, Package, Patient, Test as LabTest, TestGroup, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ResultCommentsTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(): array
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name'=>'Comments lab', 'email'=>'comments@example.test', 'password'=>'test-password-123', 'role_id'=>2]);
        foreach (['tests create', 'tests edit', 'tests list', 'invoices create', 'invoices edit', 'medical reports update'] as $permission) {
            $lab->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($lab);
        $patient = Patient::create(['user_id'=>$lab->id, 'creator_id'=>$lab->id, 'code'=>'COMMENTS-1']);
        $category = Category::create(['name'=>'General', 'lab_id_fk'=>$lab->id]);
        $test = LabTest::create(['name'=>'Analysis', 'category_id_fk'=>$category->id, 'lab_id_fk'=>$lab->id,
            'result_type_id_fk'=>1, 'result_comments'=>['First choice', 'Second choice']]);
        return [$lab, $patient, $test];
    }

    public function test_catalog_presets_can_be_created_updated_and_cleared_and_reject_invalid_items(): void
    {
        [, , $test] = $this->fixture();
        $input = ['name'=>'New analysis', 'category_id_fk'=>$test->category_id_fk, 'result_type_id_fk'=>1,
            'result_comments'=>["تعليق جاهز\nSecond line", 'Other choice']];
        $response = $this->postJson('/api/tests/create', $input)->assertCreated();
        $id = $response->json('test.id');
        $this->assertSame($input['result_comments'], LabTest::findOrFail($id)->result_comments);
        $input['id'] = $id;
        $input['result_comments'] = ['Changed choice'];
        $this->putJson('/api/tests/update', $input)->assertOk();
        $this->assertSame(['Changed choice'], LabTest::findOrFail($id)->result_comments);
        $input['result_comments'] = [];
        $this->putJson('/api/tests/update', $input)->assertOk();
        $this->assertSame([], LabTest::findOrFail($id)->result_comments);
        $input['result_comments'] = [['invalid'=>'object']];
        $this->putJson('/api/tests/update', $input)->assertUnprocessable()->assertJsonValidationErrors('result_comments.0');
        $input['result_comments'] = [str_repeat('a', 2001)];
        $this->postJson('/api/tests/create', $input)->assertUnprocessable()->assertJsonValidationErrors('result_comments.0');
    }

    public function test_patient_comments_round_trip_independently_and_presets_remain_live_for_existing_containers(): void
    {
        [$lab, $patient, $test] = $this->fixture();
        $group = TestGroup::create(['group_name'=>'Group', 'lab_id_fk'=>$lab->id]);
        $group->tests()->attach($test->id);
        $package = Package::create(['name'=>'Package', 'lab_id_fk'=>$lab->id]);
        $package->tests()->attach($test->id);
        $invoice = Invoice::create(['lab_id_fk'=>$lab->id, 'patient_id_fk'=>$patient->id, 'is_done'=>false]);
        InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id, 'test_id_fk'=>$test->id]);
        InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id, 'test_group_id_fk'=>$group->id]);
        InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id, 'package_id_fk'=>$package->id]);
        $this->getJson('/api/invoices/'.$invoice->id)->assertOk()
            ->assertJsonPath('tests.0.result_comments', ['First choice', 'Second choice'])
            ->assertJsonPath('tests.0.comment', null);
        $row = ['id'=>$test->id, 'test_id_fk'=>$test->id, 'name'=>$test->name, 'result'=>'5', 'is_done'=>true, 'result_comments'=>['Old snapshot']];
        $patientComment = str_repeat('تعليق طويل قابل للطباعة ', 40)."\nSecond line";
        $input = ['id'=>$invoice->id, 'tests'=>[array_merge($row, ['comment'=>$patientComment])],
            'test_groups'=>[['test_group_id_fk'=>$group->id, 'tests'=>[array_merge($row, ['comment'=>'Group comment'])]]],
            'packages'=>[['package_id_fk'=>$package->id, 'tests'=>[array_merge($row, ['comment'=>'Package comment'])]]]];
        $this->postJson('/api/invoices/update-result', $input)->assertOk();
        $test->update(['name'=>'Renamed analysis', 'result_comments'=>['New choice']]);
        foreach (["/api/invoices/{$invoice->id}", "/api/invoices/public/{$invoice->id}"] as $url) {
            $this->getJson($url)->assertOk()
                ->assertJsonPath('tests.0.comment', $patientComment)
                ->assertJsonPath('test_groups.0.tests.0.comment', 'Group comment')
                ->assertJsonPath('packages.0.tests.0.comment', 'Package comment')
                ->assertJsonPath('test_groups.0.tests.0.result_comments', ['New choice'])
                ->assertJsonPath('packages.0.tests.0.result_comments', ['New choice']);
        }
        $test->update(['result_comments'=>[]]);
        $this->getJson('/api/invoices/'.$invoice->id)->assertOk()->assertJsonPath('test_groups.0.tests.0.result_comments', []);
        $input['tests'][0]['comment'] = '';
        $this->postJson('/api/invoices/update-result', $input)->assertOk();
        $this->assertEmpty(InvoiceTestRel::where('invoice_id_fk', $invoice->id)->where('test_id_fk', $test->id)->value('comment'));
    }

    public function test_visibility_is_per_lab_validated_preserved_on_partial_save_public_and_resettable(): void
    {
        [$lab] = $this->fixture();
        $this->postJson('/api/lab-settings', ['print_table_config'=>['show_comments'=>'0', 'body_font_size'=>14]])->assertOk()
            ->assertJsonPath('setting.print_table_config.show_comments', false);
        $this->getJson('/api/lab-settings/'.$lab->id)->assertOk()->assertJsonPath('print_table_config.show_comments', false);
        $this->postJson('/api/lab-settings', ['print_table_config'=>['cell_padding'=>8]])->assertOk()
            ->assertJsonPath('setting.print_table_config.show_comments', false)->assertJsonPath('setting.print_table_config.body_font_size', 14);
        $this->postJson('/api/lab-settings', ['print_table_config'=>['show_comments'=>'invalid']])->assertUnprocessable()
            ->assertJsonValidationErrors('print_table_config.show_comments');
        $staff = User::create(['name'=>'Staff', 'email'=>'comment-staff@example.test', 'password'=>'test-password-123', 'role_id'=>7, 'creator_id'=>$lab->id]);
        $this->actingAs($staff)->postJson('/api/lab-settings', ['print_table_config'=>['show_comments'=>true]])->assertForbidden();
        $other = User::create(['name'=>'Other lab', 'email'=>'comment-other@example.test', 'password'=>'test-password-123', 'role_id'=>2]);
        $this->actingAs($other)->getJson('/api/lab-settings')->assertOk();
        $this->assertNull(LabSetting::where('lab_id_fk', $other->id)->first()->print_table_config);
        $this->actingAs($lab)->postJson('/api/lab-settings/reset', ['section'=>'print'])->assertOk()->assertJsonPath('setting.print_table_config', null);
    }
}
