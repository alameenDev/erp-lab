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
}
