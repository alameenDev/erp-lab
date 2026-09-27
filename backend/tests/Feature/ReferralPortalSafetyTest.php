<?php
namespace Tests\Feature;
use App\Models\{User, Referal, PriceList, PriceListRel, Test as LabTest, Invoice, InvoiceTestRel, Patient};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;
class ReferralPortalSafetyTest extends TestCase {
    use DatabaseMigrations;
    private function fixture(): array {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name'=>'Main lab','email'=>'owner@example.test','password'=>'secret-password-123','role_id'=>2]);
        $partner = User::create(['name'=>'Partner','email'=>'partner@example.test','password'=>'secret-password-123','role_id'=>5,'creator_id'=>$lab->id]);
        $list = PriceList::create(['name'=>'Partner prices','lab_id_fk'=>$lab->id,'discount'=>10]);
        Referal::create(['referral_id_fk'=>$partner->id,'lab_id_fk'=>$lab->id,'price_list_id_fk'=>$list->id]);
        $test = LabTest::create(['name'=>'D3','unit'=>'ng/mL','lab_id_fk'=>$lab->id]);
        PriceListRel::create(['lab_id_fk'=>$lab->id,'price_list_id_fk'=>$list->id,'test_id_fk'=>$test->id,'original_price'=>10000,'price_for_customer'=>8000]);
        $this->actingAs($partner);
        return [$lab,$partner,$test,$list];
    }
    private function data($lab,$test): array {
        return ['request_id'=>(string)Str::uuid(),'lab_id'=>$lab->id,'patient_name'=>'Patient A','patient_phone'=>'07701234567','patient_dob'=>'1990-01-01','test_ids'=>[$test->id]];
    }
    public function test_totals_and_idempotent_retry_are_server_owned(): void {
        [$lab,$partner,$test]=$this->fixture(); $v=$this->data($lab,$test);
        $this->getJson('/api/referral-portal/price-list/'.$lab->id)->assertOk()->assertJsonPath('tests.0.price',7200);
        $r=$this->postJson('/api/referral-portal/invoices',$v)->assertCreated()->assertJsonPath('total',7200);
        $this->postJson('/api/referral-portal/invoices',$v)->assertOk()->assertJsonPath('id',$r->json('id'));
        $v['patient_name']='Different'; $this->postJson('/api/referral-portal/invoices',$v)->assertStatus(409);
        $this->assertDatabaseCount('invoices',1); $this->assertDatabaseCount('patients',1);
        $this->assertDatabaseHas('invoice_test_rels',['price'=>7200]);
    }
    public function test_shared_phone_does_not_merge_patients_and_reuse_is_explicit(): void {
        [$lab,,$test]=$this->fixture(); $v=$this->data($lab,$test);
        $r=$this->postJson('/api/referral-portal/invoices',$v)->assertCreated();
        $v['request_id']=(string)Str::uuid(); $v['patient_name']='Family member';
        $this->postJson('/api/referral-portal/invoices',$v)->assertCreated(); $this->assertDatabaseCount('patients',2);
        $p=Invoice::findOrFail($r->json('id'))->patient_id_fk;
        $v=$this->data($lab,$test); $v['patient_id']=$p;
        $this->postJson('/api/referral-portal/invoices',$v)->assertCreated(); $this->assertDatabaseCount('patients',2);
        $this->getJson('/api/referral-portal/patients?lab_id='.$lab->id.'&name=Patient')->assertOk()->assertJsonCount(1);
    }
    public function test_unlisted_deleted_and_duplicate_tests_are_rejected_without_writes(): void {
        [$lab,,$test]=$this->fixture(); $v=$this->data($lab,$test);
        $other=LabTest::create(['name'=>'Not listed','lab_id_fk'=>$lab->id]);
        $v['test_ids']=[$other->id]; $this->postJson('/api/referral-portal/invoices',$v)->assertStatus(422);
        $v['test_ids']=[$test->id,$test->id]; $this->postJson('/api/referral-portal/invoices',$v)->assertStatus(422);
        $v['test_ids']=[$test->id]; $test->delete(); $this->postJson('/api/referral-portal/invoices',$v)->assertStatus(422);
        $this->assertDatabaseCount('patients',0); $this->assertDatabaseCount('invoices',0);
    }
    public function test_failure_after_patient_creation_rolls_everything_back(): void {
        [$lab,,$test]=$this->fixture();
        InvoiceTestRel::creating(fn () => throw new \RuntimeException('simulated failure'));
        try { $this->postJson('/api/referral-portal/invoices',$this->data($lab,$test))->assertStatus(500); }
        finally { InvoiceTestRel::flushEventListeners(); }
        $this->assertDatabaseCount('patients',0); $this->assertDatabaseCount('users',2); $this->assertDatabaseCount('invoices',0);
    }
    public function test_partner_cannot_read_another_partner_or_staff_endpoints(): void {
        [$lab,$partner,$test]=$this->fixture(); $v=$this->data($lab,$test);
        $r=$this->postJson('/api/referral-portal/invoices',$v)->assertCreated();
        $other=User::create(['name'=>'Other','email'=>'other@example.test','password'=>'secret-password-123','role_id'=>5]);
        Referal::create(['lab_id_fk'=>$lab->id,'referral_id_fk'=>$other->id]); $this->actingAs($other);
        $this->getJson('/api/referral-portal/invoices/'.$r->json('id'))->assertNotFound();
        $this->getJson('/api/referral-portal/patients?lab_id='.$lab->id.'&name=Patient')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/lab-settings')->assertForbidden();
    }
    public function test_report_masks_unreleased_values_and_includes_units_and_ranges(): void {
        [$lab,,$test]=$this->fixture();
        $r=$this->postJson('/api/referral-portal/invoices',$this->data($lab,$test))->assertCreated(); $id=$r->json('id');
        DB::table('invoice_test_rels')->where('invoice_id_fk',$id)->update(['result'=>'0','is_done'=>true]);
        $this->getJson('/api/referral-portal/invoices/'.$id)->assertOk()->assertJsonPath('tests.0.result',null);
        DB::table('test_reference_range')->insert(['lab_id_fk'=>$lab->id,'test_id'=>$test->id,'gender_id_fk'=>1,'age_unit_id_fk'=>1,'age_from'=>18,'age_to'=>100,'from'=>'20','to'=>'50']);
        Invoice::findOrFail($id)->update(['is_done'=>true]);
        $this->getJson('/api/referral-portal/invoices/'.$id)->assertOk()->assertJsonPath('tests.0.result','0')->assertJsonPath('tests.0.unit','ng/mL')->assertJsonPath('tests.0.reference_ranges.0.from','20');
    }
    public function test_create_dedicated_account_requires_permission_and_own_price_list(): void {
        [$lab,,$test,$list]=$this->fixture(); $this->actingAs($lab);
        $v=['name'=>'New Partner','email'=>'new@example.test','password'=>'Partner-secret-123','password_confirmation'=>'Partner-secret-123','price_list_id_fk'=>$list->id];
        $this->postJson('/api/referrals/portal-account',$v)->assertForbidden();
        $lab->givePermissionTo('referrals create');
        $foreign=User::create(['name'=>'Foreign','email'=>'foreign@example.test','password'=>'secret-password-123','role_id'=>2]);
        $bad=PriceList::create(['name'=>'Foreign prices','lab_id_fk'=>$foreign->id]);
        $this->postJson('/api/referrals/portal-account',array_merge($v,['price_list_id_fk'=>$bad->id]))->assertStatus(422);
        $this->postJson('/api/referrals/portal-account',$v)->assertCreated();
        $u=User::where('email',$v['email'])->firstOrFail();
        $this->assertTrue(Hash::check($v['password'],$u->password)); $this->assertTrue((bool)$u->referral_portal_only);
        $this->assertSame(0,$u->permissions()->count()); $this->assertSame(0,$u->roles()->count());
        \App\Models\Subscription::create(['lab_id_fk'=>$lab->id,'plan_name'=>'Test','status'=>'active','start_date'=>now(),'end_date'=>now()->addMonth()]);
        // A real login is a fresh request, not the RequestGuard retained by preceding test API calls.
        \Illuminate\Support\Facades\Auth::shouldUse('web');
        $this->postJson('/api/user/login',['email'=>$v['email'],'password'=>$v['password']])->assertOk()->assertJsonPath('user.is_referral_partner',true);
        Referal::where('referral_id_fk',$u->id)->delete(); $this->actingAs($u, 'sanctum');
        $this->getJson('/api/lab-settings')->assertForbidden();
    }
}
