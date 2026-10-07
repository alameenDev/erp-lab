<?php
namespace Tests\Feature;

use App\Http\Resources\TestResource;
use App\Models\{Category, Invoice, InvoiceTestRel, Package, Patient, PortalNotification, Test as LabTest, TestGroup, User};
use App\Services\ReportReadiness;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FormulaCompletionTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private Invoice $invoice;
    private array $tests;

    protected function setUp(): void
    {
        parent::setUp(); $this->seed(ReferenceDataSeeder::class);
        $this->lab = User::create(['name'=>'Formula Lab','email'=>'formula@example.test','password'=>'test-password-123','role_id'=>2]);
        $this->lab->givePermissionTo(Permission::findOrCreate('medical reports update','api')); $this->actingAs($this->lab);
        $user = User::create(['name'=>'Synthetic patient','email'=>'patient@example.test','password'=>'test-password-123','role_id'=>3,'creator_id'=>$this->lab->id]);
        $patient = Patient::create(['user_id'=>$user->id,'creator_id'=>$this->lab->id,'code'=>'SYNTHETIC']);
        $this->invoice = Invoice::create(['lab_id_fk'=>$this->lab->id,'patient_id_fk'=>$patient->id,'is_done'=>false]);
        $category = Category::create(['name'=>'General','lab_id_fk'=>$this->lab->id]);
        $this->tests = [];
        foreach (['A','B','C'] as $name) $this->tests[$name] = LabTest::create(['name'=>$name,'shortcut'=>$name,'category_id_fk'=>$category->id,'lab_id_fk'=>$this->lab->id,'result_type_id_fk'=>1]);
    }
    private function snapshots(): array
    {
        return array_map(fn ($test) => array_merge((new TestResource($test->fresh()))->resolve(), ['result'=>$test->name === 'A' ? '4' : null,'is_done'=>$test->name === 'A']), array_values($this->tests));
    }
    private function group(array $formula): TestGroup
    {
        $group = TestGroup::create(['lab_id_fk'=>$this->lab->id,'group_name'=>'Formula group','formula'=>$formula]);
        $group->tests()->attach(array_map(fn ($t)=>$t->id,$this->tests)); return $group;
    }
    private function save(array $extra = []): array
    {
        $response = $this->postJson('/api/invoices/update-result', ['id'=>$this->invoice->id]+$extra);
        $this->assertSame(200,$response->status(),$response->getContent()); return $response->json();
    }
    public function test_group_targets_are_saved_and_complete_only_after_inputs_are_approved(): void
    {
        $group = $this->group([['name'=>'B','tokens'=>['A','*','2']],['name'=>'C','tokens'=>['B','+','B']]]);
        $tests=$this->snapshots(); $tests[0]['is_done']=false;
        $rel=InvoiceTestRel::create(['invoice_id_fk'=>$this->invoice->id,'test_group_id_fk'=>$group->id,'test_group_tests'=>$tests,'is_done'=>false]);
        $data=$this->save(); $this->assertFalse($data['is_done']);
        $this->assertEquals(8,$rel->fresh()->test_group_tests[1]['result']); $this->assertFalse($rel->fresh()->test_group_tests[1]['is_done']);
        $this->assertDatabaseCount('portal_notifications',0);
        $tests[0]['is_done']=true;
        $data=$this->save(['test_groups'=>[['test_group_id_fk'=>$group->id,'tests'=>$tests,'formula'=>[['name'=>'B','tokens'=>['9','9','9']]]]]]);
        $this->assertTrue($data['is_done']); $this->assertEquals(16,$data['test_groups'][0]['tests'][2]['result']);
        $this->assertTrue($rel->fresh()->test_group_tests[2]['is_done']);
        $this->assertTrue(app(ReportReadiness::class)->isReady($this->invoice->fresh()));
        $this->assertDatabaseCount('portal_notifications',1); $this->save(); $this->assertDatabaseCount('portal_notifications',1);
        $this->getJson('/api/invoices/public/'.$this->invoice->id)->assertOk();
    }
    public function test_package_including_attached_group_formulas_repairs_on_save_without_changing_measured_values(): void
    {
        $group=$this->group([['name'=>'B','tokens'=>['A','*','2']]]);
        $pkg=Package::create(['lab_id_fk'=>$this->lab->id,'name'=>'Package','formula'=>[['name'=>'C','tokens'=>['B','+','1']]]]);
        $pkg->tests()->attach($this->tests['A']->id); $pkg->testGroups()->attach($group->id);
        $tests=$this->snapshots(); unset($tests[0]['shortcut']); // imported snapshot metadata fallback
        foreach ($tests as &$test) { $test['test_group_name']=$group->group_name; $test['test_group_id_fk']=$group->id; } unset($test);
        $rel=InvoiceTestRel::create(['invoice_id_fk'=>$this->invoice->id,'package_id_fk'=>$pkg->id,'package_tests'=>json_encode($tests),'is_done'=>false]);
        $this->getJson('/api/invoices/'.$this->invoice->id)->assertOk(); $this->assertFalse($rel->fresh()->is_done);
        $data=$this->save(); $this->assertTrue($data['is_done']);
        $saved=$rel->fresh()->package_tests;
        $this->assertSame('4',$saved[0]['result']); $this->assertEquals(8,$saved[1]['result']); $this->assertEquals(9,$saved[2]['result']);
        $this->assertDatabaseCount('portal_notifications',1);
        $saved[0]['result']='0';
        $data=$this->save(['packages'=>[['package_id_fk'=>$pkg->id,'tests'=>$saved]]]);
        $this->assertTrue($data['is_done']); $this->assertEquals(0,$rel->fresh()->package_tests[1]['result']);
        $this->assertEquals(1,$rel->fresh()->package_tests[2]['result']); $this->assertDatabaseCount('portal_notifications',1);
    }
    public function test_unfinished_culture_and_unrelated_analysis_still_block_a_calculated_report(): void
    {
        $group=$this->group([['name'=>'B','tokens'=>['A','*','2']]]);
        $tests=$this->snapshots();
        $rel=InvoiceTestRel::create(['invoice_id_fk'=>$this->invoice->id,'test_group_id_fk'=>$group->id,'test_group_tests'=>$tests,'test_group_cultures'=>[['name'=>'Culture','is_done'=>false]],'is_done'=>false]);
        $data=$this->save(); $this->assertFalse($data['is_done']); $this->assertFalse($rel->fresh()->test_group_tests[2]['is_done']);
        $tests[2]['is_done']=true; $tests[2]['result']='Negative'; $rel->update(['test_group_tests'=>$tests]);
        $this->assertFalse($this->save()['is_done']); $this->assertDatabaseCount('portal_notifications',0);
        $rel->update(['test_group_cultures'=>[['name'=>'Culture','is_done'=>true]]]);
        $this->assertTrue($this->save()['is_done']);
    }
    public function test_invalid_formula_cannot_be_approved_by_a_forged_target_result(): void
    {
        $group=$this->group([['name'=>'B','tokens'=>['A','/','0']],['name'=>'C','tokens'=>['B','+','1']]]);
        $tests=$this->snapshots(); foreach($tests as &$test){$test['is_done']=true;$test['result']='777';}unset($test);
        $rel=InvoiceTestRel::create(['invoice_id_fk'=>$this->invoice->id,'test_group_id_fk'=>$group->id,'test_group_tests'=>$tests,'is_done'=>true]);
        $this->invoice->update(['is_done'=>true]); $this->assertFalse(app(ReportReadiness::class)->isReady($this->invoice->fresh()));
        $this->assertFalse($this->save()['is_done']); $this->assertNull($rel->fresh()->test_group_tests[1]['result']);
        $this->assertDatabaseCount('portal_notifications',0);
        $this->getJson('/api/invoices/public/'.$this->invoice->id)->assertForbidden();
    }
}
