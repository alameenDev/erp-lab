<?php

namespace Tests\Feature;

use App\Models\{DeviceResult, Invoice, LabDevice, Patient, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeviceResultsInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $lab;
    private LabDevice $device;
    private Patient $patient;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->lab = User::create(['name' => 'Inbox lab', 'email' => 'inbox-lab@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $this->lab->givePermissionTo(Permission::findOrCreate('devices view', 'api'));
        $this->device = LabDevice::create(['lab_id_fk' => $this->lab->id, 'name' => 'DxH 500', 'api_token' => str_repeat('a', 64)]);
        $person = User::create(['name' => 'مريض تجريبي', 'email' => 'inbox-patient@example.test', 'password' => 'test-password', 'role_id' => 6]);
        $this->patient = Patient::create(['user_id' => $person->id, 'creator_id' => $this->lab->id, 'code' => 'INBOX-1', 'age' => 25]);
        $this->invoice = Invoice::create(['lab_id_fk' => $this->lab->id, 'patient_id_fk' => $this->patient->id, 'barcode' => 'INV-001']);
        $this->actingAs($this->lab);
    }

    private function delivery(array $overrides = []): DeviceResult
    {
        return DeviceResult::create(array_merge([
            'device_id_fk' => $this->device->id, 'invoice_id_fk' => $this->invoice->id,
            'specimen_barcode' => '00001234', 'status' => 'matched', 'raw_message' => 'Original analyzer payload',
            'parsed_results' => [['test_code' => 'WBC', 'test_name' => 'White blood cells', 'value' => '5.4', 'unit' => '10^9/L']],
            'instrument_metadata' => ['warnings' => ['Review required']],
        ], $overrides));
    }

    public function test_all_statuses_include_patient_barcode_details_without_mutation(): void
    {
        foreach (['pending', 'matched', 'applied', 'failed'] as $status) $this->delivery(['status' => $status]);
        $before = DeviceResult::all()->toArray();
        $response = $this->getJson('/api/device-results')->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('pagination.total', 4)->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.invoice.patient.user.name', 'مريض تجريبي')
            ->assertJsonPath('data.0.specimen_barcode', '00001234')->assertJsonPath('data.0.invoice.barcode', 'INV-001')
            ->assertJsonPath('data.0.parsed_results.0.value', '5.4')->assertJsonPath('data.0.raw_message', 'Original analyzer payload')
            ->assertJsonPath('data.0.instrument_metadata.warnings.0', 'Review required');
        $this->assertArrayNotHasKey('email', $response->json('data.0.invoice.patient.user'));
        $this->assertArrayNotHasKey('api_token', $response->json('data.0.device'));
        $this->assertSame($before, DeviceResult::all()->toArray());
        $this->getJson('/api/device-results?status=pending,matched')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_unlinked_delivery_does_not_guess_patient_even_when_barcode_matches(): void
    {
        $this->delivery(['invoice_id_fk' => null, 'specimen_barcode' => $this->invoice->barcode, 'status' => 'pending']);
        $this->getJson('/api/device-results')->assertOk()->assertJsonPath('data.0.invoice', null)
            ->assertJsonPath('data.0.specimen_barcode', 'INV-001')->assertJsonPath('data.0.invoice_id_fk', null);
    }

    public function test_tenant_filter_protects_linked_patient_and_name_search(): void
    {
        $otherLab = User::create(['name' => 'Other lab', 'email' => 'other-inbox@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $otherPerson = User::create(['name' => 'Private patient', 'email' => 'private-inbox@example.test', 'password' => 'test-password', 'role_id' => 6]);
        $otherPatient = Patient::create(['user_id' => $otherPerson->id, 'creator_id' => $otherLab->id, 'code' => 'OTHER-INBOX']);
        $otherInvoice = Invoice::create(['lab_id_fk' => $otherLab->id, 'patient_id_fk' => $otherPatient->id, 'barcode' => 'PRIVATE-BARCODE']);
        $otherDevice = LabDevice::create(['lab_id_fk' => $otherLab->id, 'name' => 'Other', 'api_token' => str_repeat('b', 64)]);
        $this->delivery(['device_id_fk' => $otherDevice->id, 'invoice_id_fk' => $otherInvoice->id]);
        $this->delivery(['invoice_id_fk' => $otherInvoice->id]); // Historical inconsistent linkage.
        $this->getJson('/api/device-results')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice', null);
        foreach (['Private', 'PRIVATE-BARCODE'] as $search) $this->getJson('/api/device-results?search='.$search)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/device-results?device_id='.$otherDevice->id)->assertOk()->assertJsonCount(0, 'data');
        $this->invoice->update(['patient_id_fk' => $otherPatient->id]);
        $this->delivery();
        $this->getJson('/api/device-results')->assertOk()->assertJsonPath('data.0.invoice.patient', null);
        $this->getJson('/api/device-results?search=Private')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_employee_reads_own_lab_but_cannot_apply_without_update_permission(): void
    {
        $delivery = $this->delivery();
        $employee = User::create(['name' => 'Viewer', 'email' => 'inbox-viewer@example.test', 'password' => 'test-password', 'role_id' => 4, 'creator_id' => $this->lab->id]);
        $this->actingAs($employee)->getJson('/api/device-results')->assertForbidden();
        $employee->givePermissionTo('devices view');
        $this->actingAs($employee->fresh())->getJson('/api/device-results')->assertOk()->assertJsonPath('data.0.invoice.patient.user.name', 'مريض تجريبي');
        $this->postJson('/api/device-results/'.$delivery->id.'/apply')->assertForbidden();
    }

    public function test_administrator_can_monitor_multiple_labs_with_view_permission(): void
    {
        $this->delivery();
        $otherLab = User::create(['name' => 'Second lab', 'email' => 'admin-inbox-lab@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $otherDevice = LabDevice::create(['lab_id_fk' => $otherLab->id, 'name' => 'Second device', 'api_token' => str_repeat('c', 64)]);
        $this->delivery(['device_id_fk' => $otherDevice->id, 'invoice_id_fk' => null]);
        $this->getJson('/api/device-results')->assertOk()->assertJsonCount(1, 'data');
        $admin = User::create(['name' => 'Admin', 'email' => 'inbox-admin@example.test', 'password' => 'test-password', 'role_id' => 1]);
        $admin->givePermissionTo('devices view');
        $this->actingAs($admin)->getJson('/api/device-results')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/device-results?device_id='.$otherDevice->id)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device.name', 'Second device');
    }

    public function test_archive_history_is_readable_and_searchable(): void
    {
        $this->delivery();
        $this->invoice->delete(); $this->patient->delete(); $this->patient->user->delete(); $this->device->delete();
        $response = $this->getJson('/api/device-results?search='.urlencode('تجريبي'))->assertOk()
            ->assertJsonPath('data.0.invoice.patient.user.name', 'مريض تجريبي')->assertJsonPath('data.0.device.name', 'DxH 500');
        $this->assertNotNull($response->json('data.0.invoice.deleted_at'));
        $this->assertNotNull($response->json('data.0.device.deleted_at'));
    }

    public function test_search_device_and_status_filters_combine_with_literal_barcodes(): void
    {
        $this->delivery();
        $this->delivery(['status' => 'failed', 'specimen_barcode' => 'FAIL_01']);
        foreach (['تجريبي', '0000', 'INV-001'] as $search) {
            $this->getJson('/api/device-results?status=matched&device_id='.$this->device->id.'&search='.urlencode($search))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.specimen_barcode', '00001234');
        }
        $this->getJson('/api/device-results?search=_')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.specimen_barcode', 'FAIL_01');
        $this->getJson('/api/device-results?search=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pagination_is_stable_and_rejects_invalid_filters(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 28; $i++) $this->delivery();
        $first = $this->getJson('/api/device-results')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('pagination.last_page', 2);
        $second = $this->getJson('/api/device-results?page=2')->assertOk()->assertJsonCount(3, 'data');
        $ids = array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'));
        $this->assertSame(DeviceResult::orderByDesc('id')->pluck('id')->all(), $ids);
        foreach (['per_page=0', 'per_page=101', 'page=-1', 'status=invalid', 'status[]=pending', 'device_id=abc', 'search[]=abc'] as $filter) {
            $this->getJson('/api/device-results?'.$filter)->assertUnprocessable();
        }
    }
}
