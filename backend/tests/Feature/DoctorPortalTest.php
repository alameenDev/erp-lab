<?php

namespace Tests\Feature;

use App\Models\{Invoice, InvoiceTestRel, Patient, Referal, Test as LabTest, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class DoctorPortalTest extends TestCase
{
    use DatabaseMigrations;

    public function test_doctor_sees_only_own_referrals_and_only_released_values(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name' => 'Lab', 'email' => 'lab@example.test', 'password' => 'secret-password-123', 'role_id' => 2]);
        $doctor = User::create(['name' => 'Doctor', 'email' => 'doctor@example.test', 'password' => 'secret-password-123', 'role_id' => 5, 'creator_id' => $lab->id]);
        $doctor->referral_portal_only = true;
        $doctor->save();
        $other = User::create(['name' => 'Other doctor', 'email' => 'other-doctor@example.test', 'password' => 'secret-password-123', 'role_id' => 5, 'creator_id' => $lab->id]);
        foreach ([$doctor, $other] as $user) Referal::create(['referral_id_fk' => $user->id, 'lab_id_fk' => $lab->id]);
        $patientUser = User::create(['name' => 'Shared patient', 'email' => 'patient@example.test', 'password' => 'secret-password-123', 'role_id' => 6]);
        $patient = Patient::create(['user_id' => $patientUser->id, 'creator_id' => $lab->id, 'code' => 'P-1']);
        $otherPatientUser = User::create(['name' => 'Private patient', 'email' => 'private@example.test', 'password' => 'secret-password-123', 'role_id' => 6]);
        $private = Patient::create(['user_id' => $otherPatientUser->id, 'creator_id' => $lab->id, 'code' => 'P-2']);
        $test = LabTest::create(['name' => 'Vitamin D', 'unit' => 'ng/mL', 'lab_id_fk' => $lab->id]);
        $own = Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id, 'referral_id_fk' => $doctor->id, 'is_done' => false, 'total' => 5000]);
        InvoiceTestRel::create(['invoice_id_fk' => $own->id, 'test_id_fk' => $test->id, 'result' => '19', 'is_done' => true]);
        Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $patient->id, 'referral_id_fk' => $other->id, 'is_done' => true, 'total' => 9000]);
        Invoice::create(['lab_id_fk' => $lab->id, 'patient_id_fk' => $private->id, 'referral_id_fk' => $other->id, 'is_done' => true]);

        $this->actingAs($doctor);
        $this->getJson('/api/doctor-portal/patients')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $patient->id);
        $this->getJson('/api/doctor-portal/patients/'.$private->id)->assertNotFound();
        $this->getJson('/api/doctor-portal/patients/'.$patient->id)->assertOk()
            ->assertJsonPath('invoices.total', 1)
            ->assertJsonPath('invoices.data.0.groups.0.rows.0.result', null)
            ->assertJsonMissingPath('invoices.data.0.total');

        $own->update(['is_done' => true]);
        $this->getJson('/api/doctor-portal/patients/'.$patient->id)->assertOk()
            ->assertJsonPath('invoices.data.0.groups.0.rows.0.result', '19');
        $this->getJson('/api/referral-portal/financial-report')->assertForbidden();
        $this->getJson('/api/invoices')->assertForbidden();
    }
}
