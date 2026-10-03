<?php

namespace Tests\Feature;

use App\Models\{DeviceResult, Invoice, InvoiceTestRel, LabDevice, Patient, Test as LabTest, TestGroup, Package, User};
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

    private function cbcFixture(): array
    {
        $device = $this->device();
        $person = User::create(['name'=>'Test patient', 'email'=>'cbc@example.test', 'password'=>'test-password', 'role_id'=>6]);
        $patient = Patient::create(['user_id'=>$person->id, 'creator_id'=>$device->lab_id_fk, 'code'=>'CBC-1']);
        $invoice = Invoice::create(['lab_id_fk'=>$device->lab_id_fk, 'patient_id_fk'=>$patient->id,
            'barcode'=>'TEST-SAMPLE', 'is_done'=>false]);
        $test = LabTest::create(['lab_id_fk'=>$device->lab_id_fk, 'name'=>'CBC', 'shortcut'=>'CBC',
            'interface_code'=>'12345678', 'price'=>0]);
        $rel = InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id, 'test_id_fk'=>$test->id, 'is_done'=>false]);
        $payload = json_decode(file_get_contents(base_path('../bridge/tests/capture_payload.json')), true);
        $payload['device_id'] = $device->id;
        $invoice->update(['barcode'=>$payload['specimen_barcode']]);
        $this->withHeader('X-Device-Token', $device->api_token);
        return [$device, $invoice, $test, $rel, $payload];
    }

    public function test_cbc_barcode_import_preserves_all_clinical_rows_and_does_not_release_report(): void
    {
        [$device, $invoice, $test, $rel, $payload] = $this->cbcFixture();
        $first = $this->postJson('/api/device/bridge/results', $payload)->assertCreated()->assertJsonPath('status', 'applied');
        $rel->refresh();
        $this->assertCount(21, $rel->sub_tests);
        $this->assertSame(['name','type','value','unit','reference_range'], array_keys($rel->sub_tests[0]));
        $this->assertSame('11.57', $rel->sub_tests[2]['value']);
        $this->assertSame('g/dL', $rel->sub_tests[2]['unit']);
        $this->assertSame('12 to 16.5', $rel->sub_tests[2]['reference_range']);
        $this->assertSame(4, substr_count($rel->content['html'], '<th '));
        $this->assertStringNotContainsString('@PCT', $rel->content['html']);
        $this->assertContains('Lyse Expired', $rel->content['review_messages']);
        $this->assertFalse($rel->is_done);
        $this->assertFalse($invoice->fresh()->is_done);
        $this->assertFalse((bool) $invoice->fresh()->sent_to_patient);
        $saved = DeviceResult::find($first->json('id'));
        $this->assertCount(27, $saved->parsed_results);
        $this->assertCount(8, $saved->instrument_metadata['comments']);
        $this->postJson('/api/device/bridge/results', $payload)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('device_results', 1);
        $this->assertSame($rel->sub_tests, $rel->fresh()->sub_tests);
    }

    public function test_cbc_requires_exact_barcode_and_interface_code_and_does_not_overwrite(): void
    {
        [$device, $invoice, $test, $rel, $payload] = $this->cbcFixture();
        $test->update(['interface_code'=>'OTHER']);
        $response = $this->postJson('/api/device/bridge/results', $payload)->assertCreated()->assertJsonPath('status','matched');
        $this->assertNull($rel->fresh()->sub_tests);
        $owner = User::find($device->lab_id_fk);
        $owner->givePermissionTo('medical reports update');
        $this->actingAs($owner);
        $test->update(['interface_code'=>'12345678']);
        $rel->update(['result'=>'previous result']);
        $this->postJson('/api/device-results/'.$response->json('id').'/apply')->assertUnprocessable();
        $this->assertSame('previous result', $rel->fresh()->result);
        $rel->update(['result'=>null]);
        $invoice->update(['is_signed'=>true]);
        $this->postJson('/api/device-results/'.$response->json('id').'/apply')->assertUnprocessable();
        $invoice->update(['is_signed'=>false, 'barcode'=>'another-barcode']);
        $this->postJson('/api/device-results/'.$response->json('id').'/apply')->assertUnprocessable();
        $this->assertNull($rel->fresh()->sub_tests);
        $invoice->update(['barcode'=>$payload['specimen_barcode']]);
        $this->postJson('/api/device-results/'.$response->json('id').'/apply')->assertOk()->assertJsonPath('applied_count',21);
        $this->postJson('/api/device-results/'.$response->json('id').'/apply')->assertOk()->assertJsonPath('duplicate',true);
    }

    public function test_cbc_ambiguous_invoice_or_panel_is_held(): void
    {
        [$device, $invoice, $test, $rel, $payload] = $this->cbcFixture();
        $second = Invoice::create(['lab_id_fk'=>$device->lab_id_fk,'patient_id_fk'=>$invoice->patient_id_fk,'barcode'=>$invoice->barcode]);
        $response = $this->postJson('/api/device/bridge/results', $payload)->assertCreated()->assertJsonPath('invoice_matched',false);
        $source = DeviceResult::find($response->json('id'));
        $this->assertNull($rel->fresh()->sub_tests);
        $second->delete();
        $duplicatePanel = LabTest::create(['lab_id_fk'=>$device->lab_id_fk,'name'=>'CBC duplicate mapping',
            'interface_code'=>'12345678','price'=>0]);
        InvoiceTestRel::create(['invoice_id_fk'=>$invoice->id,'test_id_fk'=>$duplicatePanel->id]);
        $outcome = app(\App\Services\BridgeCbcService::class)->apply($source);
        $this->assertFalse($outcome['applied']);
        $this->assertNull($rel->fresh()->sub_tests);
    }

    public function test_cbc_inside_group_and_package_keeps_other_results(): void
    {
        [$device, $invoice, $test, $rel, $payload] = $this->cbcFixture();
        $group = TestGroup::create(['lab_id_fk'=>$device->lab_id_fk,'group_name'=>'Haematology']);
        $group->tests()->attach($test->id);
        $other = LabTest::create(['lab_id_fk'=>$device->lab_id_fk,'name'=>'Other','price'=>0]);
        $items = [
            ['id'=>$test->id,'name'=>'CBC','result'=>null],
            ['id'=>$other->id,'name'=>'Other','result'=>'unchanged'],
        ];
        $rel->update(['test_id_fk'=>null,'test_group_id_fk'=>$group->id,'test_group_tests'=>$items]);
        $response = $this->postJson('/api/device/bridge/results',$payload)->assertCreated()->assertJsonPath('status','applied');
        $stored = $rel->fresh()->test_group_tests;
        $this->assertCount(21,$stored[0]['sub_tests']);
        $this->assertSame('unchanged',$stored[1]['result']);
        $this->assertFalse($stored[0]['is_done']);
        // Apply a different saved transmission to a package containing the CBC test.
        $package = Package::create(['lab_id_fk'=>$device->lab_id_fk,'name'=>'Package','price'=>0]);
        $package->tests()->attach($test->id);
        $rel->update(['test_group_id_fk'=>null,'test_group_tests'=>null,'package_id_fk'=>$package->id,'package_tests'=>$items]);
        $payload['raw_message'] .= "\r";
        $payload['delivery_id'] = hash('sha256',$payload['raw_message']);
        $this->postJson('/api/device/bridge/results',$payload)->assertCreated()->assertJsonPath('status','applied');
        $stored = $rel->fresh()->package_tests;
        $this->assertCount(21,$stored[0]['sub_tests']);
        $this->assertSame('unchanged',$stored[1]['result']);
    }

    public function test_cbc_unknown_or_repeated_codes_are_held_and_html_is_escaped(): void
    {
        [$device, $invoice, $test, $rel, $payload] = $this->cbcFixture();
        $payload['parsed_results'][] = $payload['parsed_results'][0];
        $r = $this->postJson('/api/device/bridge/results',$payload)->assertCreated();
        $this->assertNull($rel->fresh()->sub_tests);
        $source = DeviceResult::find($r->json('id'));
        array_pop($payload['parsed_results']);
        $payload['parsed_results'][0]['value'] = '<img src=x onerror=alert(1)>';
        $source->update(['parsed_results'=>$payload['parsed_results']]);
        $outcome = app(\App\Services\BridgeCbcService::class)->apply($source);
        $this->assertTrue($outcome['applied']);
        $this->assertStringNotContainsString('<img', $rel->fresh()->content['html']);
        $this->assertStringContainsString('&lt;img', $rel->fresh()->content['html']);
    }
    public function test_np21_full_panel_and_transport_time_retransmission(): void
    {
        [$device, $invoice, $test, $rel] = $this->cbcFixture();
        $payload = json_decode(file_get_contents(base_path('../bridge/tests/np21_payload.json')), true);
        $payload['device_id'] = $device->id;
        $invoice->update(['barcode' => $payload['specimen_barcode']]);
        $first = $this->postJson('/api/device/bridge/results', $payload)->assertCreated()->assertJsonPath('status', 'applied');
        $rows = $rel->fresh()->sub_tests;
        $this->assertCount(21, $rows);
        $this->assertSame('GRAN%', $rows[2]['name']);
        $this->assertSame('PCT', $rows[18]['name']);
        $this->assertSame('0.326', $rows[18]['value']);
        $this->assertSame('10*9/L', $rows[20]['unit']);
        $html = $rel->fresh()->content['html'];
        $this->assertStringContainsString('Complete Blood Count (CBC)', $html);
        $this->assertStringNotContainsString('Age', $html);
        $this->assertSame(4, substr_count($html, '<th '));
        $this->assertFalse($rel->fresh()->is_done);
        $this->postJson('/api/device/bridge/results', $payload)->assertOk()->assertJsonPath('duplicate', true);
        $payload['raw_message'] = str_replace('20261003183702', '20261003185702', $payload['raw_message']);
        $payload['delivery_id'] = hash('sha256', $payload['raw_message']);
        $payload['instrument_metadata']['orders'][0]['message_time'] = '20261003185702';
        $this->postJson('/api/device/bridge/results', $payload)->assertOk()
            ->assertJsonPath('duplicate', true)->assertJsonPath('id', $first->json('id'))
            ->assertJsonPath('delivery_id', $payload['delivery_id']);
        $this->assertDatabaseCount('device_results', 1);
        $payload['parsed_results'][0]['value'] = '99';
        $this->postJson('/api/device/bridge/results', $payload)->assertStatus(409);
    }

    public function test_np21_missing_parameter_is_held_without_overwriting_invoice(): void
    {
        [$device, $invoice, $test, $rel] = $this->cbcFixture();
        $payload = json_decode(file_get_contents(base_path('../bridge/tests/np21_payload.json')), true);
        $payload['device_id'] = $device->id;
        $invoice->update(['barcode' => $payload['specimen_barcode']]);
        array_pop($payload['parsed_results']);
        $this->postJson('/api/device/bridge/results', $payload)->assertCreated()->assertJsonPath('status', 'matched');
        $this->assertNull($rel->fresh()->sub_tests);
    }

}
