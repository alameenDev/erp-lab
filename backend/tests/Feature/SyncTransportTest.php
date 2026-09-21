<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sync\{SyncLedger, SyncExchange};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Crypt, DB, Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncTransportTest extends TestCase
{
    use DatabaseMigrations;

    private function peer(): object
    {
        config(['lab_sync.enabled' => true]);
        $lab = User::create(['name' => 'Sync lab', 'email' => Str::uuid().'@example.test',
            'password' => 'test-password-only', 'role_id' => 2]);
        $token = Str::random(64);
        $id = DB::table('lab_sync_peers')->insertGetId([
            'lab_id' => $lab->id, 'lab_uuid' => (string) Str::uuid(),
            'local_node_uuid' => (string) Str::uuid(), 'remote_node_uuid' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $token), 'outbound_token' => Crypt::encryptString($token),
            'remote_url' => 'https://sync.example.test', 'enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $peer = DB::table('lab_sync_peers')->find($id);
        $peer->token = $token;
        return $peer;
    }

    private function event(object $peer, bool $local = false, array $overrides = []): array
    {
        return array_replace([
            'protocol' => 1, 'event_uuid' => (string) Str::uuid(), 'lab_uuid' => $peer->lab_uuid,
            'origin_node_uuid' => $local ? $peer->local_node_uuid : $peer->remote_node_uuid,
            'kind' => 'result', 'entity_uuid' => (string) Str::uuid(), 'base_event_uuid' => null,
            'author_uuid' => (string) Str::uuid(), 'authored_at' => now()->toIso8601String(),
            'operation' => 'upsert', 'payload' => ['value' => 'TEST_ONLY_RESULT'],
        ], $overrides);
    }

    private function postEvent(object $peer, array $event)
    {
        return $this->withToken($peer->token)->postJson('/api/lab-sync/v1/events', $event);
    }

    public function test_transport_is_disabled_by_default_and_requires_peer_authentication(): void
    {
        $peer = $this->peer();
        config(['lab_sync.enabled' => false]);
        $this->postEvent($peer, $this->event($peer))->assertNotFound();
        config(['lab_sync.enabled' => true]);
        $this->withToken(str_repeat('x', 64))->postJson('/api/lab-sync/v1/events', $this->event($peer))->assertUnauthorized();
        DB::table('lab_sync_peers')->where('id', $peer->id)->update(['enabled' => false]);
        $this->postEvent($peer, $this->event($peer))->assertUnauthorized();
    }

    public function test_retry_is_idempotent_and_envelope_is_encrypted_without_clinical_writes(): void
    {
        $peer = $this->peer(); $event = $this->event($peer);
        $this->postEvent($peer, $event)->assertOk()->assertJsonPath('state', 'staged')->assertJsonPath('clinical_applied', false);
        $this->postEvent($peer, $event)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('lab_sync_events', 1);
        $this->assertDatabaseCount('invoice_test_rels', 0);
        $this->assertStringNotContainsString('TEST_ONLY_RESULT', DB::table('lab_sync_events')->value('envelope'));
        $event['payload']['value'] = 'CHANGED';
        $this->postEvent($peer, $event)->assertConflict();
    }

    public function test_tenant_and_node_are_bound_to_credential_not_request(): void
    {
        $peer = $this->peer(); $other = $this->peer();
        $this->postEvent($peer, $this->event($other))->assertForbidden();
        $this->postEvent($peer, $this->event($peer, true))->assertForbidden();
        $this->assertDatabaseCount('lab_sync_events', 0);
    }

    public function test_concurrent_result_edits_preserve_both_and_freeze_the_entity(): void
    {
        $peer = $this->peer(); $ledger = app(SyncLedger::class);
        $local = $this->event($peer, true); $ledger->stage($peer->id, $local);
        $remote = $this->event($peer, false, ['entity_uuid' => $local['entity_uuid']]);
        $this->postEvent($peer, $remote)->assertOk()->assertJsonPath('state', 'conflict');
        $this->assertDatabaseHas('lab_sync_heads', ['event_uuid' => $local['event_uuid']]);
        $this->assertDatabaseCount('lab_sync_events', 2);
        $next = $this->event($peer, false, ['entity_uuid' => $local['entity_uuid'], 'base_event_uuid' => $local['event_uuid']]);
        $this->postEvent($peer, $next)->assertOk()->assertJsonPath('state', 'conflict');
    }

    public function test_sequential_events_and_tombstone_are_staged_without_hard_delete(): void
    {
        $peer = $this->peer(); $first = $this->event($peer);
        $this->postEvent($peer, $first)->assertOk();
        $next = $this->event($peer, false, ['entity_uuid' => $first['entity_uuid'],
            'base_event_uuid' => $first['event_uuid'], 'operation' => 'tombstone', 'payload' => []]);
        $this->postEvent($peer, $next)->assertOk()->assertJsonPath('state', 'staged');
        $this->assertDatabaseCount('lab_sync_events', 2);
        $this->assertDatabaseHas('lab_sync_heads', ['event_uuid' => $next['event_uuid']]);
    }

    public function test_missing_parent_is_retained_for_review_and_not_overwritten_on_retry(): void
    {
        $peer = $this->peer();
        $event = $this->event($peer, false, ['base_event_uuid' => (string) Str::uuid()]);
        $this->postEvent($peer, $event)->assertOk()->assertJsonPath('state', 'conflict');
        $this->postEvent($peer, $event)->assertOk()->assertJsonPath('state', 'conflict')->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('lab_sync_heads', 0);
    }

    public function test_invalid_kind_extra_fields_and_oversized_payload_are_rejected(): void
    {
        $peer = $this->peer();
        $this->postEvent($peer, $this->event($peer, false, ['kind' => 'users']))->assertUnprocessable();
        $this->postEvent($peer, $this->event($peer, false, ['table' => 'users']))->assertUnprocessable();
        $this->postEvent($peer, $this->event($peer, false, ['payload' => ['data' => str_repeat('a', 66000)]]))->assertStatus(413);
        $this->assertDatabaseCount('lab_sync_events', 0);
    }

    public function test_outbox_and_domain_transaction_rollback_together(): void
    {
        $peer = $this->peer();
        try {
            DB::transaction(function () use ($peer) {
                app(SyncLedger::class)->stage($peer->id, $this->event($peer, true));
                throw new \RuntimeException('simulated business failure');
            });
        } catch (\RuntimeException $e) {}
        $this->assertDatabaseCount('lab_sync_events', 0);
        $this->assertDatabaseCount('lab_sync_heads', 0);
    }

    public function test_feed_is_tenant_scoped_and_does_not_echo_received_events(): void
    {
        $peer = $this->peer(); $other = $this->peer(); $ledger = app(SyncLedger::class);
        $local = $this->event($peer, true); $ledger->stage($peer->id, $local);
        $ledger->stage($other->id, $this->event($other, true));
        $this->postEvent($peer, $this->event($peer))->assertOk();
        $response = $this->withToken($peer->token)->getJson('/api/lab-sync/v1/changes?after=0')
            ->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.event.event_uuid', $local['event_uuid']);
        $directives = array_map('trim', explode(',', $response->headers->get('Cache-Control')));
        $this->assertContains('no-store', $directives);
        $this->assertContains('private', $directives);
        $cursor = $response->json('cursor');
        $this->getJson('/api/lab-sync/v1/changes?after='.$cursor)->assertOk()->assertJsonCount(0, 'events');
    }

    public function test_status_is_owner_only_and_contains_no_secrets_or_patient_payloads(): void
    {
        $peer = $this->peer(); $other = $this->peer();
        app(SyncLedger::class)->stage($peer->id, $this->event($peer, true));
        $owner = User::findOrFail($peer->lab_id);
        $this->actingAs($owner, 'sanctum')->getJson('/api/lab-sync/status')->assertOk()
            ->assertJsonPath('pending', 1)->assertJsonPath('clinical_sync_ready', false)
            ->assertJsonMissingPath('token_hash')->assertJsonMissingPath('outbound_token');
        $this->actingAs(User::findOrFail($other->lab_id), 'sanctum')->getJson('/api/lab-sync/status')->assertOk()->assertJsonPath('pending', 0);
        $owner->update(['role_id' => 7]);
        $this->actingAs($owner, 'sanctum')->getJson('/api/lab-sync/status')->assertForbidden();
    }

    public function test_network_failure_retains_pending_event_and_cursor(): void
    {
        $peer = $this->peer(); $event = $this->event($peer, true);
        app(SyncLedger::class)->stage($peer->id, $event);
        Http::fake(['*' => Http::failedConnection()]);
        try { app(SyncExchange::class)->run($peer->id); $this->fail('Expected transport failure'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('pending', $e->getMessage()); }
        $this->assertDatabaseHas('lab_sync_events', ['event_uuid' => $event['event_uuid'], 'delivered_at' => null]);
        $this->assertDatabaseHas('lab_sync_peers', ['id' => $peer->id, 'pull_cursor' => 0, 'last_error' => 'exchange_failed']);
    }

    public function test_successful_exchange_sends_pulls_and_acknowledges_but_does_not_apply(): void
    {
        $peer = $this->peer(); $local = $this->event($peer, true); $remote = $this->event($peer);
        app(SyncLedger::class)->stage($peer->id, $local);
        Http::fake([
            '*/events' => Http::response(['event_uuid' => $local['event_uuid'], 'state' => 'staged', 'clinical_applied' => false]),
            '*/changes*' => Http::response(['protocol' => 1, 'mode' => 'staging_only', 'clinical_applied' => false,
                'cursor' => 10, 'events' => [['sequence' => 10, 'event' => $remote]]]),
            '*/acknowledgements' => Http::response(['acknowledged' => $remote['event_uuid']]),
        ]);
        $result = app(SyncExchange::class)->run($peer->id);
        $this->assertSame(['sent' => 1, 'received' => 1, 'clinical_applied' => false], $result);
        $this->assertDatabaseHas('lab_sync_peers', ['id' => $peer->id, 'pull_cursor' => 10, 'last_error' => null]);
        $this->assertNotNull(DB::table('lab_sync_events')->where('event_uuid', $local['event_uuid'])->value('delivered_at'));
        $this->assertDatabaseCount('lab_sync_events', 2);
        $this->assertDatabaseCount('invoice_test_rels', 0);
    }

    public function test_invalid_ack_does_not_drop_pending_event(): void
    {
        $peer = $this->peer(); $event = $this->event($peer, true);
        app(SyncLedger::class)->stage($peer->id, $event);
        Http::fake(['*' => Http::response(['event_uuid' => (string) Str::uuid(), 'state' => 'staged', 'clinical_applied' => false])]);
        try { app(SyncExchange::class)->run($peer->id); $this->fail('Expected invalid ack'); }
        catch (\RuntimeException $e) {}
        $this->assertDatabaseHas('lab_sync_events', ['event_uuid' => $event['event_uuid'], 'delivered_at' => null]);
    }

    public function test_cursor_does_not_advance_when_pull_ack_is_lost_and_retry_deduplicates(): void
    {
        $peer = $this->peer(); $remote = $this->event($peer);
        $batch = ['protocol' => 1, 'mode' => 'staging_only', 'clinical_applied' => false,
            'cursor' => 7, 'events' => [['sequence' => 7, 'event' => $remote]]];
        Http::fake(['*/changes*' => Http::response($batch), '*/acknowledgements' => Http::response([], 503)]);
        try { app(SyncExchange::class)->run($peer->id); $this->fail('Expected failed ack'); }
        catch (\RuntimeException $e) {}
        $this->assertDatabaseHas('lab_sync_peers', ['id' => $peer->id, 'pull_cursor' => 0]);
        $this->assertDatabaseCount('lab_sync_events', 1);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*/changes*' => Http::response($batch), '*/acknowledgements' => Http::response(['acknowledged' => $remote['event_uuid']])]);
        app(SyncExchange::class)->run($peer->id);
        $this->assertDatabaseCount('lab_sync_events', 1);
        $this->assertDatabaseHas('lab_sync_peers', ['id' => $peer->id, 'pull_cursor' => 7]);
    }

    public function test_remote_conflict_receipt_is_preserved_and_cannot_be_replaced_by_success(): void
    {
        $peer = $this->peer(); $event = $this->event($peer, true); $ledger = app(SyncLedger::class);
        $ledger->stage($peer->id, $event);
        $receipt = ['event_uuid' => $event['event_uuid'], 'state' => 'conflict', 'clinical_applied' => false];
        $this->withToken($peer->token)->postJson('/api/lab-sync/v1/acknowledgements', $receipt)->assertOk();
        $receipt['state'] = 'staged';
        $this->postJson('/api/lab-sync/v1/acknowledgements', $receipt)->assertConflict();
        $this->assertDatabaseHas('lab_sync_events', ['event_uuid' => $event['event_uuid'], 'remote_state' => 'conflict']);
    }
    public function test_inspection_reports_counts_without_names_emails_or_secrets(): void
    {
        $peer = $this->peer();
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('lab-sync:inspect'));
        $output = \Illuminate\Support\Facades\Artisan::output();
        $report = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
        $this->assertTrue($report['read_only']);
        $this->assertFalse($report['clinical_sync_ready']);
        $this->assertSame(1, $report['counts']['users']);
        $this->assertStringNotContainsString($peer->token, $output);
        $this->assertStringNotContainsString('@example.test', $output);
        $this->assertStringNotContainsString('Sync lab', $output);
        $this->assertDatabaseCount('lab_sync_events', 0);
    }

    public function test_insecure_remote_origin_never_receives_credentials(): void
    {
        $peer = $this->peer();
        DB::table('lab_sync_peers')->where('id', $peer->id)->update(['remote_url' => 'http://sync.example.test']);
        Http::fake();
        try { app(SyncExchange::class)->run($peer->id); $this->fail('Expected HTTPS validation'); }
        catch (\RuntimeException $e) {}
        Http::assertNothingSent();
    }

    public function test_fabricated_cursor_cannot_skip_unreceived_events(): void
    {
        $peer = $this->peer();
        Http::fake(['*' => Http::response(['protocol' => 1, 'mode' => 'staging_only',
            'clinical_applied' => false, 'events' => [], 'cursor' => 999])]);
        try { app(SyncExchange::class)->run($peer->id); $this->fail('Expected cursor validation'); }
        catch (\RuntimeException $e) {}
        $this->assertDatabaseHas('lab_sync_peers', ['id' => $peer->id, 'pull_cursor' => 0]);
        $this->assertDatabaseCount('lab_sync_events', 0);
    }

}
