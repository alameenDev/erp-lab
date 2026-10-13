<?php

namespace Tests\Feature;

use App\Models\ClientMapLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientMapLocationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function user(int $role = 1): User
    {
        return User::create([
            'name' => 'Synthetic map user',
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password-123',
            'role_id' => $role,
        ]);
    }

    private function payload(array $replace = []): array
    {
        return array_replace([
            'request_id' => (string) Str::uuid(),
            'name' => 'Synthetic Medical Lab',
            'address' => 'Baghdad, Iraq',
            'latitude' => 33.312805,
            'longitude' => 44.361488,
            'enabled' => 1,
            'sort_order' => 10,
            'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
        ], $replace);
    }

    public function test_public_endpoint_returns_only_enabled_locations_without_internal_fields(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->post('/api/super-admin/client-labs', $this->payload())
            ->assertCreated();

        $this->post('/api/super-admin/client-labs', $this->payload([
            'request_id' => (string) Str::uuid(),
            'name' => 'Hidden Lab',
            'enabled' => 0,
        ]))->assertCreated();

        $this->assertDatabaseCount('client_map_locations', 2);

        $response = $this->getJson('/api/public/client-labs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Synthetic Medical Lab')
            ->assertJsonPath('data.0.latitude', 33.312805)
            ->assertJsonPath('data.0.longitude', 44.361488);

        $response->assertDontSee('payload_hash')
            ->assertDontSee('request_id')
            ->assertDontSee('logo_path')
            ->assertDontSee('created_by');
    }

    public function test_only_admin_can_manage_locations_and_coordinates_are_validated(): void
    {
        $lab = $this->user(2);
        $this->actingAs($lab)->getJson('/api/super-admin/client-labs')->assertForbidden();
        $this->post('/api/super-admin/client-labs', $this->payload())->assertForbidden();

        $admin = $this->user();
        $this->actingAs($admin)->post('/api/super-admin/client-labs', $this->payload([
            'latitude' => 91,
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->post('/api/super-admin/client-labs', $this->payload([
            'longitude' => 181,
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');
    }

    public function test_create_retry_update_conflict_and_soft_delete_are_safe(): void
    {
        $admin = $this->user();
        $this->actingAs($admin);

        $payload = $this->payload();
        $created = $this->post('/api/super-admin/client-labs', $payload)
            ->assertCreated()
            ->json('location');

        $retry = $payload;
        $retry['logo'] = UploadedFile::fake()->image('logo.png', 120, 120);
        $this->post('/api/super-admin/client-labs', $retry)
            ->assertCreated()
            ->assertJsonPath('location.id', $created['id']);

        $this->assertDatabaseCount('client_map_locations', 1);

        $this->post('/api/super-admin/client-labs/'.$created['id'], [
            'version' => $created['version'],
            'name' => 'Updated Synthetic Lab',
            'address' => 'Karrada, Baghdad',
            'latitude' => 33.300000,
            'longitude' => 44.400000,
            'enabled' => 1,
            'sort_order' => 1,
        ])->assertOk()->assertJsonPath('location.version', 2);

        $this->post('/api/super-admin/client-labs/'.$created['id'], [
            'version' => 1,
            'name' => 'Stale Update',
            'address' => 'Baghdad',
            'latitude' => 33.3,
            'longitude' => 44.4,
            'enabled' => 1,
            'sort_order' => 1,
        ])->assertStatus(409);

        $this->deleteJson('/api/super-admin/client-labs/'.$created['id'], ['version' => 2])
            ->assertOk();

        $this->assertSoftDeleted('client_map_locations', ['id' => $created['id']]);
        $this->getJson('/api/public/client-labs')->assertOk()->assertJsonCount(0, 'data');
    }
}
