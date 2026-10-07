<?php

namespace Tests\Feature;

use App\Models\{LabSetting, Package, Patient, PortalAccessToken, Test as LabTest, TestGroup, User};
use App\Services\AiAssistantService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PortalPackageCatalogTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(): array
    {
        $lab=User::create(['name'=>'Catalog Lab','email'=>uniqid().'@example.test','password'=>'test-password-123','role_id'=>2]);
        $patient=Patient::create(['user_id'=>$lab->id,'creator_id'=>$lab->id,'code'=>uniqid('P')]);
        $access=PortalAccessToken::create(['patient_id_fk'=>$patient->id,'token'=>PortalAccessToken::generatePlainToken(),'expires_at'=>now()->addDay()]);
        return [$lab,$patient,$access,'/api/portal/'.$access->token];
    }

    public function test_catalog_contains_only_real_packages_for_the_patient_lab(): void
    {
        [$lab,,$access,$url]=$this->fixture(); [$other]=$this->fixture();
        $test=LabTest::create(['name'=>'Hidden individual analysis','price'=>7654,'lab_id_fk'=>$lab->id]);
        $group=TestGroup::create(['group_name'=>'Hidden group','original_price'=>10000,'for_customer_price'=>8765,'lab_id_fk'=>$lab->id]);
        $package=Package::create(['name'=>'A actual package','price'=>25000,'lab_id_fk'=>$lab->id]);
        $package->tests()->attach($test->id); $package->testGroups()->attach($group->id);
        $free=Package::create(['name'=>'B zero price','price'=>0,'lab_id_fk'=>$lab->id]);
        $unpriced=Package::create(['name'=>'C ask lab','price'=>null,'lab_id_fk'=>$lab->id]);
        Package::create(['name'=>'Foreign private package','price'=>99999,'lab_id_fk'=>$other->id]);
        $deleted=Package::create(['name'=>'Deleted package','price'=>10000,'lab_id_fk'=>$lab->id]);$deleted->delete();
        $this->getJson($url.'/catalog')->assertOk()->assertExactJson(['packages'=>[
            ['id'=>$package->id,'name'=>$package->name,'price'=>25000],
            ['id'=>$free->id,'name'=>$free->name,'price'=>0],
            ['id'=>$unpriced->id,'name'=>$unpriced->name,'price'=>null],
        ]]);
        $setting=LabSetting::create(['lab_id_fk'=>$lab->id,'ai_config'=>['enabled'=>true]]);
        $ai=\Mockery::mock(AiAssistantService::class);
        $ai->shouldReceive('chat')->once()->withArgs(function($settings,$message,$context,$history) use($package,$free,$unpriced) {
            $this->assertSame([], $context['tests']);
            $this->assertSame([$package->id,$free->id,$unpriced->id], array_column($context['packages'],'id'));
            $this->assertStringNotContainsString('Hidden', json_encode($context));
            return true;
        })->andReturn('Synthetic response');
        $this->app->instance(AiAssistantService::class,$ai);
        $this->postJson($url.'/ai-chat',['message'=>'ما الباقات المتوفرة؟'])->assertOk();
        $package->delete();$free->delete();$unpriced->delete();
        $this->getJson($url.'/catalog')->assertOk()->assertExactJson(['packages'=>[]]);
    }

    public function test_catalog_respects_portal_expiry_and_required_verification(): void
    {
        [$lab,,$access,$url]=$this->fixture();
        Package::create(['name'=>'Verified package','price'=>5000,'lab_id_fk'=>$lab->id]);
        LabSetting::create(['lab_id_fk'=>$lab->id,'loyalty_config'=>['require_otp'=>true]]);
        $this->getJson($url.'/catalog')->assertForbidden()->assertDontSee('Verified package');
        $access->update(['verified_at'=>now()]);
        $this->getJson($url.'/catalog')->assertOk()->assertJsonCount(1,'packages');
        $access->update(['expires_at'=>now()->subMinute()]);
        $this->getJson($url.'/catalog')->assertNotFound();
        $this->getJson('/api/portal/invalid-token/catalog')->assertNotFound();
    }
}
