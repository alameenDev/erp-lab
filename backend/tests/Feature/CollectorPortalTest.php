<?php
namespace Tests\Feature;

use App\Models\{CollectionSubmission,CollectionTask,CollectorNotification,CollectorPushSubscription,Invoice,InvoiceTestRel,Patient,PaymentMethod,Referal,Subscription,User,Test as LabTest,TestGroup,Package};
use App\Services\{CollectorTasks,CollectorNotifications,PortalPushTransport};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB,Auth};
use Illuminate\Support\Str;
use Tests\TestCase;

class CollectorPortalTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab,$source,$collector,$otherLab;
    private LabTest $analysis;
    private PaymentMethod $method;
    protected function setUp():void
    {
        parent::setUp();$this->seed(ReferenceDataSeeder::class);
        $this->lab=$this->user('lab',2);$this->lab->assignRole(2);
        $this->otherLab=$this->user('other-lab',2);$this->otherLab->assignRole(2);
        $this->source=$this->user('source',4);
        Referal::create(['lab_id_fk'=>$this->lab->id,'referral_id_fk'=>$this->source->id,'commission'=>0]);
        Subscription::create(['lab_id_fk'=>$this->lab->id,'plan_name'=>'Lab','status'=>'active','start_date'=>today(),'end_date'=>today()->addYear()]);
        $this->actingAs($this->lab);
        $id=$this->postJson('/api/sample-logistics/accounts',['name'=>'Collector','email'=>'collector@test.invalid','password'=>'safe-password-123','status'=>true])->assertCreated()->json('id');
        $this->collector=User::findOrFail($id);
        $this->putJson('/api/sample-logistics/assignments',['source_lab_id'=>$this->source->id,'collector_id'=>$id])->assertOk();
        $this->analysis=LabTest::create(['name'=>'CBC','unit'=>'g/dL','lab_id_fk'=>$this->lab->id,'price'=>10000,'for_customer_price'=>12000]);
        $this->method=PaymentMethod::create(['name'=>'نقدي','lab_id_fk'=>$this->lab->id]);
    }
    private function user(string $name,?int $role=null):User {return User::create(['name'=>$name,'email'=>$name.'@test.invalid','password'=>'test-password-123','role_id'=>$role,'status'=>1,'email_verified_at'=>now()]);}
    private function payload(array $overrides=[]):array
    {
        return array_replace(['request_id'=>(string)Str::uuid(),'source_lab_id'=>$this->source->id,'registration_date'=>today()->toDateString(),
            'inline_patient'=>['name'=>'Patient One','age'=>32,'age_unit_id_fk'=>1,'gender_id_fk'=>1,'phone_number'=>'+9647701234567','address'=>'Baghdad','passport_no'=>'P123','national_id_no'=>'123456789'],
            'tests'=>[['test_id_fk'=>$this->analysis->id,'price'=>1,'result'=>'FAKE RESULT','is_done'=>true]],
            'payment_details'=>[['amount'=>2000,'payment_method_id_fk'=>$this->method->id]],'discount'=>1000,'discount_type_id_fk'=>3,'work_register_amount'=>777,'notes'=>'Keep cool','selection_order'=>[['field'=>'tests','id'=>$this->analysis->id]]],$overrides);
    }
    private function submit(array $payload=[]):int {$this->actingAs($this->collector);return $this->postJson('/api/collector-portal/workspace/invoices/create',$payload?:$this->payload())->assertCreated()->assertJsonPath('status','pending')->json('id');}
    private function invoice():Invoice
    {
        $patientUser=$this->user('patient',3);$p=Patient::create(['user_id'=>$patientUser->id,'creator_id'=>$this->lab->id,'age'=>30,'age_unit_id_fk'=>1,'gender_id_fk'=>1,'code'=>'P1']);
        return DB::transaction(function()use($p){$i=Invoice::create(['patient_id_fk'=>$p->id,'lab_id_fk'=>$this->lab->id,'from_lab_id_fk'=>$this->source->id,'total'=>12000,'sub_total'=>12000,'paid'=>0,'is_done'=>false]);InvoiceTestRel::create(['invoice_id_fk'=>$i->id,'test_id_fk'=>$this->analysis->id,'price'=>12000,'result'=>'SECRET MEDICAL RESULT','is_done'=>true]);return $i;});
    }
    public function test_collector_is_restricted_and_management_cannot_cross_tenants():void
    {
        $this->actingAs($this->collector);$this->getJson('/api/collector-portal/context')->assertOk()->assertJsonPath('lab.id',$this->lab->id);
        foreach(['/api/invoices','/api/patients','/api/sample-logistics/accounts','/api/owner-portal/me'] as $url)$this->getJson($url)->assertForbidden();
        $this->assertCount(0,$this->collector->roles);$this->assertNull($this->collector->role_id);
        $this->actingAs($this->otherLab);$this->getJson('/api/sample-logistics/accounts')->assertOk()->assertJsonCount(0,'data');
        $this->putJson('/api/sample-logistics/accounts/'.$this->collector->id,['name'=>'Hack','email'=>'hack@test.invalid','status'=>true])->assertNotFound();
        $this->putJson('/api/sample-logistics/assignments',['source_lab_id'=>$this->source->id,'collector_id'=>$this->collector->id])->assertNotFound();
        $staff=$this->user('staff');$staff->update(['creator_id'=>$this->lab->id]);$this->actingAs($staff);$this->getJson('/api/sample-logistics/accounts')->assertForbidden();
    }
    public function test_pending_submission_has_no_financial_patient_or_result_side_effects_and_approval_is_once():void
    {
        $payload=$this->payload();$id=$this->submit($payload);$this->assertDatabaseCount('patients',0);$this->assertDatabaseCount('invoices',0);$this->assertDatabaseCount('invoice_paid_details',0);
        $this->postJson('/api/collector-portal/workspace/invoices/create',$payload)->assertCreated()->assertJsonPath('id',$id);$this->assertDatabaseCount('collection_submissions',1);
        $this->getJson('/api/collector-portal/submissions/'.$id)->assertOk()->assertJsonPath('submission.payload.total',11000)->assertJsonPath('items.0.name','CBC');
        $this->assertStringNotContainsString('FAKE RESULT',json_encode(CollectionSubmission::find($id)->payload));
        $this->actingAs($this->lab);$this->getJson('/api/sample-logistics/waiting')->assertOk()->assertJsonPath('total',1);
        $response=$this->postJson('/api/sample-logistics/waiting/'.$id.'/review',['decision'=>'approved'])->assertOk();$invoice=$response->json('invoice_id');
        $this->postJson('/api/sample-logistics/waiting/'.$id.'/review',['decision'=>'approved'])->assertOk()->assertJsonPath('invoice_id',$invoice);
        $this->assertDatabaseCount('patients',1);$this->assertDatabaseCount('invoices',1);$this->assertDatabaseHas('invoices',['id'=>$invoice,'total'=>11000,'paid'=>2000,'lab_id_fk'=>$this->lab->id]);
        $this->assertDatabaseHas('invoice_paid_details',['invoice_id_fk'=>$invoice,'amount'=>2000]);
        $rel=InvoiceTestRel::where('invoice_id_fk',$invoice)->firstOrFail();$this->assertFalse((bool)$rel->is_done);$this->assertNotSame('FAKE RESULT',$rel->result);
        $this->assertSame('Baghdad',Patient::first()->address);$this->assertDatabaseHas('collection_tasks',['invoice_id'=>$invoice,'collector_id'=>$this->collector->id]);
    }
    public function test_rejection_and_price_changes_never_create_invoice():void
    {
        $id=$this->submit();$this->actingAs($this->lab);$this->postJson('/api/sample-logistics/waiting/'.$id.'/review',['decision'=>'rejected'])->assertUnprocessable();
        $this->postJson('/api/sample-logistics/waiting/'.$id.'/review',['decision'=>'rejected','note'=>'Incomplete sample'])->assertOk();
        $this->postJson('/api/sample-logistics/waiting/'.$id.'/review',['decision'=>'approved'])->assertStatus(409);$this->assertDatabaseCount('patients',0);
        $id=$this->submit();$this->analysis->update(['for_customer_price'=>15000]);$this->actingAs($this->lab);
        $this->postJson('/api/sample-logistics/waiting/'.$id.'/review',['decision'=>'approved'])->assertStatus(409);$this->assertDatabaseCount('invoices',0);
    }
    public function test_catalog_and_submissions_reject_foreign_ids_and_unassigned_sources():void
    {
        $foreign=LabTest::create(['lab_id_fk'=>$this->otherLab->id,'name'=>'Foreign','price'=>999]);$this->actingAs($this->collector);
        $this->getJson('/api/collector-portal/workspace/tests')->assertOk()->assertJsonCount(1);
        $this->postJson('/api/collector-portal/workspace/invoices/create',$this->payload(['tests'=>[['test_id_fk'=>$foreign->id]]]))->assertUnprocessable();
        $this->postJson('/api/collector-portal/workspace/invoices/create',$this->payload(['source_lab_id'=>$this->otherLab->id]))->assertNotFound();
        $id=$this->submit();$this->actingAs($this->otherLab);$this->getJson('/api/sample-logistics/waiting/'.$id)->assertNotFound();
        $this->postJson('/api/sample-logistics/waiting/'.$id.'/review',['decision'=>'approved'])->assertNotFound();
    }
    public function test_tasks_notify_after_commit_do_not_expose_results_and_require_ordered_transitions():void
    {
        $i=$this->invoice();$task=CollectionTask::where('invoice_id',$i->id)->firstOrFail();$this->assertSame($this->collector->id,(int)$task->collector_id);
        $this->assertSame(1,CollectorNotification::where('task_id',$task->id)->count());
        $this->actingAs($this->collector);$response=$this->getJson('/api/collector-portal/tasks')->assertOk()->assertJsonPath('data.0.details.items.0.name','CBC');
        $this->assertStringNotContainsString('SECRET MEDICAL RESULT',$response->getContent());$this->assertStringNotContainsString('12000',$response->getContent());
        $this->postJson('/api/collector-portal/tasks/'.$task->id.'/status',['status'=>'delivered','version'=>1])->assertUnprocessable();
        $this->postJson('/api/collector-portal/tasks/'.$task->id.'/status',['status'=>'collected','version'=>1,'sample_count'=>3])->assertOk()->assertJsonPath('status','collected');
        $this->postJson('/api/collector-portal/tasks/'.$task->id.'/status',['status'=>'collected','version'=>1,'sample_count'=>3])->assertOk();
        $this->postJson('/api/collector-portal/tasks/'.$task->id.'/status',['status'=>'delivered','version'=>1])->assertStatus(409);
        $this->postJson('/api/collector-portal/tasks/'.$task->id.'/status',['status'=>'delivered','version'=>2])->assertOk();
        $day=today('Asia/Baghdad')->toDateString();$this->getJson('/api/collector-portal/statistics?from='.$day.'&to='.$day)->assertOk()->assertJsonPath('data.0.collected_samples',3)->assertJsonPath('data.0.collected_requests',1)->assertJsonPath('data.0.delivered_samples',3);
        $this->assertDatabaseCount('collection_tasks',1);
        $this->actingAs($this->lab);$this->postJson('/api/sample-logistics/tasks/'.$task->id.'/assign',['collector_id'=>$this->collector->id,'version'=>3])->assertStatus(409);
        $i->delete();$this->assertNotNull($task->fresh()->cancelled_at);$this->assertEquals(3,$task->fresh()->sample_count);
    }
    public function test_rollback_creates_no_task_and_unassigned_queue_can_be_assigned():void
    {
        DB::beginTransaction();$i=$this->invoice();DB::rollBack();$this->assertDatabaseCount('collection_tasks',0);$this->assertDatabaseCount('collector_notifications',0);
        DB::table('collector_lab_assignments')->delete();$i=$this->invoice();$task=CollectionTask::where('invoice_id',$i->id)->firstOrFail();$this->assertNull($task->collector_id);
        $this->putJson('/api/sample-logistics/assignments',['source_lab_id'=>$this->source->id,'collector_id'=>$this->collector->id])->assertOk();$this->assertEquals($this->collector->id,$task->fresh()->collector_id);
    }
    public function test_full_bundle_snapshot_is_safe_and_home_submission_can_be_collected():void
    {
        $group=TestGroup::create(['lab_id_fk'=>$this->lab->id,'group_name'=>'Blood group','original_price'=>9000]);$group->tests()->attach($this->analysis->id,['order'=>1]);
        $package=Package::create(['lab_id_fk'=>$this->lab->id,'name'=>'Checkup','price'=>18000]);$package->testGroups()->attach($group->id,['position'=>1]);
        $id=$this->submit($this->payload(['source_lab_id'=>null,'tests'=>[],'packages'=>[['package_id_fk'=>$package->id,'package_tests'=>[['id'=>999,'result'=>'BAD']]]]]));
        $this->actingAs($this->lab);$invoice=$this->postJson('/api/sample-logistics/waiting/'.$id.'/review',['decision'=>'approved'])->assertOk()->json('invoice_id');
        $task=CollectionTask::where('invoice_id',$invoice)->firstOrFail();$this->assertNull($task->source_lab_id);
        $this->actingAs($this->collector);$r=$this->getJson('/api/collector-portal/tasks')->assertOk()->assertJsonPath('data.0.details.items.0.children.0.name','CBC');$this->assertStringNotContainsString('BAD',$r->getContent());
    }
    public function test_failed_approval_rolls_back_patient_invoice_and_review_decision():void
    {
        $id=$this->submit();$this->actingAs($this->lab);
        Invoice::creating(function(){throw new \RuntimeException('Simulated invoice failure');});
        $this->postJson('/api/sample-logistics/waiting/'.$id.'/review',['decision'=>'approved'])->assertStatus(500);
        $this->assertDatabaseCount('patients',0);$this->assertDatabaseCount('invoices',0);$this->assertDatabaseCount('invoice_paid_details',0);
        $this->assertSame('pending',CollectionSubmission::findOrFail($id)->status);
    }
    public function test_reconciliation_recovers_missing_tasks_only_since_collector_setup():void
    {
        $i=$this->invoice();CollectionTask::query()->delete();
        $this->assertSame(1,app(CollectorTasks::class)->reconcile());
        $this->assertSame(0,app(CollectorTasks::class)->reconcile());
        $this->assertDatabaseCount('collection_tasks',1);
    }
    public function test_direct_collectors_cannot_read_or_transition_another_collectors_tasks():void
    {
        $i=$this->invoice();$task=CollectionTask::firstOrFail();
        $new=$this->postJson('/api/sample-logistics/accounts',['name'=>'Second','email'=>'second@test.invalid','password'=>'safe-password-123','status'=>true])->assertCreated()->json('id');
        $this->actingAs(User::findOrFail($new));$this->getJson('/api/collector-portal/tasks')->assertOk()->assertJsonCount(0,'data');
        $this->postJson('/api/collector-portal/tasks/'.$task->id.'/status',['status'=>'collected','version'=>1,'sample_count'=>2])->assertNotFound();
        $this->getJson('/api/collector-portal/notifications')->assertOk()->assertJsonCount(0,'data');
    }
    public function test_push_endpoint_is_validated_private_and_disabled_account_revokes_tokens():void
    {
        $this->mock(PortalPushTransport::class,function($m){$m->shouldReceive('keys')->andReturn(['publicKey'=>'test']);$m->shouldReceive('send')->once()->andReturn('accepted');});
        $this->actingAs($this->collector);$encode=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');
        $sub=['endpoint'=>'https://fcm.googleapis.com/fcm/send/collector-test','keys'=>['p256dh'=>$encode("\x04".str_repeat('a',64)),'auth'=>$encode(str_repeat('b',16))]];
        $this->postJson('/api/collector-portal/notifications/push/subscribe',['subscription'=>array_replace($sub,['endpoint'=>'http://127.0.0.1/private'])])->assertUnprocessable();
        $this->postJson('/api/collector-portal/notifications/push/subscribe',['subscription'=>$sub])->assertOk();
        $this->actingAs($this->lab);$this->invoice();$this->assertSame(1,app(CollectorNotifications::class)->deliver());$this->assertDatabaseHas('collector_push_deliveries',['status'=>'accepted']);
        $token=$this->collector->createToken('test')->plainTextToken;
        $this->putJson('/api/sample-logistics/accounts/'.$this->collector->id,['name'=>'Collector','email'=>$this->collector->email,'status'=>false])->assertOk();
        $this->assertDatabaseCount('collector_push_subscriptions',0);$this->assertDatabaseCount('personal_access_tokens',0);
        $this->actingAs($this->collector->fresh());$this->getJson('/api/collector-portal/context')->assertUnauthorized();
    }
}
