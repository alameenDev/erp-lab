<?php
namespace Tests\Feature;

use App\Models\{User, Referal, PriceList, PriceListRel, Test as LabTest, Invoice, InvoiceTestRel, Patient, PaymentMethod};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralWorkspaceTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(): array
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name'=>'Destination','email'=>'destination@example.test','password'=>'secret-password-123','role_id'=>2]);
        $partner = User::create(['name'=>'Referral','email'=>'referral@example.test','password'=>'secret-password-123','role_id'=>5,'creator_id'=>$lab->id]);
        $other = User::create(['name'=>'Other referral','email'=>'other@example.test','password'=>'secret-password-123','role_id'=>5,'creator_id'=>$lab->id]);
        PaymentMethod::create(['name'=>'Cash','lab_id_fk'=>$partner->id]);
        $list = PriceList::create(['name'=>'Price list','lab_id_fk'=>$lab->id,'discount'=>10]);
        foreach ([$partner,$other] as $p) Referal::create(['referral_id_fk'=>$p->id,'lab_id_fk'=>$lab->id,'price_list_id_fk'=>$list->id]);
        $test = LabTest::create(['name'=>'Vitamin D','unit'=>'ng/mL','lab_id_fk'=>$lab->id]);
        PriceListRel::create(['lab_id_fk'=>$lab->id,'price_list_id_fk'=>$list->id,'test_id_fk'=>$test->id,'original_price'=>10000,'price_for_customer'=>8000]);
        $this->actingAs($partner);
        return [$lab,$partner,$other,$test];
    }
    private function payload(User $lab, LabTest $test): array
    {
        return ['destination_lab_id'=>$lab->id,'request_id'=>(string)Str::uuid(), 'inline_patient'=>['name'=>'Patient','gender_id_fk'=>1,'age'=>35,'age_unit_id_fk'=>1], 'tests'=>[['test_id_fk'=>$test->id]],'discount_type_id_fk'=>3,'discount'=>200,'payment_details'=>[['amount'=>2000,'payment_method_id_fk'=>1]]];
    }
    public function test_invoice_uses_server_price_and_independent_customer_payment_with_idempotent_retry(): void
    {
        [$lab,$partner,,$test]=$this->fixture(); $input=$this->payload($lab,$test);
        $this->getJson('/api/referral-portal/workspace/tests?destination_lab_id='.$lab->id)->assertOk()->assertJsonPath('0.price',7200);
        $r=$this->postJson('/api/referral-portal/workspace/invoices/create',$input)->assertCreated()->assertJsonPath('total',7000)->assertJsonPath('paid',2000);
        $id=$r->json('id');
        $this->assertDatabaseHas('invoices',['id'=>$id,'total'=>7200,'paid'=>0,'lab_id_fk'=>$lab->id,'from_lab_id_fk'=>$partner->id]);
        $this->assertDatabaseHas('referral_invoice_details',['invoice_id'=>$id,'total'=>7000,'paid'=>2000]);
        $this->assertDatabaseCount('patients',1);
        $this->postJson('/api/referral-portal/workspace/invoices/create',$input)->assertOk()->assertJsonPath('id',$id);
        $input['discount']=201;
        $this->postJson('/api/referral-portal/workspace/invoices/create',$input)->assertStatus(409);
        $this->assertDatabaseCount('patients',1);
        $this->getJson('/api/referral-portal/workspace/invoices/'.$id)->assertOk()->assertJsonPath('total',7000);
        $this->getJson('/api/referral-portal/workspace/invoices/'.$id.'?document=report')->assertStatus(409);
    }
    public function test_report_requires_all_results_and_is_scoped_to_current_partner(): void
    {
        [$lab,,$other,$test]=$this->fixture();
        $id=$this->postJson('/api/referral-portal/workspace/invoices/create',$this->payload($lab,$test))->assertCreated()->json('id');
        $rel=InvoiceTestRel::where('invoice_id_fk',$id)->firstOrFail();
        $rel->update(['result'=>'19','is_done'=>true]);
        Invoice::whereKey($id)->update(['is_done'=>true]);
        $this->getJson('/api/referral-portal/workspace/invoices/'.$id.'?document=report')->assertOk()->assertJsonPath('tests.0.result','19');
        $this->actingAs($other);
        $this->getJson('/api/referral-portal/workspace/invoices/'.$id.'?document=report')->assertNotFound();
        $this->postJson('/api/referral-portal/workspace/patients/search-name',['destination_lab_id'=>$lab->id,'name'=>'Patient'])->assertOk()->assertJsonCount(0);
    }
    public function test_print_settings_are_private_to_partner_and_do_not_change_destination_lab(): void
    {
        [$lab,,$other]=$this->fixture();
        $this->postJson('/api/referral-portal/workspace/lab-settings',['lab_display_name'=>'My referral','primary_color'=>'#123456'])->assertOk();
        $this->getJson('/api/referral-portal/workspace/lab-settings')->assertOk()->assertJsonPath('lab_display_name','My referral');
        $this->assertDatabaseHas('referral_print_settings',['lab_display_name'=>'My referral']);
        $this->assertDatabaseMissing('lab_settings',['lab_id_fk'=>$lab->id,'lab_display_name'=>'My referral']);
        $this->postJson('/api/referral-portal/workspace/lab-settings',['loyalty_config'=>['enabled'=>true]])->assertStatus(422);
        $this->actingAs($other);
        $this->getJson('/api/referral-portal/workspace/lab-settings')->assertOk()->assertJsonMissing(['lab_display_name'=>'My referral']);
    }
    public function test_unlisted_tests_and_other_partner_patient_ids_are_rejected(): void
    {
        [$lab,$partner,$other,$test]=$this->fixture();
        $id=$this->postJson('/api/referral-portal/workspace/invoices/create',$this->payload($lab,$test))->assertCreated()->json('id');
        $patientId=Invoice::findOrFail($id)->patient_id_fk;
        PaymentMethod::create(['name'=>'Other cash','lab_id_fk'=>$other->id]);
        $this->actingAs($other);
        $v=$this->payload($lab,$test);$v['payment_details'][0]['payment_method_id_fk']=2;unset($v['inline_patient']);$v['patient_id_fk']=$patientId;
        $this->postJson('/api/referral-portal/workspace/invoices/create',$v)->assertNotFound();
        $v=$this->payload($lab,$test);$v['payment_details'][0]['payment_method_id_fk']=2;$v['tests'][0]['test_id_fk']=999999;
        $this->postJson('/api/referral-portal/workspace/invoices/create',$v)->assertStatus(422);
        $this->assertDatabaseCount('invoices',1);
    }
}
