<?php

namespace Tests\Feature;

use App\Models\{Invoice, InvoiceTestRel, LabSetting, Patient, PortalAccessToken, Test as LabTest, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ResultTrendsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_trends_require_patient_verification_and_separate_units_and_unreleased_values(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name'=>'Lab','email'=>'lab@example.test','password'=>'secret-password-123','role_id'=>2]);
        $other = User::create(['name'=>'Other','email'=>'other@example.test','password'=>'secret-password-123','role_id'=>2]);
        $person = User::create(['name'=>'Patient','email'=>'patient@example.test','password'=>'secret-password-123','role_id'=>6]);
        $patient = Patient::create(['user_id'=>$person->id,'creator_id'=>$lab->id,'code'=>'P-1']);
        LabSetting::create(['lab_id_fk'=>$lab->id,'loyalty_config'=>['require_otp'=>true]]);
        $test = LabTest::create(['name'=>'Glucose','unit'=>'mg/dL','lab_id_fk'=>$lab->id]);
        $first = Invoice::create(['lab_id_fk'=>$lab->id,'patient_id_fk'=>$patient->id,'is_done'=>true,'result_date'=>'2026-01-01']);
        InvoiceTestRel::create(['invoice_id_fk'=>$first->id,'test_id_fk'=>$test->id,'result'=>'0','is_done'=>true]);
        $second = Invoice::create(['lab_id_fk'=>$lab->id,'patient_id_fk'=>$patient->id,'is_done'=>true,'result_date'=>'2026-02-01']);
        InvoiceTestRel::create(['invoice_id_fk'=>$second->id,'is_done'=>true,'package_tests'=>[
            ['id'=>$test->id,'name'=>'Glucose','unit'=>'mg/dL','result'=>'١٢٫٥'],
            ['id'=>$test->id,'name'=>'Glucose','unit'=>'mmol/L','result'=>'<5'],
            ['id'=>$test->id,'name'=>'Glucose','unit'=>'mg/dL','result'=>'999','is_done'=>false],
        ]]);
        $pending = Invoice::create(['lab_id_fk'=>$lab->id,'patient_id_fk'=>$patient->id,'is_done'=>false,'result_date'=>'2026-03-01']);
        InvoiceTestRel::create(['invoice_id_fk'=>$pending->id,'test_id_fk'=>$test->id,'result'=>'70','is_done'=>true]);
        $foreign = Invoice::create(['lab_id_fk'=>$other->id,'patient_id_fk'=>$patient->id,'is_done'=>true]);
        InvoiceTestRel::create(['invoice_id_fk'=>$foreign->id,'test_id_fk'=>$test->id,'result'=>'888','is_done'=>true]);
        $access = PortalAccessToken::create(['patient_id_fk'=>$patient->id,'token'=>PortalAccessToken::generatePlainToken(),'expires_at'=>now()->addDay()]);
        $url = '/api/portal/'.$access->token.'/result-trends';
        $this->getJson($url)->assertForbidden();
        $access->update(['verified_at'=>now()]);
        $r = $this->getJson($url)->assertOk()->assertJsonCount(2,'series')->assertJsonCount(2,'series.0.points')
            ->assertJsonPath('series.0.points.0.value','0')->assertJsonPath('series.0.points.1.number',12.5)
            ->assertJsonPath('series.1.points.0.number',null)->assertJsonMissing(['value'=>'888'])->assertJsonMissing(['value'=>'999'])->assertJsonMissing(['value'=>'70']);
        $this->actingAs($lab);
        $this->getJson('/api/invoices/'.$first->id.'/result-trends')->assertForbidden();
        $lab->givePermissionTo('invoices edit');
        $this->getJson('/api/invoices/'.$first->id.'/result-trends')->assertOk()->assertJsonCount(3,'series.0.points');
        $this->getJson('/api/invoices/'.$foreign->id.'/result-trends')->assertForbidden();
        $access->update(['expires_at'=>now()->subMinute()]);
        $this->getJson($url)->assertNotFound();
    }
}
