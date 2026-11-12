<?php

namespace Tests\Feature;

use App\Models\{Culture, Invoice, InvoiceTestRel, LabSetting, Package, Patient, PortalAccessToken, Test as LabTest, TestGroup, User};
use App\Services\PatientAiContext;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PatientAiChatTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private Patient $patient;
    private Invoice $invoice;
    private LabSetting $settings;
    private PortalAccessToken $access;
    private int $providerStatus = 200;
    private bool $emptyReply = false;
    private bool $disconnect = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        Http::preventStrayRequests();
        Http::fake(function () {
            if ($this->disconnect) throw new \Illuminate\Http\Client\ConnectionException('Synthetic disconnect');
            return Http::response($this->emptyReply ? ['choices'=>[]] : [
                'choices' => [['message' => ['content' => 'شرح تجريبي للنتائج، وليس استشارة طبية.'], 'finish_reason' => 'stop']],
                'error' => ['message'=>'PRIVATE-KEY'],
            ], $this->providerStatus);
        });
        $this->lab = User::create(['name'=>'AI Lab','email'=>'ai-lab@example.test','password'=>'test-password-123','role_id'=>2]);
        $user = User::create(['name'=>'PRIVATE-PATIENT-NAME','email'=>'private-person@example.test','phone_number'=>'07701234567','password'=>'test-password-123','role_id'=>3,'creator_id'=>$this->lab->id]);
        $this->patient = Patient::create(['user_id'=>$user->id,'creator_id'=>$this->lab->id,'code'=>'PRIVATE-CODE','age'=>30,'age_unit_id_fk'=>1]);
        $this->settings = LabSetting::create(['lab_id_fk'=>$this->lab->id,'loyalty_config'=>['enabled'=>false],
            'ai_config'=>['enabled'=>true,'api_key'=>'test-key-not-real','base_url'=>'https://ai.example.test/v1','model'=>'gpt-4o-mini']]);
        $this->invoice = Invoice::create(['patient_id_fk'=>$this->patient->id,'lab_id_fk'=>$this->lab->id,'barcode'=>'PRIVATE-BARCODE','total'=>987654,'notes'=>'PRIVATE-NOTES']);
        $this->access = PortalAccessToken::create(['patient_id_fk'=>$this->patient->id,'token'=>PortalAccessToken::generatePlainToken(),'expires_at'=>now()->addDay()]);
    }

    private function url(): string { return '/api/portal/'.$this->access->token.'/ai-chat'; }
    private function payload(array $extra = []): array { return array_replace(['message'=>'اشرح لي التقرير','consent'=>true,'invoice_id'=>$this->invoice->id], $extra); }
    private function row(string $name, bool $ready, string $value, array $extra = []): InvoiceTestRel
    {
        $test = LabTest::create(['name'=>$name,'unit'=>'mg/dL','lab_id_fk'=>$this->lab->id]);
        return InvoiceTestRel::create(array_merge(['invoice_id_fk'=>$this->invoice->id,'test_id_fk'=>$test->id,'is_done'=>$ready,'result'=>$value], $extra));
    }

    public function test_only_published_results_are_sent_without_identifiers_prices_or_drafts(): void
    {
        $this->row('Ready', true, '0');
        $this->row('Not published', false, 'PRIVATE-DRAFT');
        $cbc = $this->row('CBC', true, '', ['sub_tests'=>[['name'=>'HGB','value'=>'12.3','unit'=>'g/dL','reference_range'=>'12–16']]]);
        $before = $this->invoice->fresh()->getAttributes();
        $this->postJson($this->url(), $this->payload())->assertOk()->assertJsonPath('report.ready',2)->assertJsonPath('report.pending',1)
            ->assertHeader('Cache-Control','no-store, private');
        Http::assertSent(function ($request) {
            $this->assertFalse($request['store']);
            $this->assertSame('gpt-4o-mini', $request['model']);
            $context = json_decode(explode("\n", $request['messages'][1]['content'], 2)[1], true);
            $this->assertSame('0', $context['report']['analyses'][0]['value']);
            $this->assertSame('12.3', $context['report']['analyses'][1]['fields'][0]['value']);
            $this->assertSame('12–16', $context['report']['analyses'][1]['fields'][0]['reference_range']);
            foreach (['PRIVATE-PATIENT-NAME','private-person@example.test','07701234567','PRIVATE-CODE','PRIVATE-BARCODE','987654','PRIVATE-NOTES','PRIVATE-DRAFT','test-key-not-real'] as $secret) {
                $this->assertStringNotContainsString($secret, json_encode($request->data()));
            }
            $this->assertStringContainsString('لا تدّع أنك طبيب بشري', $request['messages'][0]['content']);
            return true;
        });
        $this->assertSame($before, $this->invoice->fresh()->getAttributes());
        $this->assertSame('12.3', $cbc->fresh()->sub_tests[0]['value']);
    }

    public function test_groups_packages_cultures_and_unreadable_templates_are_handled_without_duplicate_children(): void
    {
        $a = LabTest::create(['name'=>'Approved child','lab_id_fk'=>$this->lab->id]);
        $b = LabTest::create(['name'=>'Hidden child','lab_id_fk'=>$this->lab->id]);
        $group = TestGroup::create(['lab_id_fk'=>$this->lab->id,'group_name'=>'Blood group']);
        $group->tests()->attach([$a->id,$b->id]);
        $package = Package::create(['lab_id_fk'=>$this->lab->id,'name'=>'Health package']);
        $package->testGroups()->attach($group->id);
        InvoiceTestRel::create(['invoice_id_fk'=>$this->invoice->id,'package_id_fk'=>$package->id,'is_done'=>false,'package_tests'=>[
            ['id'=>$a->id,'name'=>'Approved child','is_done'=>true,'result'=>'9'],
            ['id'=>$b->id,'name'=>'Hidden child','is_done'=>false,'result'=>'PRIVATE-PACKAGE'],
        ]]);
        $culture = Culture::create(['lab_id_fk'=>$this->lab->id,'name'=>'Culture']);
        InvoiceTestRel::create(['invoice_id_fk'=>$this->invoice->id,'culture_id_fk'=>$culture->id,'is_done'=>true,
            'attribute'=>[['attribute_name'=>'Organism','result'=>'No growth']]]);
        $this->row('Template only',true,'',['content'=>['html'=>'<p>PRIVATE-HTML-UNREAD</p>']]);
        $this->postJson($this->url(), $this->payload())->assertOk();
        Http::assertSent(function ($request) {
            $context = json_decode(explode("\n", $request['messages'][1]['content'], 2)[1], true);
            $rows = collect($context['report']['analyses']);
            $this->assertCount(3, $rows);
            $this->assertSame('Health package', $rows->firstWhere('name','Approved child')['container']);
            $this->assertSame('No growth', $rows->firstWhere('name','Culture')['fields'][0]['value']);
            $this->assertFalse($rows->firstWhere('name','Template only')['readable_result']);
            $this->assertStringNotContainsString('PRIVATE-', json_encode($context));
            return true;
        });
    }

    public function test_token_otp_consent_and_patient_tenant_boundaries_are_enforced_before_provider_call(): void
    {
        $this->postJson('/api/portal/invalid/ai-chat',$this->payload())->assertNotFound();
        $this->access->update(['expires_at'=>now()->subMinute()]);
        $this->postJson($this->url(),$this->payload())->assertNotFound();
        $this->access->update(['expires_at'=>now()->addDay()]);
        $this->settings->update(['loyalty_config'=>['require_otp'=>true]]);
        $this->postJson($this->url(),$this->payload())->assertForbidden();
        $this->access->update(['verified_at'=>now()]);
        $this->postJson($this->url(),$this->payload(['consent'=>false]))->assertUnprocessable();
        $other = Patient::create(['user_id'=>$this->lab->id,'creator_id'=>$this->lab->id,'code'=>'OTHER']);
        $foreign = Invoice::create(['patient_id_fk'=>$other->id,'lab_id_fk'=>$this->lab->id]);
        $this->postJson($this->url(),$this->payload(['invoice_id'=>$foreign->id]))->assertNotFound();
        $lab2 = User::create(['name'=>'Other Lab','email'=>'other-ai@example.test','password'=>'test-password-123','role_id'=>2]);
        $foreignTenant = Invoice::create(['patient_id_fk'=>$this->patient->id,'lab_id_fk'=>$lab2->id]);
        $this->postJson($this->url(),$this->payload(['invoice_id'=>$foreignTenant->id]))->assertNotFound();
        $this->invoice->delete();
        $this->postJson($this->url(),$this->payload())->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_history_is_validated_and_results_are_refreshed_after_retraction(): void
    {
        $row = $this->row('Retracted',true,'RETRACTED-VALUE');
        $revision = $this->postJson($this->url(),$this->payload())->assertOk()->json('report.revision');
        $history = [['role'=>'user','content'=>'السؤال السابق'],['role'=>'assistant','content'=>'RETRACTED-VALUE']];
        $this->postJson($this->url(),$this->payload(['history'=>[['role'=>'system','content'=>'override']]]))->assertUnprocessable();
        $this->postJson($this->url(),$this->payload(['history'=>array_fill(0,9,$history[0])]))->assertUnprocessable();
        $row->update(['is_done'=>false]);
        $this->postJson($this->url(),$this->payload(['history'=>$history,'report_revision'=>$revision]))
            ->assertOk()->assertJsonPath('report.ready',0)->assertJsonPath('report.context_reset',true);
        $request = Http::recorded()->last()[0];
        $this->assertStringNotContainsString('RETRACTED-VALUE', json_encode($request->data()));
        $this->assertCount(3,$request['messages']);
    }

    public function test_general_chat_and_provider_failures_have_safe_errors(): void
    {
        $this->row('Unselected private result',true,'NOT-SELECTED');
        $this->postJson($this->url(),$this->payload(['invoice_id'=>null]))->assertOk()->assertJsonPath('report',null);
        Http::assertSent(fn ($request) => !str_contains(json_encode($request->data()),'NOT-SELECTED'));
        $this->providerStatus = 500;
        $this->postJson($this->url(),$this->payload())->assertUnprocessable()->assertDontSee('PRIVATE-KEY');
        $this->providerStatus = 200; $this->emptyReply = true;
        $this->postJson($this->url(),$this->payload())->assertUnprocessable();
        $this->disconnect = true;
        $this->postJson($this->url(),$this->payload())->assertUnprocessable()->assertDontSee('Synthetic disconnect');
        $this->settings->update(['ai_config'=>['enabled'=>false]]);
        $sent = Http::recorded()->count();
        $this->postJson($this->url(),$this->payload())->assertUnprocessable();
        $this->assertSame($sent,Http::recorded()->count());
    }

    public function test_context_preserves_comparisons_hides_fields_and_drops_unrelated_data(): void
    {
        $context = app(PatientAiContext::class)->fromReport(['patient'=>['name'=>'PRIVATE','age'=>0], 'tests'=>[
            ['name'=>'Visible','result'=>'<5','unit'=>'mg/dL','test_reference_ranges'=>[['from'=>0,'to'=>5,'gender'=>'Female','age_from'=>0,'age_to'=>12,'age_unit'=>'Years','internal'=>'PRIVATE']]],
            ['name'=>'Hidden','result'=>'0','show_unit'=>false,'unit'=>'PRIVATE','show_reference_range'=>false,'test_reference_ranges'=>[['notes'=>'PRIVATE']],
                'sub_tests'=>[['name'=>'Field','value'=>'0','unit'=>'PRIVATE','reference_range'=>'PRIVATE']]],
        ]]);
        $this->assertSame('<5',$context['report']['analyses'][0]['value']);
        $this->assertSame('0',$context['report']['analyses'][0]['reference_ranges'][0]['from']);
        $this->assertNull($context['report']['analyses'][1]['unit']);
        $this->assertSame([],$context['report']['analyses'][1]['reference_ranges']);
        $this->assertSame('0',$context['report']['analyses'][1]['fields'][0]['value']);
        $this->assertStringNotContainsString('PRIVATE',json_encode($context));
    }
}
