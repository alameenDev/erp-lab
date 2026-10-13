<?php
namespace Tests\Feature;

use App\Models\{Category, Invoice, Patient, Test as LabTest, TestGroup, Package, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceSelectionOrderTest extends TestCase
{
    use DatabaseMigrations;

    public function test_selection_order_survives_creation_reopening_invoice_and_result_updates(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name'=>'Order Lab','email'=>'order@example.test','password'=>'test-password-123','role_id'=>2]);
        foreach (['invoices create','invoices edit','medical reports update'] as $permission) $lab->givePermissionTo(Permission::findOrCreate($permission,'api'));
        $this->actingAs($lab);
        $patient = Patient::create(['user_id'=>$lab->id,'creator_id'=>$lab->id,'code'=>'ORDER-1']);
        $category = Category::create(['name'=>'General','lab_id_fk'=>$lab->id]);
        $a = LabTest::create(['name'=>'First','category_id_fk'=>$category->id,'lab_id_fk'=>$lab->id,'result_type_id_fk'=>1]);
        $b = LabTest::create(['name'=>'Last','category_id_fk'=>$category->id,'lab_id_fk'=>$lab->id,'result_type_id_fk'=>1]);
        $group = TestGroup::create(['group_name'=>'Group','lab_id_fk'=>$lab->id]);
        $pkg = Package::create(['name'=>'Package','lab_id_fk'=>$lab->id]);
        $order = [['field'=>'tests','id'=>$b->id],['field'=>'packages','id'=>$pkg->id],['field'=>'test_groups','id'=>$group->id],['field'=>'tests','id'=>$a->id]];
        $input = ['patient_id_fk'=>$patient->id,'total'=>0,'sub_total'=>0,'selection_order'=>$order,
            'tests'=>[['test_id_fk'=>$a->id],['test_id_fk'=>$b->id]],'packages'=>[['package_id_fk'=>$pkg->id]],'test_groups'=>[['test_group_id_fk'=>$group->id]]];
        $id = $this->postJson('/api/invoices/create',$input)->assertOk()->json('id');
        $this->assertSame($order,Invoice::findOrFail($id)->selection_order);
        $this->getJson('/api/invoices/'.$id)->assertOk()->assertJsonPath('selection_order',$order);
        // Editing another field from an older client must not erase the order.
        $input['id']=$id; unset($input['selection_order']);
        $this->putJson('/api/invoices/update',$input)->assertOk();
        $this->postJson('/api/invoices/update-result',['id'=>$id,'tests'=>[['test_id_fk'=>$a->id,'result'=>'5','is_done'=>true]]])->assertOk();
        $this->getJson('/api/invoices/'.$id)->assertOk()->assertJsonPath('selection_order',$order);
        $input['selection_order']=array_reverse($order);
        $this->putJson('/api/invoices/update',$input)->assertOk();
        $this->getJson('/api/invoices/'.$id)->assertOk()->assertJsonPath('selection_order',array_reverse($order));
        $input['selection_order']=[['field'=>'patients','id'=>1]];
        $this->putJson('/api/invoices/update',$input)->assertUnprocessable()->assertJsonValidationErrors('selection_order.0.field');
    }
}
