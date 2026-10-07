<?php

namespace Tests\Feature;

use App\Models\{Category, Culture, Package, Test as LabTest, TestGroup, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PackageContentsTest extends TestCase
{
    use DatabaseMigrations;

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
}
