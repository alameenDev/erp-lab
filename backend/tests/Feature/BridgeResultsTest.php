<?php

namespace Tests\Feature;

use App\Models\{DeviceResult, Invoice, LabDevice, Patient, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeResultsTest extends TestCase
{
    use RefreshDatabase;

    private function device(): LabDevice
    {
        $this->seed(ReferenceDataSeeder::class);
        $owner = User::create(['name' => 'Lab', 'email' => 'bridge@example.test', 'password' => 'test-password', 'role_id' => 2]);
        return LabDevice::create(['lab_id_fk' => $owner->id, 'name' => 'DxH', 'api_token' => str_repeat('a', 64)]);
    }

    private function payload(LabDevice $device): array
    {
        $raw = "H|\\!~|||DxH 500!01\rP|1||!\rO|1|TEST-123!\rR|1|!!!@PCT|0.205 ! R |%||0 to 9.999|A\rL|1|N\r";
        return ['delivery_id' => hash('sha256', $raw), 'device_id' => $device->id,
            'specimen_barcode' => 'TEST-123', 'raw_message' => $raw,
            'parsed_results' => [['test_code' => '@PCT', 'value' => '0.205', 'unit' => '%', 'flags' => 'R',
                'reference_range' => '0 to 9.999', 'research_only' => false, 'raw_value' => '0.205 ! R ']],
            'instrument_metadata' => ['adapter' => 'dxh500', 'warnings' => ['Lyse Expired'],
                'comments' => [['scope' => 'sample', 'code' => '', 'text' => 'Lyse Expired']], 'orders' => []]];
    }

    public function test_authenticated_durable_idempotent_receipt_preserves_raw_and_warnings(): void
    {
        $device = $this->device(); $payload = $this->payload($device);
        $this->postJson('/api/device/bridge/results', $payload)->assertUnauthorized();
        $this->withHeader('X-Device-Token', $device->api_token);
        $this->postJson('/api/device/bridge/heartbeat')->assertOk()->assertJsonPath('protocol', 'labbridge-v1');
        $first = $this->postJson('/api/device/bridge/results', $payload)->assertCreated()
            ->assertJsonPath('stored', true)->assertJsonPath('duplicate', false);
        $this->postJson('/api/device/bridge/results', $payload)->assertOk()
            ->assertJsonPath('duplicate', true)->assertJsonPath('id', $first->json('id'));
        $this->assertDatabaseCount('device_results', 1);
        $saved = DeviceResult::first();
        $this->assertSame($payload['raw_message'], $saved->raw_message);
        $this->assertSame('0.205 ! R ', $saved->parsed_results[0]['raw_value']);
        $this->assertTrue($saved->parsed_results[0]['research_only']);
        $this->assertTrue($saved->instrument_metadata['review_required']);
        $this->assertSame('Lyse Expired', $saved->instrument_metadata['comments'][0]['text']);
        $payload['parsed_results'][0]['value'] = '99';
        $this->postJson('/api/device/bridge/results', $payload)->assertStatus(409);
        $this->assertDatabaseCount('device_results', 1);
    }

    public function test_full_python_capture_keeps_all_27_results_and_comments(): void
    {
        $device = $this->device();
        $payload = json_decode(file_get_contents(base_path('../bridge/tests/capture_payload.json')), true);
        $payload['device_id'] = $device->id;
        $this->withHeader('X-Device-Token', $device->api_token)
            ->postJson('/api/device/bridge/results', $payload)->assertCreated()->assertJsonPath('stored', true);
        $saved = DeviceResult::first();
        $this->assertCount(27, $saved->parsed_results);
        $this->assertCount(8, $saved->instrument_metadata['comments']);
        $this->assertSame('11.57', $saved->parsed_results[2]['value']);
        $this->assertSame('Rl', $saved->parsed_results[2]['flags']);
        $this->assertSame('12 to 16.5', $saved->parsed_results[2]['reference_range']);
        $this->assertSame($payload['raw_message'], $saved->raw_message);
    }

    public function test_wrong_destination_and_hash_are_rejected(): void
    {
        $device = $this->device(); $payload = $this->payload($device);
        $this->withHeader('X-Device-Token', $device->api_token);
        $payload['device_id']++;
        $this->postJson('/api/device/bridge/results', $payload)->assertStatus(409);
        $payload['device_id'] = $device->id; $payload['delivery_id'] = str_repeat('b', 64);
        $this->postJson('/api/device/bridge/results', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('device_results', 0);
    }

    public function test_matching_is_tenant_scoped_and_does_not_release_invoice(): void
    {
        $device = $this->device(); $payload = $this->payload($device);
        $other = User::create(['name'=>'Other', 'email'=>'other-bridge@example.test', 'password'=>'test-password', 'role_id'=>2]);
        $person = User::create(['name'=>'Patient', 'email'=>'bridge-patient@example.test', 'password'=>'test-password', 'role_id'=>6]);
        $patient = Patient::create(['user_id'=>$person->id, 'creator_id'=>$device->lab_id_fk, 'code'=>'P-1']);
        Invoice::create(['lab_id_fk'=>$other->id,'patient_id_fk'=>$patient->id,'barcode'=>'TEST-123']);
        $this->withHeader('X-Device-Token',$device->api_token);
        $this->postJson('/api/device/bridge/results',$payload)->assertCreated()->assertJsonPath('invoice_matched',false);
        $own = Invoice::create(['lab_id_fk'=>$device->lab_id_fk,'patient_id_fk'=>$patient->id,'barcode'=>'TEST-456','is_done'=>false]);
        $payload['raw_message'] = str_replace('TEST-123','TEST-456',$payload['raw_message']);
        $payload['specimen_barcode']='TEST-456'; $payload['delivery_id']=hash('sha256',$payload['raw_message']);
        $r=$this->postJson('/api/device/bridge/results',$payload)->assertCreated()->assertJsonPath('invoice_matched',true);
        $this->assertFalse((bool)$own->fresh()->is_done);
        $owner=User::find($device->lab_id_fk);$owner->givePermissionTo('medical reports update');$this->actingAs($owner);
        $this->postJson('/api/device-results/'.$r->json('id').'/apply')->assertUnprocessable();
    }
}
