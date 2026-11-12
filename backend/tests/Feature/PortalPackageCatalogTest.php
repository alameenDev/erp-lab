<?php

namespace Tests\Feature;

use App\Models\{Invoice, InvoiceTestRel, LabSetting, Package, Patient, PortalAccessToken, Test as LabTest, TestGroup, User};
use App\Services\AiAssistantService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
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
        $package=Package::create(['name'=>'A actual package','price'=>25000,'lab_id_fk'=>$lab->id,'show_in_patient_portal'=>true]);
        $package->tests()->attach($test->id); $package->testGroups()->attach($group->id);
        $free=Package::create(['name'=>'B zero price','price'=>0,'lab_id_fk'=>$lab->id,'show_in_patient_portal'=>true]);
        $unpriced=Package::create(['name'=>'C ask lab','price'=>null,'lab_id_fk'=>$lab->id,'show_in_patient_portal'=>true]);
        Package::create(['name'=>'Hidden unselected package','price'=>99999,'lab_id_fk'=>$lab->id]);
        Package::create(['name'=>'Hidden unchecked package','price'=>99999,'lab_id_fk'=>$lab->id,'show_in_patient_portal'=>false]);
        Package::create(['name'=>'Foreign private package','price'=>99999,'lab_id_fk'=>$other->id,'show_in_patient_portal'=>true]);
        $deleted=Package::create(['name'=>'Deleted package','price'=>10000,'lab_id_fk'=>$lab->id,'show_in_patient_portal'=>true]);$deleted->delete();
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
        $this->postJson($url.'/ai-chat',['message'=>'ما الباقات المتوفرة؟','consent'=>true])->assertOk();
        $package->delete();$free->delete();$unpriced->delete();
        $this->getJson($url.'/catalog')->assertOk()->assertExactJson(['packages'=>[]]);
    }

    public function test_catalog_respects_portal_expiry_and_required_verification(): void
    {
        [$lab,,$access,$url]=$this->fixture();
        Package::create(['name'=>'Verified package','price'=>5000,'lab_id_fk'=>$lab->id,'show_in_patient_portal'=>true]);
        LabSetting::create(['lab_id_fk'=>$lab->id,'loyalty_config'=>['require_otp'=>true]]);
        $this->getJson($url.'/catalog')->assertForbidden()->assertDontSee('Verified package');
        $access->update(['verified_at'=>now()]);
        $this->getJson($url.'/catalog')->assertOk()->assertJsonCount(1,'packages');
        $access->update(['expires_at'=>now()->subMinute()]);
        $this->getJson($url.'/catalog')->assertNotFound();
        $this->getJson('/api/portal/invalid-token/catalog')->assertNotFound();
    }

    public function test_checkbox_round_trip_only_filters_portal_catalog_and_preserves_existing_patient_results(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        [$lab,$patient,,$url]=$this->fixture();
        foreach (['packages view','packages create','packages edit'] as $permission) {
            $lab->givePermissionTo(Permission::findOrCreate($permission,'api'));
        }
        $this->actingAs($lab);
        $test=LabTest::create(['name'=>'Existing patient analysis','lab_id_fk'=>$lab->id,'result_type_id_fk'=>1]);
        $input=['name'=>'Selectable package','shortcut'=>'PKG','price'=>12000,'is_constant_price'=>true,'tests'=>[['id'=>$test->id]]];
        $id=$this->postJson('/api/packages/create',$input)->assertOk()->assertJsonPath('show_in_patient_portal',false)->json('id');
        $this->getJson($url.'/catalog')->assertOk()->assertJsonCount(0,'packages');
        $invoice=Invoice::create(['patient_id_fk'=>$patient->id,'lab_id_fk'=>$lab->id,'is_done'=>true]);
        InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id,'package_id_fk'=>$id,
            'package_tests'=>[['id'=>$test->id,'test_id_fk'=>$test->id,'name'=>$test->name,'result'=>'5','is_done'=>true]]]);
        $input['id']=$id;
        $before=$this->getJson('/api/invoices/'.$invoice->id)->assertOk()->json('packages');
        $portalBefore=$this->getJson($url)->assertOk()->assertJsonCount(1,'reports')->json('reports');
        foreach ([true,false] as $show) {
            $this->putJson('/api/packages/update',$input+['show_in_patient_portal'=>$show])->assertOk()->assertJsonPath('show_in_patient_portal',$show);
            // Legacy callers omitting this setting must preserve the saved choice.
            $this->putJson('/api/packages/update',$input)->assertOk()->assertJsonPath('show_in_patient_portal',$show);
            $this->getJson('/api/packages')->assertOk()->assertJsonCount(1)->assertJsonPath('0.show_in_patient_portal',$show);
            $this->getJson('/api/packages/show?id='.$id)->assertOk()->assertJsonPath('show_in_patient_portal',$show)
                ->assertJsonPath('tests.0.id',$test->id);
            $this->getJson($url.'/catalog')->assertOk()->assertJsonCount($show ? 1 : 0,'packages');
            $this->assertEquals($before,$this->getJson('/api/invoices/'.$invoice->id)->assertOk()->json('packages'));
            $this->assertEquals($portalBefore,$this->getJson($url)->assertOk()->json('reports'));
            $this->assertSame(12000,(int) Package::findOrFail($id)->price);
        }
        $this->postJson('/api/packages/create',array_diff_key($input,['id'=>true])+['show_in_patient_portal'=>true])
            ->assertOk()->assertJsonPath('show_in_patient_portal',true);
        foreach (['/api/packages/create','/api/packages/update'] as $endpoint) {
            $method=str_ends_with($endpoint,'create') ? 'postJson' : 'putJson';
            $this->$method($endpoint,$input+['show_in_patient_portal'=>'invalid'])->assertUnprocessable()->assertJsonValidationErrors('show_in_patient_portal');
        }
        [$other]=$this->fixture();
        $other->givePermissionTo(Permission::findOrCreate('packages edit','api'));
        $this->actingAs($other)->putJson('/api/packages/update',$input+['show_in_patient_portal'=>true])->assertForbidden();
        $this->assertFalse(Package::findOrFail($id)->show_in_patient_portal);
    }
}
