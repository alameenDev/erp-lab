<?php

namespace Tests\Feature;

use App\Models\{LabSetting, Patient, PatientAiConversation, PatientAiTurn, PortalAccessToken, User};
use App\Services\PatientAiJournal;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientAiConversationTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private Patient $patient;
    private PortalAccessToken $access;
    private int $providerStatus = 200;
    private const LOGS = '/api/lab-settings/ibn-sina/conversations';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => 'رد اصطناعي محفوظ'], 'finish_reason' => 'stop']]], $this->providerStatus));
        $this->lab = $this->user(2);
        [$this->patient, $this->access] = $this->patient($this->lab);
        LabSetting::create(['lab_id_fk' => $this->lab->id, 'loyalty_config' => ['require_otp' => false],
            'ai_config' => ['enabled' => true, 'api_key' => 'PRIVATE-TEST-KEY', 'base_url' => 'https://ai.example.test/v1', 'model' => 'gpt-4o-mini']]);
    }

    private function user(int $role, ?int $creator = null): User
    {
        return User::create(['name' => 'حساب تجريبي', 'email' => Str::uuid().'@example.test', 'password' => 'test-password-123', 'role_id' => $role, 'creator_id' => $creator]);
    }
    private function patient(User $lab): array
    {
        $patient = Patient::create(['user_id' => $this->user(3, $lab->id)->id, 'creator_id' => $lab->id, 'code' => (string) Str::uuid()]);
        return [$patient, PortalAccessToken::create(['patient_id_fk' => $patient->id, 'token' => PortalAccessToken::generatePlainToken(), 'expires_at' => now()->addDay()])];
    }
    private function payload(array $extra = []): array
    {
        return array_replace(['message' => 'رسالة مريض اصطناعية <img src=x onerror=alert(1)>', 'consent' => true,
            'consent_version' => PatientAiJournal::CONSENT_VERSION, 'conversation_id' => (string) Str::uuid(), 'request_id' => (string) Str::uuid()], $extra);
    }
    private function send(array $payload, ?PortalAccessToken $access = null)
    {
        return $this->postJson('/api/portal/'.($access ?? $this->access)->token.'/ai-chat', $payload);
    }

    public function test_new_disclosure_is_required_and_invalid_access_never_creates_logs(): void
    {
        $payload = $this->payload(); unset($payload['consent_version']);
        $this->send($payload)->assertUnprocessable();
        $this->send($this->payload(['consent' => false]))->assertUnprocessable();
        $this->access->update(['expires_at' => now()->subMinute()]);
        $this->send($this->payload())->assertNotFound();
        $this->access->update(['expires_at' => now()->addDay()]);
        $this->lab->labSetting->update(['loyalty_config' => ['require_otp' => true]]);
        $this->send($this->payload())->assertForbidden();
        $this->lab->labSetting->update(['loyalty_config' => ['require_otp' => false], 'ai_config' => ['enabled' => false]]);
        $this->send($this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('patient_ai_conversations', 0);
        $this->assertDatabaseCount('patient_ai_turns', 0);
        Http::assertNothingSent();
    }

    public function test_encrypted_messages_are_visible_only_to_the_owning_lab_and_authorized_staff(): void
    {
        $payload = $this->payload(['history' => [['role' => 'user', 'content' => 'FORGED-HISTORY']]]);
        $this->send($payload)->assertOk();
        $raw = DB::table('patient_ai_turns')->first();
        $this->assertStringNotContainsString('رسالة', $raw->message);
        $this->assertStringNotContainsString('رد اصطناعي', $raw->answer);
        $this->getJson(self::LOGS)->assertUnauthorized();
        $this->actingAs($this->lab)->getJson(self::LOGS)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.message_count', 1);
        $this->getJson(self::LOGS.'/'.$payload['conversation_id'])->assertOk()->assertJsonPath('data.0.message', $payload['message'])
            ->assertJsonPath('data.0.reply', 'رد اصطناعي محفوظ')->assertDontSee('FORGED-HISTORY')->assertDontSee($this->access->token)->assertDontSee('PRIVATE-TEST-KEY');
        $this->assertDatabaseCount('patient_ai_turns', 1);
        $audit = DB::table('activity_log')->where('log_name', 'patient-ai-access')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString($payload['message'], $audit->properties);
        $other = $this->user(2);
        $this->actingAs($other)->getJson(self::LOGS.'?lab_id='.$this->lab->id)->assertOk()->assertJsonPath('total', 0);
        $this->getJson(self::LOGS.'/'.$payload['conversation_id'])->assertNotFound();
        $staff = $this->user(7, $this->lab->id);
        $staff->givePermissionTo('lab settings edit');
        $this->actingAs($staff)->getJson(self::LOGS)->assertForbidden();
        $this->getJson(self::LOGS.'/'.$payload['conversation_id'])->assertForbidden();
        $staff->givePermissionTo('patient ai conversations view');
        $this->actingAs($staff->fresh())->getJson(self::LOGS.'/'.$payload['conversation_id'])->assertOk();
        $staff->revokePermissionTo('patient ai conversations view');
        $this->actingAs($staff->fresh())->getJson(self::LOGS)->assertForbidden();
    }

    public function test_retry_is_idempotent_and_failed_provider_requests_remain_visible(): void
    {
        $payload = $this->payload();
        $this->providerStatus = 503;
        $this->send($payload)->assertUnprocessable();
        $this->assertSame('failed', PatientAiTurn::firstOrFail()->status);
        $this->assertSame($payload['message'], PatientAiTurn::firstOrFail()->message);
        $this->providerStatus = 200;
        $this->send($payload)->assertOk();
        $this->send($payload)->assertOk();
        Http::assertSentCount(2);
        $this->assertDatabaseCount('patient_ai_turns', 1);
        $this->assertSame(2, PatientAiTurn::firstOrFail()->attempt);
        $this->send(array_replace($payload, ['message' => 'Changed']))->assertStatus(409);
        $this->send(array_replace($payload, ['request_id' => (string) Str::uuid(), 'message' => 'متابعة المريض']))->assertOk();
        $this->assertDatabaseCount('patient_ai_conversations', 1);
        $this->assertDatabaseCount('patient_ai_turns', 2);
    }

    public function test_conversation_ids_cannot_be_reused_by_another_patient_or_lab_or_changed_context(): void
    {
        $payload = $this->payload(); $this->send($payload)->assertOk();
        [, $otherPatient] = $this->patient($this->lab);
        $this->send($payload, $otherPatient)->assertNotFound();
        $otherLab = $this->user(2);
        LabSetting::create(['lab_id_fk' => $otherLab->id, 'ai_config' => ['enabled' => true]]);
        [, $otherAccess] = $this->patient($otherLab);
        $this->send($payload, $otherAccess)->assertNotFound();
        $this->send(array_replace($payload, ['guided' => true]))->assertStatus(409)->assertJsonPath('context_reset', true);
        $this->assertDatabaseCount('patient_ai_turns', 1);
        Http::assertSentCount(1);
    }

    public function test_inflight_retry_is_blocked_and_a_stale_attempt_cannot_overwrite_the_new_response(): void
    {
        $payload = $this->payload(); $this->send($payload)->assertOk();
        $old = PatientAiTurn::firstOrFail(); $old->update(['status' => 'pending', 'answer' => null]);
        $this->send($payload)->assertStatus(409);
        Http::assertSentCount(1);
        DB::table('patient_ai_turns')->where('id', $old->id)->update(['updated_at' => now()->subMinutes(3)]);
        $this->send($payload)->assertOk();
        $this->assertFalse(app(PatientAiJournal::class)->finish($old, ['reply' => 'Stale response']));
        $this->assertSame('رد اصطناعي محفوظ', $old->fresh()->answer['reply']);
        $this->assertDatabaseCount('patient_ai_turns', 1);
    }

    public function test_patient_search_baghdad_dates_and_pagination_are_scoped_and_bounded(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-11 21:30:00', 'UTC'));
        $payload = $this->payload(); $this->send($payload)->assertOk();
        $conversation = PatientAiConversation::firstOrFail();
        for ($i = 0; $i < 30; $i++) $conversation->turns()->create(['request_id' => (string) Str::uuid(), 'message' => 'سؤال '.$i, 'status' => 'failed']);
        $this->actingAs($this->lab)->getJson(self::LOGS.'?from=2026-11-12&to=2026-11-12&search='.$this->patient->code)->assertOk()->assertJsonPath('total', 1);
        $this->getJson(self::LOGS.'?to=2026-11-11')->assertOk()->assertJsonPath('total', 0);
        $this->getJson(self::LOGS.'?search=NONEXISTENT')->assertOk()->assertJsonPath('total', 0);
        $response = $this->getJson(self::LOGS.'/'.$payload['conversation_id'])->assertOk()->assertJsonCount(30, 'data')->assertJsonPath('last_page', 2);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->getJson(self::LOGS.'/'.$payload['conversation_id'].'?page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::LOGS.'?from=2026-11-13&to=2026-11-12')->assertUnprocessable();
        $this->getJson(self::LOGS.'?page=0')->assertUnprocessable();
        $this->travelBack();
    }
}
