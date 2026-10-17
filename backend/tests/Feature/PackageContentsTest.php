<?php

namespace Tests\Feature;

use App\Models\{Category, Culture, Invoice, InvoiceTestRel, Patient, Package, Test as LabTest, TestGroup, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PackageContentsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_invoice_snapshots_keep_categories_for_tests_and_cultures_inside_groups_and_packages(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name'=>'Barcode Lab','email'=>'barcode-categories@example.test','password'=>'test-password-123','role_id'=>2]);
        $patient = Patient::create(['user_id'=>$lab->id,'creator_id'=>$lab->id,'code'=>'BC-1']);
        $category = Category::create(['name'=>'Microbiology','lab_id_fk'=>$lab->id]);
        $test = LabTest::create(['name'=>'Nested test','category_id_fk'=>$category->id,'lab_id_fk'=>$lab->id,'result_type_id_fk'=>1]);
        $group = TestGroup::create(['group_name'=>'Group','lab_id_fk'=>$lab->id]);
        $group->tests()->attach($test->id, ['order'=>1]);
        $culture = Culture::create(['name'=>'Nested culture','test_group_id_fk'=>$group->id,'category_id_fk'=>$category->id,'lab_id_fk'=>$lab->id]);
        $package = Package::create(['name'=>'Package','lab_id_fk'=>$lab->id]);
        $package->testGroups()->attach($group->id);
        $invoice = Invoice::create(['lab_id_fk'=>$lab->id,'patient_id_fk'=>$patient->id]);
        foreach ([['test_group_id_fk',$group->id,'test_group'],['package_id_fk',$package->id,'package']] as [$key,$id,$prefix]) {
            InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id,$key=>$id,
                $prefix.'_tests'=>[['id'=>$test->id,'name'=>$test->name,'result'=>'0','is_done'=>true]],
                $prefix.'_cultures'=>json_encode([['id'=>$culture->id,'name'=>$culture->name,'result'=>'unchanged','is_done'=>false]])]);
        }
        $response = $this->actingAs($lab)->getJson('/api/invoices/'.$invoice->id)->assertOk();
        foreach (['test_groups.0','packages.0','packages.0.test_groups.0'] as $path) {
            foreach (['tests','cultures'] as $field) {
                $response->assertJsonPath($path.'.'.$field.'.0.category', 'Microbiology')
                    ->assertJsonPath($path.'.'.$field.'.0.category_id_fk', $category->id);
            }
        }
        foreach (['test_groups.0','packages.0'] as $path) {
            $response->assertJsonPath($path.'.tests.0.result','0')->assertJsonPath($path.'.tests.0.is_done',true)
                ->assertJsonPath($path.'.cultures.0.result','unchanged')->assertJsonPath($path.'.cultures.0.is_done',false);
        }
    }

    public function test_package_list_and_details_include_group_membership_without_flattening_or_leaking_other_labs(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name'=>'Package Lab','email'=>'packages@example.test','password'=>'test-password-123','role_id'=>2]);
        $lab->givePermissionTo(Permission::findOrCreate('packages view', 'api'));
        $other = User::create(['name'=>'Other Lab','email'=>'other-packages@example.test','password'=>'test-password-123','role_id'=>2]);
        $category = Category::create(['name'=>'General','lab_id_fk'=>$lab->id]);
        $direct = LabTest::create(['name'=>'Glucose','shortcut'=>'GLU','category_id_fk'=>$category->id,'lab_id_fk'=>$lab->id,'result_type_id_fk'=>1]);
        $nested = LabTest::create(['name'=>'Hemoglobin','shortcut'=>'HGB','category_id_fk'=>$category->id,'lab_id_fk'=>$lab->id,'result_type_id_fk'=>1]);
        $group = TestGroup::create(['group_name'=>'CBC group','shortcut'=>'CBC','lab_id_fk'=>$lab->id]);
        $group->tests()->attach($nested->id, ['order'=>1]);
        $culture = Culture::create(['name'=>'Group culture','test_group_id_fk'=>$group->id,'lab_id_fk'=>$lab->id]);
        $package = Package::create(['name'=>'Mixed package','lab_id_fk'=>$lab->id]);
        $package->tests()->attach($direct->id);
        $package->testGroups()->attach($group->id);
        $foreign = Package::create(['name'=>'Private package','lab_id_fk'=>$other->id]);
        $this->actingAs($lab);

        $list = $this->getJson('/api/packages')->assertOk()->assertJsonCount(1)->json('0');
        $detail = $this->getJson('/api/packages/show?id='.$package->id)->assertOk()->json();
        foreach ([$list, $detail] as $data) {
            $this->assertSame([$direct->id], array_column($data['tests'], 'id'));
            $this->assertSame($group->id, $data['test_groups'][0]['id']);
            $this->assertSame('CBC group', $data['test_groups'][0]['group_name']);
            $this->assertSame(['id'=>$nested->id,'name'=>'Hemoglobin','shortcut'=>'HGB'], $data['test_groups'][0]['tests'][0]);
            $this->assertSame(['id'=>$culture->id,'name'=>'Group culture'], $data['test_groups'][0]['cultures'][0]);
        }
        $package->tests()->detach();
        $this->getJson('/api/packages/show?id='.$package->id)->assertOk()->assertJsonCount(0, 'tests')->assertJsonCount(1, 'test_groups');
        $package->testGroups()->detach();
        $this->getJson('/api/packages/show?id='.$package->id)->assertOk()->assertJsonCount(0, 'test_groups');
        $this->getJson('/api/packages/show?id='.$foreign->id)->assertForbidden();
    }
    private function orderFixtures(): array
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name' => 'Selection Order Lab', 'email' => 'selection-order@example.test', 'password' => 'test-password-123', 'role_id' => 2]);
        foreach (['packages view', 'packages create', 'packages edit', 'test groups view', 'test groups create', 'test groups edit'] as $permission) {
            $lab->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($lab);
        $tests = collect(['Alpha', 'Beta', 'Gamma'])->map(fn ($name) => LabTest::create([
            'name' => $name, 'lab_id_fk' => $lab->id, 'result_type_id_fk' => 1,
        ]));
        $cultures = collect(['Culture A', 'Culture B'])->map(fn ($name) => Culture::create([
            'name' => $name, 'lab_id_fk' => $lab->id,
        ]));

        return [$lab, $tests, $cultures];
    }

    public function test_group_selection_order_survives_save_reorder_removal_and_readding(): void
    {
        [$lab, $tests, $cultures] = $this->orderFixtures();
        $testIds = [$tests[2]->id, $tests[0]->id, $tests[1]->id];
        $cultureIds = [$cultures[1]->id, $cultures[0]->id];
        $input = ['group_name' => 'Ordered group', 'shortcut' => 'ORDER', 'test_ids' => $testIds, 'culture_ids' => $cultureIds];
        $this->postJson('/api/test_groups/create', $input)->assertOk();
        $group = TestGroup::where('lab_id_fk', $lab->id)->sole();
        $assertOrder = function (array $expectedTests, array $expectedCultures): void {
            $data = $this->getJson('/api/test_groups')->assertOk()->json('0');
            $this->assertSame($expectedTests, array_column($data['tests'], 'id'));
            $this->assertSame($expectedCultures, array_column($data['culture'], 'id'));
        };
        $assertOrder($testIds, $cultureIds);
        $input['id'] = $group->id;
        $input['test_ids'] = [$tests[1]->id, $tests[2]->id];
        $input['culture_ids'] = [$cultures[0]->id];
        $this->putJson('/api/test_groups/update', $input)->assertOk();
        $assertOrder($input['test_ids'], $input['culture_ids']);
        $this->assertNull($cultures[1]->fresh()->test_group_id_fk);
        $input['test_ids'][] = $tests[0]->id;
        $input['culture_ids'][] = $cultures[1]->id;
        $this->putJson('/api/test_groups/update', $input)->assertOk();
        $assertOrder($input['test_ids'], $input['culture_ids']);
        $this->assertSame([1, 2, 3], $group->fresh()->tests->pluck('pivot.order')->all());
        $input['test_ids'] = [];
        $input['culture_ids'] = [];
        $this->putJson('/api/test_groups/update', $input)->assertOk();
        $assertOrder([], []);
    }

    public function test_package_selection_order_is_returned_in_catalog_and_new_invoice_results(): void
    {
        [$lab, $tests, $cultures] = $this->orderFixtures();
        $groups = collect(['Group A', 'Group B'])->map(fn ($name) => TestGroup::create([
            'group_name' => $name, 'lab_id_fk' => $lab->id,
        ]));
        $groups[1]->tests()->attach([$tests[1]->id => ['order' => 1], $tests[0]->id => ['order' => 2]]);
        $input = [
            'name' => 'Ordered package', 'shortcut' => 'ORDER', 'is_constant_price' => true, 'price' => 5000,
            'tests' => [['id' => $tests[2]->id], ['id' => $tests[0]->id], ['id' => $tests[1]->id]],
            'cultures' => [['id' => $cultures[1]->id], ['id' => $cultures[0]->id]],
            'test_groups' => [['id' => $groups[1]->id], ['id' => $groups[0]->id]],
        ];
        $id = $this->postJson('/api/packages/create', $input)->assertOk()->json('id');
        $assertOrder = function (array $expected) use ($id): void {
            $detail = $this->getJson('/api/packages/show?id='.$id)->assertOk()->json();
            $list = $this->getJson('/api/packages')->assertOk()->json('0');
            foreach ([$detail, $list] as $data) {
                foreach (['tests', 'cultures', 'test_groups'] as $field) {
                    $this->assertSame(array_column($expected[$field], 'id'), array_column($data[$field], 'id'));
                }
            }
        };
        $assertOrder($input);
        $this->getJson('/api/packages/show?id='.$id)->assertOk()
            ->assertJsonPath('test_groups.0.tests.0.id', $tests[1]->id)
            ->assertJsonPath('test_groups.0.tests.1.id', $tests[0]->id);
        $input['id'] = $id;
        foreach (['tests', 'cultures', 'test_groups'] as $field) {
            $input[$field] = array_reverse($input[$field]);
        }
        $this->putJson('/api/packages/update', $input)->assertOk();
        $assertOrder($input);
        $patient = Patient::create(['user_id' => $lab->id, 'creator_id' => $lab->id, 'code' => 'ORDER-PACKAGE']);
        $invoice = Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id]);
        InvoiceTestRel::create(['invoice_id_fk' => $invoice->id, 'package_id_fk' => $id]);
        $invoiceData = $this->getJson('/api/invoices/'.$invoice->id)->assertOk()->json('packages.0');
        $this->assertSame(array_column($input['tests'], 'id'), array_column($invoiceData['tests'], 'id'));
        // Remove and add a member again: it belongs at the end, not at its catalog position.
        array_shift($input['tests']);
        array_shift($input['test_groups']);
        $input['cultures'] = [];
        $this->putJson('/api/packages/update', $input)->assertOk();
        $assertOrder($input);
        $input['tests'][] = ['id' => $tests[1]->id];
        $input['test_groups'][] = ['id' => $groups[0]->id];
        $this->putJson('/api/packages/update', $input)->assertOk();
        $assertOrder($input);
        $input['tests'][] = ['id' => $tests[1]->id];
        $this->putJson('/api/packages/update', $input)->assertUnprocessable()->assertJsonValidationErrors('tests.2.id');
    }

    public function test_legacy_package_members_keep_pivot_insertion_order_until_edited(): void
    {
        [$lab, $tests, $cultures] = $this->orderFixtures();
        $package = Package::create(['name' => 'Legacy package', 'lab_id_fk' => $lab->id]);
        foreach ([$tests[2], $tests[0], $tests[1]] as $test) {
            $package->tests()->attach($test->id);
        }
        $package->cultures()->attach([$cultures[1]->id, $cultures[0]->id]);
        $data = $this->getJson('/api/packages/show?id='.$package->id)->assertOk()->json();
        $this->assertSame([$tests[2]->id, $tests[0]->id, $tests[1]->id], array_column($data['tests'], 'id'));
        $this->assertSame([$cultures[1]->id, $cultures[0]->id], array_column($data['cultures'], 'id'));
    }
}
