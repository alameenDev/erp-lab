<?php

namespace Tests\Feature;

use App\Models\{Invoice, LabSetting, Patient, PortalAccessToken, User};
use App\Services\PatientMobileAccess;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, RateLimiter, Schema};
use Tests\TestCase;

class PatientMobileTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['patient_mobile.enabled'=>true, 'patient_mobile.lab_ids'=>[], 'cache.default'=>'array']);
        // CI provides a different database; never migrate these tables on the ERP connection.
        $this->assertNotSame(config('database.connections.mysql.database'), config('database.connections.patient_mobile.database'));
        $migration = require database_path('patient-mobile-migrations/2026_10_07_000001_create_mobile_pairing.php');
        $migration->down(); $migration->up();
    }

    private function fixture(string $phone = '07701234567'): array
    {
        $lab = User::create(['name'=>'Test Laboratory', 'email'=>uniqid().'@example.test','password'=>'test-password-123','role_id'=>2]);
        $user = User::create(['name'=>'Synthetic Patient','email'=>uniqid().'@example.test','password'=>'test-password-123','role_id'=>4,'phone_number'=>$phone]);
        $patient = Patient::create(['user_id'=>$user->id,'creator_id'=>$lab->id,'code'=>uniqid('P')]);
        $access = PortalAccessToken::create(['patient_id_fk'=>$patient->id,'token'=>PortalAccessToken::generatePlainToken(),'expires_at'=>now()->addDays(60)]);
        config(['patient_mobile.lab_ids'=>array_merge(config('patient_mobile.lab_ids'), [$lab->id])]);
        return [$lab,$patient,$access,$user];
    }

    private function issue(PortalAccessToken $access): string
    {
        return $this->postJson('/api/portal/'.$access->token.'/mobile-link')->assertCreated()->json('code');
    }

    private function pair(PortalAccessToken $access, string $phone = '07701234567'): string
    {
        return $this->postJson('/api/patient-mobile/v1/exchange',['phone'=>$phone,'code'=>$this->issue($access)])
            ->assertCreated()->json('token');
    }

    private function invoice(User $lab, Patient $patient, bool $ready = true): Invoice
    {
        $invoice = Invoice::create(['patient_id_fk'=>$patient->id,'lab_id_fk'=>$lab->id,
            'barcode'=>uniqid('B'), 'is_done'=>$ready,'total'=>10000,'paid'=>4000]);
        $test = DB::table('tests')->insertGetId(['name'=>'Glucose','unit'=>'mg/dL','lab_id_fk'=>$lab->id]);
        // Raw fixture inserts avoid unrelated production observers.
        DB::table('invoice_test_rels')->insert(['invoice_id_fk'=>$invoice->id,'test_id_fk'=>$test,'is_done'=>$ready,'result'=>'91']);
        return $invoice;
    }

    public function test_disabled_feature_does_not_need_the_pairing_database(): void
    {
        config(['patient_mobile.enabled'=>false]);
        Schema::connection('patient_mobile')->drop('mobile_pairing_codes');
        $this->postJson('/api/patient-mobile/v1/exchange',['phone'=>'07701234567','code'=>'123456789012'])->assertNotFound();
        $this->getJson('/api/portal/'.str_repeat('a',48).'/mobile-link')->assertNotFound();
        $this->getJson('/api/patient-mobile/v1/me')->assertNotFound();
    }

    public function test_empty_lab_allowlist_denies_pairing(): void
    {
        [,, $access] = $this->fixture();
        config(['patient_mobile.lab_ids'=>[]]);
        $this->postJson('/api/portal/'.$access->token.'/mobile-link')->assertNotFound();
        $this->assertSame(0, DB::connection('patient_mobile')->table('mobile_pairing_codes')->count());
    }

    public function test_phone_normalization_and_single_use_secret(): void
    {
        [,, $access] = $this->fixture();
        $code = $this->issue($access);
        $row = DB::connection('patient_mobile')->table('mobile_pairing_codes')->first();
        $this->assertNotSame($code,$row->code_digest);
        $this->assertNotSame('07701234567',$row->phone_digest);
        $response = $this->postJson('/api/patient-mobile/v1/exchange',['phone'=>'+٩٦٤ ٧٧٠ ١٢٣ ٤٥٦٧','code'=>$code])
            ->assertCreated()->assertHeader('Cache-Control','no-store, private');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response->json('token'));
        $this->postJson('/api/patient-mobile/v1/exchange',['phone'=>'07701234567','code'=>$code])->assertStatus(422);
        $this->assertSame(1, DB::connection('patient_mobile')->table('mobile_patient_sessions')->count());
    }

    public function test_wrong_phone_expired_code_and_superseded_code_are_rejected(): void
    {
        [,, $access] = $this->fixture();
        $old = $this->issue($access);
        $code = $this->issue($access);
        $this->postJson('/api/patient-mobile/v1/exchange',['phone'=>'07801234567','code'=>$code])->assertStatus(422);
        $this->postJson('/api/patient-mobile/v1/exchange',['phone'=>'07701234567','code'=>$old])->assertStatus(422);
        $this->travel(11)->minutes();
        $this->postJson('/api/patient-mobile/v1/exchange',['phone'=>'07701234567','code'=>$code])->assertStatus(422);
    }

    public function test_guessing_is_limited_per_phone(): void
    {
        for ($i=0;$i<6;$i++) $this->postJson('/api/patient-mobile/v1/exchange',
            ['phone'=>'07701234567','code'=>'111111111111'])->assertStatus(422);
        $this->postJson('/api/patient-mobile/v1/exchange',['phone'=>'07701234567','code'=>'111111111111'])->assertStatus(429);
    }

    public function test_missing_phone_does_not_create_a_code(): void
    {
        [,, $access] = $this->fixture('');
        $this->postJson('/api/portal/'.$access->token.'/mobile-link')->assertStatus(422);
        $this->assertSame(0, DB::connection('patient_mobile')->table('mobile_pairing_codes')->count());
    }

    public function test_existing_otp_policy_is_respected_without_modifying_it(): void
    {
        [$lab,, $access] = $this->fixture();
        LabSetting::create(['lab_id_fk'=>$lab->id,'loyalty_config'=>['require_otp'=>true]]);
        $this->postJson('/api/portal/'.$access->token.'/mobile-link')->assertForbidden();
        $access->update(['verified_at'=>now(),'otp_code'=>'unchanged-secret']);
        $before = $access->fresh()->getAttributes();
        $this->issue($access);
        $this->assertSame($before,$access->fresh()->getAttributes());
    }

    public function test_reads_do_not_write_any_erp_table_or_award_welcome_points(): void
    {
        [$lab,$patient,$access] = $this->fixture();
        $invoice = $this->invoice($lab,$patient);
        DB::table('loyalty_transactions')->insert(['patient_id_fk'=>$patient->id,'lab_id_fk'=>$lab->id,'type'=>'purchase','points'=>120,
            'description'=>'Synthetic payment','created_at'=>now(),'updated_at'=>now()]);
        $beforePatient = $patient->fresh()->getAttributes();
        $beforePortal = $access->fresh()->getAttributes();
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if ($query->connectionName === config('database.default') && preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i',$query->sql)) $writes[]=$query->sql;
        });
        $token = $this->pair($access);
        $headers = ['Authorization'=>'Bearer '.$token];
        $this->getJson('/api/patient-mobile/v1/me',$headers)->assertOk()
            ->assertJsonPath('loyalty.balance',120)->assertJsonPath('lab.id',$lab->id)
            ->assertJsonPath('reports.items.0.due',6000);
        $this->getJson('/api/patient-mobile/v1/reports/'.$invoice->id,$headers)->assertOk()
            ->assertJsonPath('sections.0.items.0.value','91');
        $this->getJson('/api/patient-mobile/v1/points',$headers)->assertOk()->assertJsonCount(1,'items');
        $this->assertSame([], $writes);
        $this->assertSame($beforePatient,$patient->fresh()->getAttributes());
        $this->assertSame($beforePortal,$access->fresh()->getAttributes());
        $this->assertDatabaseCount('loyalty_transactions',1);
    }

    public function test_same_phone_never_automatically_links_another_patient_or_lab(): void
    {
        [$lab,$patient,$access] = $this->fixture();
        [$otherLab,$otherPatient] = $this->fixture();
        $mine = $this->invoice($lab,$patient);
        $other = $this->invoice($otherLab,$otherPatient);
        $crossLab = $this->invoice($otherLab,$patient);
        DB::table('loyalty_transactions')->insert(['patient_id_fk'=>$patient->id,'lab_id_fk'=>$otherLab->id,'type'=>'purchase','points'=>999,'created_at'=>now()]);
        $headers = ['Authorization'=>'Bearer '.$this->pair($access)];
        $this->getJson('/api/patient-mobile/v1/me',$headers)->assertOk()->assertJsonCount(1,'reports.items')
            ->assertJsonPath('reports.items.0.id',$mine->id)->assertJsonPath('loyalty.balance',0);
        $this->getJson('/api/patient-mobile/v1/reports/'.$other->id,$headers)->assertNotFound();
        $this->getJson('/api/patient-mobile/v1/reports/'.$crossLab->id,$headers)->assertNotFound();
    }

    public function test_pending_or_partly_approved_reports_do_not_expose_values(): void
    {
        [$lab,$patient,$access] = $this->fixture();
        $invoice = $this->invoice($lab,$patient,false);
        $headers = ['Authorization'=>'Bearer '.$this->pair($access)];
        $this->getJson('/api/patient-mobile/v1/me',$headers)->assertJsonPath('reports.items.0.status','pending')->assertDontSee('"value"');
        $this->getJson('/api/patient-mobile/v1/reports/'.$invoice->id,$headers)->assertStatus(409)->assertDontSee('91');
        $invoice->update(['is_done'=>true]);
        $this->getJson('/api/patient-mobile/v1/reports/'.$invoice->id,$headers)->assertStatus(409);
    }

    public function test_session_expires_with_portal_and_phone_changes_revoke_access(): void
    {
        [,, $access,$user] = $this->fixture();
        $access->update(['expires_at'=>now()->addHour()]);
        $token = $this->pair($access);
        $row = DB::connection('patient_mobile')->table('mobile_patient_sessions')->first();
        $this->assertSame($access->fresh()->expires_at->timestamp, \Carbon\Carbon::parse($row->expires_at)->timestamp);
        $user->update(['phone_number'=>'07801234567']);
        $this->getJson('/api/patient-mobile/v1/me',['Authorization'=>'Bearer '.$token])->assertUnauthorized();
    }

    public function test_portal_revocation_and_logout_only_revoke_mobile_sessions(): void
    {
        [,, $access] = $this->fixture();
        $token = $this->pair($access);
        $before = $access->fresh()->getAttributes();
        $this->deleteJson('/api/patient-mobile/v1/session',[],['Authorization'=>'Bearer '.$token])->assertOk();
        $this->getJson('/api/patient-mobile/v1/me',['Authorization'=>'Bearer '.$token])->assertUnauthorized();
        $token2 = $this->pair($access);
        $this->deleteJson('/api/portal/'.$access->token.'/mobile-link')->assertOk();
        $this->getJson('/api/patient-mobile/v1/me',['Authorization'=>'Bearer '.$token2])->assertUnauthorized();
        $this->assertSame($before,$access->fresh()->getAttributes());
    }

    public function test_deleted_portal_patient_or_user_cannot_keep_access(): void
    {
        [,$patient,$access] = $this->fixture();
        $token = $this->pair($access);
        $patient->delete();
        $this->getJson('/api/patient-mobile/v1/me',['Authorization'=>'Bearer '.$token])->assertUnauthorized();
    }

    public function test_sessions_are_not_staff_sanctum_tokens(): void
    {
        [,, $access] = $this->fixture();
        $headers = ['Authorization'=>'Bearer '.$this->pair($access)];
        $this->getJson('/api/patients',$headers)->assertUnauthorized();
    }
}
