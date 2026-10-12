<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LabAnnouncementTest extends TestCase
{
    use DatabaseMigrations;

    private function user(int $role = 2, ?int $creator = null): User
    {
        return User::create(['name' => 'Synthetic '.Str::random(8), 'email' => Str::uuid().'@example.test',
            'password' => 'test-password-123', 'role_id' => $role, 'creator_id' => $creator]);
    }

    private function payload(array $labs = [], array $changes = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'title' => 'Synthetic notice', 'body' => 'A plain-text message',
            'level' => 'info', 'audience' => 'selected', 'lab_ids' => $labs], $changes);
    }

    private function send(User $admin, array $labs, array $changes = []): int
    {
        return $this->actingAs($admin)->postJson('/api/lab-announcements', $this->payload($labs, $changes))
            ->assertCreated()->json('id');
    }

    public function test_only_admin_can_send_list_targets_view_history_and_cancel(): void
    {
        $lab = $this->user(); $admin = $this->user(1);
        $this->getJson('/api/lab-announcements/pending')->assertUnauthorized();
        $this->postJson('/api/lab-announcements', $this->payload([$lab->id]))->assertUnauthorized();
        $id = $this->send($admin, [$lab->id]);
        $this->actingAs($lab)->getJson('/api/lab-announcements/labs')->assertForbidden();
        $this->getJson('/api/lab-announcements/history')->assertForbidden();
        $this->postJson('/api/lab-announcements', $this->payload([$lab->id]))->assertForbidden();
        $this->postJson('/api/lab-announcements/'.$id.'/cancel')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/lab-announcements/history')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_selected_labs_and_nested_staff_receive_but_other_labs_cannot_read_or_acknowledge(): void
    {
        $admin = $this->user(1); $one = $this->user(); $two = $this->user(); $other = $this->user();
        $branch = $this->user(4, $one->id); $staff = $this->user(7, $branch->id);
        $id = $this->send($admin, [$one->id, $two->id]);
        foreach ([$one, $two, $staff] as $user) {
            $this->actingAs($user)->getJson('/api/lab-announcements/pending')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.id', $id);
        }
        $this->actingAs($other)->getJson('/api/lab-announcements/pending')->assertOk()->assertJsonPath('unread_count', 0);
        $this->getJson('/api/lab-announcements/inbox')->assertOk()->assertJsonPath('total', 0);
        $this->postJson('/api/lab-announcements/'.$id.'/read')->assertNotFound();
        $this->getJson('/api/lab-announcements/pending?lab_id='.$one->id)->assertUnprocessable();
        $this->postJson('/api/lab-announcements/'.$id.'/read', ['lab_id' => $one->id])->assertUnprocessable();
    }

    public function test_read_receipts_are_persistent_idempotent_and_independent_per_user_and_tenant(): void
    {
        $admin = $this->user(1); $one = $this->user(); $two = $this->user(); $staff = $this->user(7, $one->id);
        $id = $this->send($admin, [$one->id, $two->id]);
        $this->actingAs($staff)->postJson('/api/lab-announcements/'.$id.'/read')->assertOk();
        $this->postJson('/api/lab-announcements/'.$id.'/read')->assertOk();
        $this->assertDatabaseCount('lab_announcement_reads', 1);
        $this->getJson('/api/lab-announcements/pending')->assertJsonPath('unread_count', 0);
        $this->getJson('/api/lab-announcements/inbox')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($one)->getJson('/api/lab-announcements/pending')->assertJsonPath('unread_count', 1);
        $staff->update(['creator_id' => $two->id]);
        $this->actingAs($staff)->getJson('/api/lab-announcements/pending')->assertJsonPath('unread_count', 1);
        $this->postJson('/api/lab-announcements/'.$id.'/read')->assertOk();
        $this->assertDatabaseCount('lab_announcement_reads', 2);
        $this->actingAs($admin)->getJson('/api/lab-announcements/history')->assertJsonPath('data.0.read_lab_count', 2);
    }

    public function test_all_is_a_snapshot_excluding_patients_deleted_labs_and_referral_portal_accounts(): void
    {
        $admin = $this->user(1); $one = $this->user(); $two = $this->user();
        $this->user(3, $one->id); $this->user(7, $one->id);
        $deleted = $this->user(); $deleted->delete();
        $portal = $this->user(); $portal->forceFill(['referral_portal_only' => true])->save();
        $payload = $this->payload([], ['audience' => 'all']); unset($payload['lab_ids']);
        $this->actingAs($admin)->getJson('/api/lab-announcements/labs')->assertOk()->assertJsonCount(2);
        $id = $this->postJson('/api/lab-announcements', $payload)->assertCreated()->assertJsonPath('target_count', 2)->json('id');
        $newLab = $this->user();
        $this->actingAs($newLab)->getJson('/api/lab-announcements/pending')->assertJsonPath('unread_count', 0);
        $this->assertDatabaseMissing('lab_announcement_targets', ['announcement_id' => $id, 'lab_id' => $portal->id]);
        $this->assertDatabaseMissing('lab_announcement_targets', ['announcement_id' => $id, 'lab_id' => $deleted->id]);
    }

    public function test_retry_is_idempotent_and_changed_payload_cannot_reuse_the_request_key(): void
    {
        $admin = $this->user(1); $lab = $this->user();
        $payload = $this->payload([$lab->id]);
        $this->actingAs($admin);
        $id = $this->postJson('/api/lab-announcements', $payload)->assertCreated()->json('id');
        $this->postJson('/api/lab-announcements', $payload)->assertCreated()->assertJsonPath('id', $id);
        $this->postJson('/api/lab-announcements', array_replace($payload, ['body' => 'Different']))->assertStatus(409);
        $this->assertDatabaseCount('lab_announcements', 1);
        $this->assertDatabaseCount('lab_announcement_targets', 1);
    }

    public function test_invalid_recipient_and_unsafe_link_do_not_partially_create_a_message(): void
    {
        $admin = $this->user(1); $lab = $this->user(); $patient = $this->user(3, $lab->id);
        $this->actingAs($admin);
        foreach ([[], [$lab->id, $patient->id], [$lab->id, 999999999], [$lab->id, $lab->id]] as $ids) {
            $this->postJson('/api/lab-announcements', $this->payload($ids))->assertUnprocessable();
        }
        foreach (['javascript:alert(1)', 'data:text/html,bad', 'ftp://example.test'] as $url) {
            $this->postJson('/api/lab-announcements', $this->payload([$lab->id], ['button_label' => 'Open', 'button_url' => $url]))->assertUnprocessable();
        }
        $this->postJson('/api/lab-announcements', $this->payload([$lab->id], ['expires_at' => now()->subMinute()->toISOString()]))->assertUnprocessable();
        $this->assertDatabaseCount('lab_announcements', 0);
        $this->assertDatabaseCount('lab_announcement_targets', 0);
    }

    public function test_expired_and_cancelled_notices_stop_appearing_and_cannot_be_acknowledged(): void
    {
        $admin = $this->user(1); $lab = $this->user();
        $expiring = $this->send($admin, [$lab->id], ['expires_at' => now()->addMinute()->toISOString()]);
        $cancelled = $this->send($admin, [$lab->id]);
        $this->postJson('/api/lab-announcements/'.$cancelled.'/cancel')->assertOk();
        $this->postJson('/api/lab-announcements/'.$cancelled.'/cancel')->assertOk();
        $this->actingAs($lab)->getJson('/api/lab-announcements/pending')->assertJsonPath('unread_count', 1);
        $this->travel(2)->minutes();
        $this->getJson('/api/lab-announcements/pending')->assertJsonPath('unread_count', 0);
        $this->getJson('/api/lab-announcements/inbox')->assertJsonPath('total', 0);
        $this->postJson('/api/lab-announcements/'.$expiring.'/read')->assertNotFound();
        $this->postJson('/api/lab-announcements/'.$cancelled.'/read')->assertNotFound();
        $this->travelBack();
    }

    public function test_patients_doctors_orphans_and_cyclic_ownership_cannot_fetch_staff_messages(): void
    {
        $lab = $this->user();
        $patient = $this->user(3, $lab->id); $doctor = $this->user(5, $lab->id); $orphan = $this->user(7);
        $cycle = $this->user(7); $cycle->update(['creator_id' => $cycle->id]);
        foreach ([$patient, $doctor, $orphan, $cycle] as $user) {
            $this->actingAs($user)->getJson('/api/lab-announcements/pending')->assertForbidden();
            $this->getJson('/api/lab-announcements/inbox')->assertForbidden();
        }
    }

    public function test_pending_queue_is_oldest_first_bounded_and_advances_without_skipping(): void
    {
        $admin = $this->user(1); $lab = $this->user();
        for ($i = 0; $i < 23; $i++) {
            $id = DB::table('lab_announcements')->insertGetId(['sender_id' => $admin->id, 'request_id' => Str::uuid(),
                'payload_hash' => str_repeat('0', 64), 'title' => 'Notice '.$i, 'body' => 'Synthetic', 'level' => 'info',
                'audience' => 'selected', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('lab_announcement_targets')->insert(['announcement_id' => $id, 'lab_id' => $lab->id]);
        }
        $this->actingAs($lab);
        $first = $this->getJson('/api/lab-announcements/pending')->assertOk()->assertJsonPath('unread_count', 23)->assertJsonCount(20, 'data')->json('data.0.id');
        $this->postJson('/api/lab-announcements/'.$first.'/read')->assertOk();
        $this->getJson('/api/lab-announcements/pending')->assertJsonPath('unread_count', 22)->assertJsonPath('data.0.id', $first + 1);
        $this->getJson('/api/lab-announcements/inbox?page=2')->assertOk()->assertJsonCount(8, 'data');
    }
}
