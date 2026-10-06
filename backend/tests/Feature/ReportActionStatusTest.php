<?php

namespace Tests\Feature;

use App\Models\{Invoice, Patient, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReportActionStatusTest extends TestCase
{
    use DatabaseMigrations;
    private User $lab;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->lab = User::create(['name' => 'Report lab', 'email' => 'report@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $this->lab->givePermissionTo(['invoices view', 'invoices print', 'invoices send whatsapp']);
        $person = User::create(['name' => 'Synthetic patient', 'email' => 'report-patient@example.test', 'password' => 'test-password', 'role_id' => 6]);
        $patient = Patient::create(['user_id' => $person->id, 'creator_id' => $this->lab->id, 'code' => 'REPORT-1']);
        $this->invoice = Invoice::create(['lab_id_fk' => $this->lab->id, 'patient_id_fk' => $patient->id, 'barcode' => 'REPORT-1', 'total' => 0, 'result_date' => '2026-10-01']);
        $this->actingAs($this->lab);
    }

    private function action(string $action)
    {
        return $this->postJson('/api/invoices/report-action', ['id' => $this->invoice->id, 'action' => $action, 'with_background' => false]);
    }

    public function test_actions_accumulate_and_survive_list_detail_reload_without_marking_print_as_sent(): void
    {
        $this->action('print')->assertOk()->assertJsonPath('report_status.printed', true)->assertJsonPath('report_status.saved', false)->assertJsonPath('sent_to_patient', false);
        $printedAt = $this->invoice->fresh()->report_printed_at->toISOString();
        $this->action('download')->assertOk()->assertJsonPath('report_status.printed', true)->assertJsonPath('report_status.saved', true)->assertJsonPath('report_status.sent', false);
        $this->action('whatsapp')->assertOk()->assertJsonPath('report_status.sent', true)->assertJsonPath('public_with_background', false);
        $this->action('download')->assertOk()->assertJsonPath('report_status.sent', true)->assertJsonPath('report_status.printed_at', $printedAt);
        $this->getJson('/api/invoices')->assertOk()->assertJsonPath('data.0.report_status.printed', true)->assertJsonPath('data.0.report_status.saved', true)->assertJsonPath('stats.sent', 1);
        $this->getJson('/api/invoices/'.$this->invoice->id)->assertOk()->assertJsonPath('report_status.sent', true)->assertJsonPath('report_status.saved', true);
        $this->assertSame('2026-10-01', $this->invoice->fresh()->result_date->toDateString());
        $log = Activity::where('audit_invoice_id', $this->invoice->id)->where('description', 'طباعة التقرير الطبي')->firstOrFail();
        $change = collect($log->properties['changes'])->firstWhere('field', 'invoice.report_printed_at');
        $this->assertNull($change['before']);
        $this->assertNotNull($change['after']);
    }

    public function test_combined_actions_record_only_the_selected_outputs(): void
    {
        $this->action('print-download')->assertOk()->assertJsonPath('report_status.printed', true)->assertJsonPath('report_status.saved', true)->assertJsonPath('report_status.sent', false);
        $this->invoice->report_printed_at = null;
        $this->invoice->report_saved_at = null;
        $this->invoice->save();
        $this->action('whatsapp-download')->assertOk()->assertJsonPath('report_status.printed', false)->assertJsonPath('report_status.saved', true)->assertJsonPath('report_status.sent', true);
    }

    public function test_legacy_sent_status_is_preserved_without_inventing_other_actions_or_dates(): void
    {
        $this->invoice->update(['sent_to_patient' => true, 'is_printed' => true]);
        $this->getJson('/api/invoices')->assertOk()->assertJsonPath('data.0.report_status.sent', true)->assertJsonPath('data.0.report_status.sent_at', null)->assertJsonPath('data.0.report_status.printed', false);
        $this->postJson('/api/invoices/send', ['id' => $this->invoice->id, 'with_background' => false])->assertOk()->assertJsonPath('report_status.sent', true)->assertJsonPath('report_status.saved', false);
        $this->assertNotNull($this->invoice->fresh()->report_sent_at);
    }

    public function test_permissions_tenant_and_invalid_actions_cannot_change_status(): void
    {
        $this->lab->revokePermissionTo('invoices send whatsapp');
        $this->action('whatsapp')->assertForbidden();
        $this->action('whatsapp-download')->assertForbidden();
        $this->action('download')->assertOk();
        $this->lab->revokePermissionTo('invoices print');
        $this->action('print')->assertForbidden();
        $this->lab->givePermissionTo(['invoices print', 'invoices send whatsapp']);
        $this->action('preview')->assertUnprocessable();
        $this->postJson('/api/invoices/send', ['id' => $this->invoice->id, 'action' => 'print'])->assertUnprocessable();
        $other = User::create(['name' => 'Other lab', 'email' => 'other-report@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $other->givePermissionTo(['invoices print', 'invoices send whatsapp']);
        $this->actingAs($other);
        $this->action('print')->assertNotFound();
        $this->action('whatsapp')->assertNotFound();
        $this->assertNull($this->invoice->fresh()->report_printed_at);
        $this->assertNull($this->invoice->fresh()->report_sent_at);
        $this->invoice->delete();
        $this->actingAs($this->lab);
        $this->action('download')->assertNotFound();
    }
}
