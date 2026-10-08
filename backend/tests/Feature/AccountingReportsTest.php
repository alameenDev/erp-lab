<?php

namespace Tests\Feature;

use App\Models\{Culture, Invoice, InvoiceTestRel, Package, Patient, Referal, Test as LabTest, TestGroup, User};
use App\Services\AccountingReportService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccountingReportsTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private Patient $patient;
    private LabTest $analysis;
    private string $range = '?from=2026-10-01&to=2026-10-08';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026,10,8)->setTime(12,0));
        $this->seed(ReferenceDataSeeder::class);
        $this->lab=$this->user('Lab',2);
        $person=$this->user('Patient',3);
        $this->patient=Patient::create(['user_id'=>$person->id,'creator_id'=>$this->lab->id,'code'=>'ACC-1']);
        $this->analysis=LabTest::create(['name'=>'Glucose','shortcut'=>'GLU','price'=>2000,'for_customer_price'=>10000,'lab_id_fk'=>$this->lab->id]);
        $this->actingAs($this->lab);
    }

    private function user(string $name,int $role=2,?int $creator=null): User
    {
        return User::create(['name'=>$name,'email'=>strtolower(str_replace(' ','-',$name)).'@accounting.test','password'=>'testing-only-password','role_id'=>$role,'creator_id'=>$creator]);
    }

    private function invoice(array $attributes=[],bool $item=true): Invoice
    {
        $i=Invoice::create(array_merge(['lab_id_fk'=>$this->lab->id,'patient_id_fk'=>$this->patient->id,'sub_total'=>10000,'total'=>10000,'paid'=>999999,'discount'=>0,'discount_type_id_fk'=>1],$attributes));
        if($item)$i->invoiceTestRels()->create(['test_id_fk'=>$this->analysis->id,'price'=>10000,'result'=>'PRIVATE MEDICAL VALUE']);
        return $i;
    }

    private function pay(Invoice $invoice,int $amount,string $date='2026-10-08 12:00:00',bool $deleted=false): void
    {
        $p=$invoice->paidDetails()->create(['amount'=>$amount,'lab_id_fk'=>$this->lab->id]);
        DB::table('invoice_paid_details')->where('id',$p->id)->update(['created_at'=>$date,'deleted_at'=>$deleted?now():null]);
    }

    private function report(array $params=[]): array
    {
        return $this->getJson('/api/accounting-reports'.$this->range.'&'.http_build_query($params))->assertOk()->json();
    }

    public function test_ledger_discount_credits_and_profit_reconcile_without_mutating_financial_data(): void
    {
        $first=$this->invoice(['sub_total'=>20000,'discount'=>10,'discount_type_id_fk'=>2,'total'=>17000]);
        DB::table('invoices')->where('id',$first->id)->update(['loyalty_discount'=>1000]);
        $this->pay($first,5000);$this->pay($first,50000,deleted:true);
        $second=$this->invoice(['total'=>9500,'discount'=>500]);$this->pay($second,12000);
        $before=DB::table('invoices')->orderBy('id')->get()->toJson();
        $s=$this->report()['summary'];
        foreach(['total'=>26500,'paid'=>17000,'balance'=>12000,'credit'=>2500,'discount_amount'=>2500,'loyalty_discount'=>1000,'adjustment'=>0,'collections_in_period'=>17000,'cost_estimate'=>4000,'profit_estimate'=>22500,'analysis_count'=>2]as$key=>$value)$this->assertEquals($value,$s[$key],$key);
        $rows=$this->getJson('/api/accounting-reports/invoices'.$this->range)->assertOk()->json('data');
        $this->assertSame(['credit','partial'],array_column($rows,'payment_status'));
        $this->assertSame(1,$this->report(['status'=>'credit'])['summary']['invoice_count']);
        $this->assertSame(0,$this->report(['status'=>'paid'])['summary']['invoice_count']);
        $this->assertSame($before,DB::table('invoices')->orderBy('id')->get()->toJson());
        $this->assertStringNotContainsString('PRIVATE MEDICAL VALUE',json_encode($rows));
    }

    public function test_collections_include_old_invoices_by_payment_date_with_inclusive_end_day(): void
    {
        $old=$this->invoice();DB::table('invoices')->where('id',$old->id)->update(['created_at'=>'2026-09-01 12:00:00']);
        $this->pay($old,1000,'2026-09-30 23:59:59');$this->pay($old,2000,'2026-10-01 00:00:00');$this->pay($old,3000,'2026-10-08 23:59:59');$this->pay($old,4000,'2026-10-09 00:00:00');
        $r=$this->report();$this->assertEquals(0,$r['summary']['invoice_count']);$this->assertEquals(5000,$r['summary']['collections_in_period']);$this->assertCount(8,$r['trend']);
        $this->getJson('/api/accounting-reports/payments'.$this->range)->assertOk()->assertJsonPath('total',2)->assertJsonPath('data.0.invoice_id',$old->id)->assertJsonPath('data.0.patient_name','Patient');
    }

    public function test_tenant_scope_preserves_deleted_staff_history_and_blocks_foreign_details_exports_and_options(): void
    {
        $other=$this->user('Other Lab');$staff=$this->user('Old Staff',6,$this->lab->id);$childLab=$this->user('Separate Lab',2,$this->lab->id);
        $own=$this->invoice(['lab_id_fk'=>$staff->id]);$staff->delete();
        $foreign=$this->invoice(['lab_id_fk'=>$other->id]);$this->invoice(['lab_id_fk'=>$childLab->id]);$this->pay($foreign,9999);
        $r=$this->report();$this->assertSame(1,$r['summary']['invoice_count']);$this->assertEquals(0,$r['summary']['collections_in_period']);
        $this->getJson('/api/accounting-reports/invoices/'.$foreign->id)->assertNotFound();
        $this->getJson('/api/accounting-reports/invoices/'.$own->id)->assertOk()->assertJsonPath('created_by','Old Staff')->assertJsonPath('creator_source','invoice_account');
        $options=$this->getJson('/api/accounting-reports/options')->assertOk()->json();$this->assertSame([$staff->id],array_column($options['branches'],'id'));
        $this->assertSame(0,$this->report(['branch_id'=>$other->id])['summary']['invoice_count']);
        $csv=$this->get('/api/accounting-reports/export'.$this->range.'&section=invoices&format=csv')->assertOk()->streamedContent();$this->assertStringNotContainsString('Other Lab',$csv);$this->assertStringNotContainsString('Separate Lab',$csv);
    }

    public function test_permissions_and_portal_accounts_cannot_access_any_accounting_endpoint(): void
    {
        $this->invoice();$staff=$this->user('Staff',6,$this->lab->id);$this->actingAs($staff);
        foreach(['/api/accounting-reports','/api/accounting-reports/options','/api/accounting-reports/invoices','/api/accounting-reports/payments','/api/accounting-reports/invoices/1','/api/accounting-reports/export?section=invoices&format=csv']as$url)$this->getJson($url)->assertForbidden();
        $staff->givePermissionTo(Permission::findOrCreate('accounting reports view','api'));$this->report();
        $staff->forceFill(['referral_portal_only'=>true])->save();$this->getJson('/api/accounting-reports')->assertForbidden();
    }

    public function test_referrals_use_actual_entry_actor_and_doctor_commission_is_scoped_to_invoice_lab(): void
    {
        $staff=$this->user('Reception',6,$this->lab->id);$refLab=$this->user('Referring Lab',4,$this->lab->id);$doctor=$this->user('Doctor',5,$this->lab->id);$other=$this->user('Other');
        Referal::create(['lab_id_fk'=>$this->lab->id,'referral_id_fk'=>$doctor->id,'commission'=>10]);Referal::create(['lab_id_fk'=>$other->id,'referral_id_fk'=>$doctor->id,'commission'=>90]);
        $i=$this->invoice(['from_lab_id_fk'=>$refLab->id,'referral_id_fk'=>$doctor->id]);$this->pay($i,3000);
        DB::table('activity_log')->insert(['description'=>'إنشاء فاتورة','subject_type'=>Invoice::class,'subject_id'=>$i->id,'event'=>'created','causer_type'=>User::class,'causer_id'=>$staff->id,'audit_invoice_id'=>$i->id,'properties'=>json_encode(['changes'=>[['field'=>'invoice.total','label'=>'المبلغ','before'=>null,'after'=>10000],['field'=>'analyses.test_1.result','before'=>null,'after'=>'PRIVATE']]]),'created_at'=>now(),'updated_at'=>now()]);
        $r=$this->report();$this->assertSame('Referring Lab',$r['lab_referrals'][0]['name']);$this->assertSame('Reception',$r['operators'][0]['name']);$this->assertEquals(1000,$r['doctor_referrals'][0]['commission_estimate']);$this->assertEquals(7000,$r['summary']['profit_estimate']);
        $d=$this->getJson('/api/accounting-reports/invoices/'.$i->id)->assertOk()->assertJsonPath('created_by','Reception')->assertJsonPath('creator_source','audit')->json();$this->assertCount(1,$d['activity'][0]['changes']);$this->assertStringNotContainsString('PRIVATE',json_encode($d));
        $this->assertSame(1,$this->report(['created_by'=>$staff->id,'doctor_id'=>$doctor->id,'lab_referral_id'=>$refLab->id])['summary']['invoice_count']);
    }

    public function test_explicit_laboratory_referral_commission_is_included_and_portal_creator_has_an_honest_fallback(): void
    {
        $refLab=$this->user('Partner Lab',2,$this->lab->id);
        Referal::create(['lab_id_fk'=>$this->lab->id,'referral_id_fk'=>$refLab->id,'commission'=>10]);
        $invoice=$this->invoice(['referral_id_fk'=>$refLab->id,'from_lab_id_fk'=>$refLab->id,'referral_request_uuid'=>(string) \Illuminate\Support\Str::uuid()]);
        $r=$this->report();$this->assertEquals(1000,$r['summary']['commission_estimate']);$this->assertEquals(7000,$r['summary']['profit_estimate']);
        $this->getJson('/api/accounting-reports/invoices/'.$invoice->id)->assertOk()->assertJsonPath('created_by','Partner Lab')->assertJsonPath('creator_source','portal_account');
        $this->assertSame([], $r['doctor_referrals']);$this->assertSame('Partner Lab',$r['lab_referrals'][0]['name']);
    }

    public function test_groups_packages_and_flattened_snapshots_count_leaves_once_and_allocate_exact_revenue(): void
    {
        $nested=LabTest::create(['name'=>'Hemoglobin','shortcut'=>'HGB','price'=>1000,'lab_id_fk'=>$this->lab->id]);
        $group=TestGroup::create(['group_name'=>'CBC','original_price'=>1500,'lab_id_fk'=>$this->lab->id]);$group->tests()->attach($nested->id);
        $culture=Culture::create(['name'=>'Culture','price'=>500,'lab_id_fk'=>$this->lab->id,'test_group_id_fk'=>$group->id]);
        $package=Package::create(['name'=>'Checkup','price'=>99999,'lab_id_fk'=>$this->lab->id]);$package->tests()->attach($this->analysis->id);$package->testGroups()->attach($group->id);
        $i=$this->invoice(['sub_total'=>3,'total'=>2],false);
        $i->invoiceTestRels()->create(['test_id_fk'=>$this->analysis->id,'price'=>1]);
        $i->invoiceTestRels()->create(['test_group_id_fk'=>$group->id,'price'=>1]);
        $i->invoiceTestRels()->create(['package_id_fk'=>$package->id,'price'=>1,'package_tests'=>[['id'=>$this->analysis->id,'name'=>'Glucose','price'=>2000],['name'=>'Hemoglobin','price'=>1000]],'package_cultures'=>[['id'=>$culture->id,'name'=>'Culture','price'=>500]]]);
        $d=$this->getJson('/api/accounting-reports/invoices/'.$i->id)->assertOk()->json();
        $this->assertSame([1,2,3],array_column($d['items'],'analysis_count'));$this->assertSame([1,1,0],array_column($d['items'],'net_allocated'));$this->assertEquals(7000,$d['cost_estimate']);$this->assertEquals(6,$d['analysis_count']);
        $r=$this->report();$this->assertEquals(2,array_sum(array_column($r['items'],'net_allocated')));$this->assertEquals(6,array_sum(array_column($r['analyses'],'count')));
    }

    public function test_unknown_cost_and_missing_doctor_rates_are_not_reported_as_zero_profit(): void
    {
        $this->analysis->update(['price'=>null]);$doctor=$this->user('Unconfigured Doctor',5,$this->lab->id);$this->invoice(['referral_id_fk'=>$doctor->id]);
        $r=$this->report();foreach(['cost_estimate','commission_estimate','profit_estimate']as$key)$this->assertNull($r['summary'][$key]);$this->assertNull($r['items'][0]['profit_estimate']);$this->assertNull($r['doctor_referrals'][0]['commission_estimate']);
        $this->assertEquals(1,$r['summary']['incomplete_profit_invoices']);
        $this->analysis->update(['price'=>0]);Referal::create(['lab_id_fk'=>$this->lab->id,'referral_id_fk'=>$doctor->id,'commission'=>0]);$this->assertEquals(10000,$this->report()['summary']['profit_estimate']);
    }

    public function test_exports_include_all_filtered_pages_escape_html_and_spreadsheet_formulas(): void
    {
        $this->patient->user->update(['name'=>'=HYPERLINK("bad") <script>alert(1)</script>']);
        for($n=0;$n<27;$n++)$this->invoice();
        $this->getJson('/api/accounting-reports/invoices'.$this->range.'&per_page=10&page=2')->assertOk()->assertJsonPath('total',27)->assertJsonCount(10,'data');
        $csv=$this->get('/api/accounting-reports/export'.$this->range.'&section=invoices&format=csv')->assertOk()->streamedContent();$this->assertSame(28,substr_count($csv,"\n"));$this->assertStringContainsString("'=HYPERLINK",$csv);
        $html=$this->get('/api/accounting-reports/export'.$this->range.'&section=invoices&format=print')->assertOk()->streamedContent();$this->assertStringNotContainsString('<script>',$html);$this->assertStringContainsString('&lt;script&gt;',$html);$this->assertStringContainsString('عدد السجلات: 27',$html);$this->assertStringContainsString('table-header-group',$html);
        $this->get('/api/accounting-reports/export'.$this->range.'&section=overview&format=csv')->assertOk();
        $this->getJson('/api/accounting-reports?from=2026-10-08&to=2026-10-01')->assertUnprocessable();$this->getJson('/api/accounting-reports/invoices?per_page=101')->assertUnprocessable();
    }

    public function test_allocation_is_conservative_for_discounts_zero_prices_and_negative_adjustments(): void
    {
        foreach([[999,[100,200,300]],[1,[1,1,1]],[100,[0,0,0]],[-17,[5,6,7]],[0,[1,2]]]as[$amount,$weights])$this->assertSame($amount,array_sum(AccountingReportService::allocate($amount,$weights)));
        $i=$this->invoice([],false);$i->delete();$r=$this->report();$this->assertEquals(0,$r['summary']['invoice_count']);$this->assertSame([],$r['items']);$this->assertEquals([0,0,0,0],$r['aging']);
    }
}
