<?php
namespace Tests\Feature;

use App\Models\{User, Patient, Invoice, InvoiceTestRel, PortalAccessToken, LabSetting, Test as LabTest, TestGroup, Package, Culture};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PatientPartialReportTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private Patient $patient;
    private Invoice $invoice;
    private PortalAccessToken $access;

    protected function setUp(): void
    {
        parent::setUp(); $this->seed(ReferenceDataSeeder::class);
        $this->lab = User::create(['name'=>'Partial Lab','email'=>'partial-lab@example.test','password'=>'test-password-123','role_id'=>2]);
        $user = User::create(['name'=>'Partial Patient','email'=>'partial-patient@example.test','password'=>'test-password-123','role_id'=>3,'creator_id'=>$this->lab->id]);
        $this->patient = Patient::create(['user_id'=>$user->id,'creator_id'=>$this->lab->id,'code'=>'PARTIAL']);
        LabSetting::create(['lab_id_fk'=>$this->lab->id,'loyalty_config'=>['enabled'=>false]]);
        $this->invoice = Invoice::create(['patient_id_fk'=>$this->patient->id,'lab_id_fk'=>$this->lab->id,'total'=>20000,'is_done'=>false,'notes'=>'INTERNAL-NOTE']);
        $this->access = PortalAccessToken::create(['patient_id_fk'=>$this->patient->id,'token'=>PortalAccessToken::generatePlainToken(),'expires_at'=>now()->addDay()]);
    }
    private function endpoint(?int $id = null): string { return '/api/portal/'.$this->access->token.'/reports/'.($id ?? $this->invoice->id); }
    private function testRow(string $name, bool $ready, string $result): InvoiceTestRel
    {
        $test = LabTest::create(['name'=>$name,'shortcut'=>$name,'unit'=>'mg/dL','lab_id_fk'=>$this->lab->id]);
        return InvoiceTestRel::create(['invoice_id_fk'=>$this->invoice->id,'test_id_fk'=>$test->id,'is_done'=>$ready,'result'=>$result]);
    }
    public function test_partial_invoice_lists_every_analysis_but_publishes_only_approved_results(): void
    {
        $this->testRow('Ready test',true,'0'); $this->testRow('Waiting test',false,'PRIVATE-DRAFT');
        $portal = $this->getJson('/api/portal/'.$this->access->token)->assertOk()->assertJsonPath('reports.0.status','partial')
            ->assertJsonCount(2,'reports.0.tests')->assertJsonPath('reports.0.ready_count',1)->assertJsonPath('reports.0.pending_count',1);
        $this->assertStringEndsWith('/result/'.$this->invoice->id, $portal->json('reports.0.view_url'));
        $this->assertStringNotContainsString('PRIVATE-DRAFT',$portal->getContent());
        $response = $this->getJson($this->endpoint())->assertOk()->assertJsonCount(1,'tests')->assertJsonPath('tests.0.result','0')
            ->assertJsonPath('portal_report.ready',1)->assertJsonPath('portal_report.pending',1)->assertJsonPath('is_done',false)
            ->assertJsonMissingPath('total')->assertJsonMissingPath('notes')->assertJsonMissingPath('test_groups_all');
        $this->assertStringNotContainsString('PRIVATE-DRAFT',$response->getContent());
        $this->assertStringNotContainsString('INTERNAL-NOTE',$response->getContent());
        $this->getJson('/api/invoices/public/'.$this->invoice->id)->assertForbidden();
    }
    public function test_pending_link_keeps_working_when_results_are_approved_or_retracted(): void
    {
        $row=$this->testRow('Pending',false,'PRIVATE-DRAFT');
        $this->getJson($this->endpoint())->assertOk()->assertJsonCount(0,'tests')->assertJsonPath('portal_report.ready',0);
        $row->update(['is_done'=>true,'result'=>'25']);
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('tests.0.result','25')->assertJsonPath('portal_report.ready',1);
        $this->getJson('/api/portal/'.$this->access->token)->assertOk()->assertJsonPath('reports.0.tests.0.status','ready');
        $row->update(['is_done'=>false]);
        $this->getJson($this->endpoint())->assertOk()->assertJsonCount(0,'tests')->assertJsonPath('portal_report.pending',1);
    }
    public function test_groups_packages_formulas_and_cultures_publish_independent_children_without_drafts(): void
    {
        $a=LabTest::create(['name'=>'A','shortcut'=>'A','lab_id_fk'=>$this->lab->id]);
        $b=LabTest::create(['name'=>'B','shortcut'=>'B','lab_id_fk'=>$this->lab->id]);
        $c=LabTest::create(['name'=>'C','shortcut'=>'C','lab_id_fk'=>$this->lab->id]);
        $group=TestGroup::create(['lab_id_fk'=>$this->lab->id,'group_name'=>'Nested group','formula'=>[['name'=>'Derived','tokens'=>['A','*','2'],'unit'=>'mg/dL']]]);
        $group->tests()->attach([$a->id,$b->id]);
        $package=Package::create(['lab_id_fk'=>$this->lab->id,'name'=>'Package']); $package->tests()->attach($c->id);$package->testGroups()->attach($group->id);
        $snapshot=[['id'=>$a->id,'name'=>'A','shortcut'=>'A','result'=>'4','is_done'=>true],['id'=>$b->id,'name'=>'B','result'=>'PRIVATE-PACKAGE','is_done'=>false],['id'=>$c->id,'name'=>'C','result'=>'9','is_done'=>true]];
        InvoiceTestRel::create(['invoice_id_fk'=>$this->invoice->id,'package_id_fk'=>$package->id,'package_tests'=>json_encode($snapshot),'package_cultures'=>[['name'=>'Pending culture','result'=>'PRIVATE-CULTURE','is_done'=>false]],'is_done'=>false,'comment'=>'PRIVATE-CONTAINER']);
        InvoiceTestRel::create(['invoice_id_fk'=>$this->invoice->id,'test_group_id_fk'=>$group->id,'test_group_tests'=>[['id'=>$a->id,'name'=>'A','shortcut'=>'A','result'=>'PRIVATE-GROUP','is_done'=>false],['id'=>$b->id,'name'=>'B','result'=>'5','is_done'=>true]],'is_done'=>false]);
        $response=$this->getJson($this->endpoint())->assertOk()->assertJsonCount(3,'packages.0.tests')->assertJsonCount(1,'test_groups.0.tests')->assertJsonCount(0,'packages.0.cultures')->assertJsonPath('portal_report.ready',4);
        $derived=collect($response->json('packages.0.tests'))->firstWhere('name','Derived');$this->assertEquals(8,$derived['result']);
        foreach (['PRIVATE-PACKAGE','PRIVATE-CULTURE','PRIVATE-CONTAINER','PRIVATE-GROUP'] as $secret) $this->assertStringNotContainsString($secret,$response->getContent());
        $this->assertSame([], $response->json('packages.0.formula'));
        $portal=$this->getJson('/api/portal/'.$this->access->token)->assertOk();
        $this->assertEquals(4,$portal->json('reports.0.ready_count'));$this->assertEquals(4,$portal->json('reports.0.pending_count'));
        $this->assertContains('Package · Nested group', array_column($portal->json('reports.0.tests'),'context'));
    }
    public function test_invalid_expired_otp_and_other_patient_tokens_cannot_view_results(): void
    {
        $this->testRow('Private',true,'SECRET');
        $other=Patient::create(['user_id'=>$this->lab->id,'creator_id'=>$this->lab->id,'code'=>'OTHER']);
        $foreign=Invoice::create(['patient_id_fk'=>$other->id,'lab_id_fk'=>$this->lab->id]);
        $this->getJson($this->endpoint($foreign->id))->assertNotFound();
        $this->getJson('/api/portal/invalid/reports/'.$this->invoice->id)->assertNotFound();
        $this->access->update(['expires_at'=>now()->subMinute()]);$this->getJson($this->endpoint())->assertNotFound();
        $this->access->update(['expires_at'=>now()->addDay()]);
        LabSetting::where('lab_id_fk',$this->lab->id)->update(['loyalty_config'=>['require_otp'=>true]]);
        $this->getJson($this->endpoint())->assertForbidden();
        $this->invoice->delete();LabSetting::where('lab_id_fk',$this->lab->id)->update(['loyalty_config'=>['require_otp'=>false]]);
        $this->getJson($this->endpoint())->assertNotFound();
    }
}
