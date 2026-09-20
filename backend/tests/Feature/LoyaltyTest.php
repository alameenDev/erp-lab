<?php
namespace Tests\Feature;

use App\Models\{User,Patient,LabSetting,InvoicePaidDetail};
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use DatabaseMigrations;
    private function fixture(): array
    {
        $lab=User::create(['name'=>'Lab','email'=>'loyal@example.test','password'=>'test-password-123','role_id'=>2]);
        $patient=Patient::create(['user_id'=>$lab->id,'creator_id'=>$lab->id,'code'=>'P1']);
        return [$lab,$patient,app(LoyaltyService::class)];
    }
    public function test_settings_are_available_and_rewards_can_be_removed(): void
    {
        [$lab]=$this->fixture();
        $this->actingAs($lab)->getJson('/api/lab-settings')->assertOk()->assertJsonPath('loyalty_config.welcome_bonus',50);
        $this->postJson('/api/lab-settings',['loyalty_config'=>['points_per_currency'=>0.005,'redemption_catalog'=>[]]])->assertOk();
        $this->getJson('/api/lab-settings')->assertJsonPath('loyalty_config.points_per_currency',0.005)->assertJsonCount(0,'loyalty_config.redemption_catalog');
    }
    public function test_payment_reference_cannot_award_twice(): void
    {
        [$lab,$patient,$service]=$this->fixture();
        $reference=new InvoicePaidDetail(); $reference->id=123;
        $service->awardForPayment($patient,$lab,10000,$reference);
        $service->awardForPayment($patient,$lab,10000,$reference);
        $this->assertSame(100,$service->balance($patient));
        $this->assertDatabaseCount('loyalty_transactions',1);
    }
    public function test_disabled_program_does_not_mark_welcome_as_awarded(): void
    {
        [$lab,$patient,$service]=$this->fixture();
        LabSetting::create(['lab_id_fk'=>$lab->id,'loyalty_config'=>['enabled'=>false]]);
        $service->awardWelcomeBonusIfNeeded($patient,$lab);
        $this->assertNull($patient->fresh()->loyalty_joined_at);
        $this->assertSame(0,$service->balance($patient));
        $this->expectException(\RuntimeException::class);
        $service->redeem($patient,$lab,'cbc_free');
    }
    public function test_staff_payments_use_owner_configuration(): void
    {
        [$lab,$patient,$service]=$this->fixture();
        $staff=User::create(['name'=>'Staff','email'=>'loyalstaff@example.test','password'=>'test-password-123','role_id'=>7,'creator_id'=>$lab->id]);
        LabSetting::create(['lab_id_fk'=>$lab->id,'loyalty_config'=>['points_per_currency'=>0.02]]);
        $service->awardForPayment($patient,$staff,1000);
        $this->assertSame(20,$service->balance($patient));
        $this->assertDatabaseHas('loyalty_transactions',['lab_id_fk'=>$lab->id,'points'=>20]);
    }

    private function receptionFixture(): array
    {
        [$lab,$patient,$service]=$this->fixture();
        $lab->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('invoices create','api'));
        LabSetting::create(['lab_id_fk'=>$lab->id,'loyalty_config'=>['redemption_catalog'=>[['key'=>'discount5','label_ar'=>'خصم خمسة آلاف','points'=>100,'discount_amount'=>5000]]]]);
        $service->awardPoints($patient,$lab,'manual',150);
        $this->actingAs($lab);
        return [$lab,$patient,$service];
    }

    public function test_reception_can_read_patient_balance_and_discount_rewards(): void
    {
        [, $patient]=$this->receptionFixture();
        $this->getJson('/api/patients/'.$patient->id.'/loyalty')->assertOk()->assertJsonPath('balance',150)->assertJsonPath('rewards.0.discount_amount',5000);
    }

    public function test_invoice_redemption_reduces_total_and_records_points(): void
    {
        [$lab,$patient,$service]=$this->receptionFixture();
        $this->postJson('/api/invoices/create',['patient_id_fk'=>$patient->id,'sub_total'=>20000,'total'=>15000,'total_before_loyalty'=>20000,'loyalty_reward_key'=>'discount5','paid'=>0])->assertSuccessful();
        $this->assertSame(50,$service->balance($patient));
        $this->assertDatabaseHas('invoices',['patient_id_fk'=>$patient->id,'total'=>15000,'loyalty_discount'=>5000,'loyalty_points_spent'=>100]);
        $this->assertDatabaseHas('loyalty_transactions',['patient_id_fk'=>$patient->id,'type'=>'redemption','reference_type'=>\App\Models\Invoice::class,'points'=>-100]);
    }

    public function test_invalid_invoice_discount_does_not_spend_points(): void
    {
        [, $patient,$service]=$this->receptionFixture();
        $this->postJson('/api/invoices/create',['patient_id_fk'=>$patient->id,'total'=>0,'total_before_loyalty'=>1000,'loyalty_reward_key'=>'discount5'])->assertUnprocessable();
        $this->assertSame(150,$service->balance($patient));
        $this->assertDatabaseCount('invoices',0);
    }

    public function test_portal_shows_only_own_invoices_and_pending_work_without_draft_results(): void
    {
        [$lab,$patient]=$this->fixture();
        LabSetting::create(['lab_id_fk'=>$lab->id,'loyalty_config'=>['enabled'=>false]]);
        $invoice=\App\Models\Invoice::create(['patient_id_fk'=>$patient->id,'lab_id_fk'=>$lab->id,'total'=>20000,'paid'=>5000,'is_done'=>false]);
        \App\Models\InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id,'is_done'=>false,'is_sample_received'=>true,'price'=>20000,'result'=>'PRIVATE_DRAFT']);
        $other=Patient::create(['user_id'=>$lab->id,'creator_id'=>$lab->id,'code'=>'P2']);
        \App\Models\Invoice::create(['patient_id_fk'=>$other->id,'lab_id_fk'=>$lab->id,'total'=>90000]);
        $access=\App\Models\PortalAccessToken::create(['patient_id_fk'=>$patient->id,'token'=>\App\Models\PortalAccessToken::generatePlainToken(),'expires_at'=>now()->addDay()]);
        $response=$this->getJson('/api/portal/'.$access->token)->assertOk()->assertJsonCount(1,'reports')
            ->assertJsonPath('reports.0.id',$invoice->id)->assertJsonPath('reports.0.due',15000)
            ->assertJsonPath('reports.0.view_url',null)->assertJsonPath('reports.0.tests.0.status','pending')
            ->assertJsonPath('reports.0.tests.0.sample_received',true);
        $this->assertStringNotContainsString('PRIVATE_DRAFT',$response->getContent());
        $invoice->update(['is_done'=>true]);
        $this->assertStringEndsWith('/result/'.$invoice->id,$this->getJson('/api/portal/'.$access->token)->assertOk()->json('reports.0.view_url'));
    }

    public function test_portal_hides_invoice_details_until_otp_and_rejects_expired_link(): void
    {
        [$lab,$patient]=$this->fixture();
        LabSetting::create(['lab_id_fk'=>$lab->id,'loyalty_config'=>['require_otp'=>true]]);
        $access=\App\Models\PortalAccessToken::create(['patient_id_fk'=>$patient->id,'token'=>\App\Models\PortalAccessToken::generatePlainToken(),'expires_at'=>now()->addDay()]);
        $this->getJson('/api/portal/'.$access->token)->assertOk()->assertJsonPath('requires_otp',true)->assertJsonMissingPath('reports');
        $access->update(['expires_at'=>now()->subMinute()]);
        $this->getJson('/api/portal/'.$access->token)->assertNotFound();
    }

    public function test_staff_can_share_own_lab_patient_portal_but_not_other_labs(): void
    {
        [$lab,$patient]=$this->fixture();
        $staff=User::create(['name'=>'Reception','email'=>'portalstaff@example.test','password'=>'test-password-123','role_id'=>7,'creator_id'=>$lab->id]);
        $patient->update(['creator_id'=>$staff->id]);
        $this->actingAs($staff)->postJson('/api/portal/generate',['patient_id'=>$patient->id])->assertOk()->assertJsonStructure(['url','loyalty_points']);
        $other=User::create(['name'=>'Other lab','email'=>'portallab@example.test','password'=>'test-password-123','role_id'=>2]);
        $this->actingAs($other)->postJson('/api/portal/generate',['patient_id'=>$patient->id])->assertForbidden();
    }
}
