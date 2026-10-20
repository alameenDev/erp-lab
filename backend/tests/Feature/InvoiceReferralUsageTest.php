<?php

namespace Tests\Feature;

use App\Models\{Invoice, Referal, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class InvoiceReferralUsageTest extends TestCase
{
    use DatabaseMigrations;

    private function user(string $name, int $role = 2, ?int $creator = null): User
    {
        return User::create(['name' => $name, 'email' => $name.'@example.test',
            'password' => 'test-password-123', 'role_id' => $role, 'creator_id' => $creator]);
    }

    public function test_counts_use_saved_invoices_from_the_whole_lab_team_and_preserve_referral_metadata(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = $this->user('owner');
        $staff = $this->user('employee', 7, $lab->id);
        $other = $this->user('other-owner');
        $doctor = $this->user('doctor', 5);
        $partner = $this->user('partner');
        $branch = $this->user('branch', 4);
        $hidden = $this->user('hidden', 5);
        $doctorLink = Referal::create(['lab_id_fk' => $lab->id, 'referral_id_fk' => $doctor->id, 'commission' => 1500]);
        Referal::create(['lab_id_fk' => $staff->id, 'referral_id_fk' => $partner->id]);
        Referal::create(['lab_id_fk' => $lab->id, 'referral_id_fk' => $branch->id]);
        Referal::create(['lab_id_fk' => $other->id, 'referral_id_fk' => $doctor->id]);
        Referal::create(['lab_id_fk' => $other->id, 'referral_id_fk' => $hidden->id]);

        $ownerInvoice = Invoice::create(['lab_id_fk' => $lab->id, 'referral_id_fk' => $doctor->id, 'from_lab_id_fk' => $partner->id, 'is_done' => false]);
        $staffInvoice = Invoice::create(['lab_id_fk' => $staff->id, 'referral_id_fk' => $doctor->id, 'from_lab_id_fk' => $branch->id]);
        Invoice::create(['lab_id_fk' => $other->id, 'referral_id_fk' => $doctor->id, 'from_lab_id_fk' => $partner->id]);
        Invoice::create(['lab_id_fk' => $lab->id, 'referral_id_fk' => $doctor->id, 'from_lab_id_fk' => $partner->id])->delete();

        foreach ([$lab, $staff] as $viewer) {
            $viewer->givePermissionTo('referrals view');
            $rows = collect($this->actingAs($viewer)->getJson('/api/referrals')->assertOk()->json())->keyBy('user_id');
            $this->assertCount(3, $rows);
            $this->assertSame(2, $rows[$doctor->id]['invoice_usage_count']);
            $this->assertSame(1, $rows[$partner->id]['invoice_usage_count']);
            $this->assertSame(1, $rows[$branch->id]['invoice_usage_count']);
            $this->assertSame($doctorLink->id, $rows[$doctor->id]['id']);
            $this->assertEquals(1500, $rows[$doctor->id]['commission']);
            $this->assertSame('Doctor', $rows[$doctor->id]['role']);
            $this->assertArrayHasKey('price_list_id_fk', $rows[$doctor->id]);
            $this->assertArrayHasKey('lab_price_list_id_fk', $rows[$partner->id]);
        }

        // A saved change replaces the old usage; deleting removes it. Refreshes add nothing.
        $staffInvoice->update(['referral_id_fk' => null, 'from_lab_id_fk' => $partner->id]);
        $ownerInvoice->delete();
        for ($refresh = 0; $refresh < 2; $refresh++) {
            $rows = collect($this->getJson('/api/referrals')->assertOk()->json())->keyBy('user_id');
            $this->assertSame(0, $rows[$doctor->id]['invoice_usage_count']);
            $this->assertSame(1, $rows[$partner->id]['invoice_usage_count']);
            $this->assertSame(0, $rows[$branch->id]['invoice_usage_count']);
        }
    }

    public function test_permission_is_required_and_unused_referrals_remain_available(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = $this->user('lab');
        $doctor = $this->user('doctor', 5);
        Referal::create(['lab_id_fk' => $lab->id, 'referral_id_fk' => $doctor->id]);
        $this->actingAs($lab)->getJson('/api/referrals')->assertForbidden();
        $lab->givePermissionTo('referrals view');
        $this->getJson('/api/referrals')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.user_id', $doctor->id)->assertJsonPath('0.invoice_usage_count', 0);
    }
}
