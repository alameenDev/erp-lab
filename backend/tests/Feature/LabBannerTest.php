<?php

namespace Tests\Feature;

use App\Models\{LabBanner, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LabBannerTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function user(int $role = 2, ?int $creator = null): User
    {
        return User::create(['name' => 'Synthetic banner user', 'email' => Str::uuid().'@example.test',
            'password' => 'test-password-123', 'role_id' => $role, 'creator_id' => $creator]);
    }

    private function payload(array $replace = []): array
    {
        return array_replace(['request_id' => (string) Str::uuid(), 'title' => 'Banner title', 'description' => 'Banner details',
            'image' => UploadedFile::fake()->image('banner.jpg', 1600, 400), 'audience' => 'all', 'enabled' => true, 'sort_order' => 0], $replace);
    }

    private function send(array $payload)
    {
        return $this->post('/api/lab-banners', $payload, ['Accept' => 'application/json']);
    }

    private function editData(LabBanner $banner, array $replace = []): array
    {
        return array_replace(['title' => $banner->title, 'description' => $banner->description, 'audience' => 'all',
            'enabled' => true, 'sort_order' => 0, 'version' => $banner->version], $replace);
    }

    public function test_only_administrators_manage_banners_and_lab_options(): void
    {
        $this->getJson('/api/lab-banners')->assertUnauthorized();
        $this->getJson('/api/lab-banners/active')->assertUnauthorized();
        $owner = $this->user(); $this->actingAs($owner);
        $this->getJson('/api/lab-banners')->assertForbidden();
        $this->getJson('/api/lab-banners/labs')->assertForbidden();
        $this->send($this->payload())->assertForbidden();
        $this->postJson('/api/lab-banners/1', [])->assertForbidden();
        $this->deleteJson('/api/lab-banners/1', ['version' => 1])->assertForbidden();
        $admin = $this->user(1); $this->actingAs($admin);
        $this->getJson('/api/lab-banners/labs')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $owner->id);
        $this->getJson('/api/lab-banners/active')->assertForbidden();
    }

    public function test_main_mobile_image_and_optional_link_are_saved_without_exposing_internal_paths(): void
    {
        $this->actingAs($this->user(1));
        $id = $this->send($this->payload(['mobile_image' => UploadedFile::fake()->image('phone.png', 800, 600),
            'button_label' => 'Learn more', 'button_url' => 'https://example.test/offer']))->assertCreated()
            ->assertJsonPath('banner.enabled', true)->assertJsonPath('banner.version', 1)
            ->assertJsonMissingPath('banner.image_path')->assertJsonMissingPath('banner.payload_hash')->json('banner.id');
        $banner = LabBanner::findOrFail($id);
        Storage::disk('public')->assertExists([$banner->image_path, $banner->mobile_image_path]);
        $this->actingAs($this->user())->getJson('/api/lab-banners/active')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.button_url', 'https://example.test/offer')->assertJsonMissingPath('data.0.targets')
            ->assertJsonMissingPath('data.0.created_by');
    }

    public function test_selected_labs_and_nested_staff_are_isolated_from_other_labs_and_patients(): void
    {
        $one = $this->user(); $two = $this->user(); $other = $this->user();
        $branch = $this->user(4, $one->id); $staff = $this->user(7, $branch->id);
        $this->actingAs($this->user(1));
        $this->send($this->payload(['audience' => 'selected', 'lab_ids' => [$one->id, $two->id]]))->assertCreated();
        foreach ([$one, $two, $staff] as $user) $this->actingAs($user)->getJson('/api/lab-banners/active')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($other)->getJson('/api/lab-banners/active')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/lab-banners/active?lab_id='.$one->id)->assertUnprocessable();
        foreach ([3, 5] as $role) $this->actingAs($this->user($role, $one->id))->getJson('/api/lab-banners/active')->assertForbidden();
    }

    public function test_all_labs_includes_future_labs_but_not_referral_or_orphan_accounts(): void
    {
        $this->actingAs($this->user(1)); $this->send($this->payload())->assertCreated();
        $lab = $this->user();
        $this->actingAs($lab)->getJson('/api/lab-banners/active')->assertOk()->assertJsonCount(1, 'data');
        $referral = $this->user(); $referral->forceFill(['referral_portal_only' => true])->save();
        $this->actingAs($referral)->getJson('/api/lab-banners/active')->assertForbidden();
        $this->actingAs($this->user(7))->getJson('/api/lab-banners/active')->assertForbidden();
        $a = $this->user(7); $b = $this->user(7, $a->id); $a->update(['creator_id' => $b->id]);
        $this->actingAs($a)->getJson('/api/lab-banners/active')->assertForbidden();
    }

    public function test_invalid_targets_links_dates_and_images_do_not_leave_partial_uploads(): void
    {
        $patient = $this->user(3); $this->actingAs($this->user(1));
        $this->send($this->payload(['audience' => 'selected', 'lab_ids' => [$patient->id]]))->assertUnprocessable();
        $this->send($this->payload(['button_label' => 'Bad', 'button_url' => 'javascript:alert(1)']))->assertUnprocessable();
        $this->send($this->payload(['button_label' => 'Incomplete']))->assertUnprocessable();
        $this->send($this->payload(['starts_at' => '2028-01-02T00:00:00Z', 'ends_at' => '2028-01-01T00:00:00Z']))->assertUnprocessable();
        $this->send($this->payload(['image' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')]))->assertUnprocessable();
        $this->send($this->payload(['image' => UploadedFile::fake()->image('large.jpg', 800, 200)->size(5121)]))->assertUnprocessable();
        $this->send($this->payload(['image' => UploadedFile::fake()->image('tiny.png', 2, 2)]))->assertUnprocessable();
        $this->assertDatabaseCount('lab_banners', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('lab-banners'));
    }

    public function test_create_retries_are_idempotent_and_payload_changes_conflict(): void
    {
        $this->actingAs($this->user(1)); $payload = $this->payload();
        $id = $this->send($payload)->assertCreated()->json('banner.id');
        $this->send($payload)->assertCreated()->assertJsonPath('banner.id', $id);
        $this->send(array_replace($payload, ['title' => 'Different title']))->assertStatus(409);
        $this->assertDatabaseCount('lab_banners', 1);
        $this->assertCount(1, Storage::disk('public')->allFiles('lab-banners'));
    }

    public function test_update_replaces_assets_clears_targets_and_protects_against_lost_updates(): void
    {
        $lab = $this->user(); $this->actingAs($this->user(1));
        $id = $this->send($this->payload(['audience' => 'selected', 'lab_ids' => [$lab->id],
            'mobile_image' => UploadedFile::fake()->image('mobile.jpg', 800, 600)]))->assertCreated()->json('banner.id');
        $banner = LabBanner::findOrFail($id); $oldImage = $banner->image_path; $oldMobile = $banner->mobile_image_path;
        $data = $this->editData($banner, ['title' => 'Updated banner', 'remove_mobile_image' => true,
            'image' => UploadedFile::fake()->image('replacement.jpg', 1200, 400)]);
        $this->post('/api/lab-banners/'.$id, $data, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('banner.version', 2)
            ->assertJsonPath('banner.mobile_image_url', null)->assertJsonCount(0, 'banner.targets');
        Storage::disk('public')->assertMissing([$oldImage, $oldMobile]);
        Storage::disk('public')->assertExists($banner->fresh()->image_path);
        $this->post('/api/lab-banners/'.$id, $data, ['Accept' => 'application/json'])->assertStatus(409);
        $this->assertCount(1, Storage::disk('public')->allFiles('lab-banners'));
        $this->assertDatabaseCount('lab_banner_targets', 0);
    }

    public function test_enabled_schedule_expiry_and_priority_control_the_active_feed(): void
    {
        $admin = $this->user(1); $lab = $this->user(); $this->actingAs($admin);
        $this->send($this->payload(['title' => 'Disabled', 'enabled' => false]))->assertCreated();
        $this->send($this->payload(['title' => 'Future', 'starts_at' => now()->addHour()->toISOString()]))->assertCreated();
        $this->send($this->payload(['title' => 'Expired', 'ends_at' => now()->subHour()->toISOString()]))->assertCreated();
        $this->send($this->payload(['title' => 'Second', 'sort_order' => 20]))->assertCreated();
        $this->send($this->payload(['title' => 'First', 'sort_order' => 1]))->assertCreated();
        $this->actingAs($lab)->getJson('/api/lab-banners/active')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.title', 'First');
        $this->actingAs($admin)->getJson('/api/lab-banners')->assertOk()->assertJsonPath('total', 5);
    }

    public function test_soft_delete_removes_the_banner_without_deleting_unrelated_files_or_replaying_it(): void
    {
        $admin = $this->user(1); $lab = $this->user(); $this->actingAs($admin);
        Storage::disk('public')->put('other/lab-logo.png', 'synthetic file');
        $payload = $this->payload(); $id = $this->send($payload)->assertCreated()->json('banner.id');
        $path = LabBanner::findOrFail($id)->image_path;
        $this->deleteJson('/api/lab-banners/'.$id, ['version' => 9])->assertStatus(409);
        $this->deleteJson('/api/lab-banners/'.$id, ['version' => 1])->assertOk();
        $this->assertSoftDeleted('lab_banners', ['id' => $id]);
        Storage::disk('public')->assertExists([$path, 'other/lab-logo.png']);
        $this->send($payload)->assertStatus(409);
        $this->actingAs($lab)->getJson('/api/lab-banners/active')->assertOk()->assertJsonCount(0, 'data');
    }
}
