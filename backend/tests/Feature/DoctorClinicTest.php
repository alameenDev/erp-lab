<?php

namespace Tests\Feature;

use App\Models\DoctorClinicVisit;
use App\Models\Referal;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class DoctorClinicTest extends TestCase
{
    use DatabaseMigrations;

    private User $doctor;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name' => 'Test lab', 'email' => 'cliniclab@example.test', 'password' => 'test-only', 'role_id' => 2]);
        $this->doctor = User::create(['name' => 'Clinic doctor', 'email' => 'clinicdoctor@example.test',
            'password' => 'test-only', 'role_id' => 5, 'creator_id' => $lab->id]);
        $this->other = User::create(['name' => 'Other clinic doctor', 'email' => 'otherclinicdoctor@example.test',
            'password' => 'test-only', 'role_id' => 5, 'creator_id' => $lab->id]);
        $this->doctor->referral_portal_only = true;
        $this->doctor->save();
        $this->other->referral_portal_only = true;
        $this->other->save();
        Referal::create(['referral_id_fk' => $this->doctor->id, 'lab_id_fk' => $lab->id]);
        Referal::create(['referral_id_fk' => $this->other->id, 'lab_id_fk' => $lab->id]);
        $this->actingAs($this->doctor);
    }

    private function patient(): int
    {
        return $this->postJson('/api/doctor-portal/clinic/patients', [
            'name' => 'Synthetic clinic patient', 'age_value' => 34, 'age_unit' => 'years',
            'phone' => '07801234567', 'gender' => 'unknown',
        ])->assertCreated()->json('id');
    }

    private function visit(int $patientId, string $date = '2026-11-01'): int
    {
        return $this->postJson('/api/doctor-portal/clinic/visits', [
            'clinic_patient_id_fk' => $patientId, 'visit_date' => $date,
        ])->assertCreated()->json('id');
    }

    public function test_register_patient_daily_queue_and_persist_medical_visit(): void
    {
        $patientId = $this->patient();
        $first = $this->visit($patientId);
        $second = $this->visit($patientId);
        $third = $this->visit($patientId, '2026-11-02');
        $this->assertSame(1, DoctorClinicVisit::findOrFail($first)->queue_number);
        $this->assertSame(2, DoctorClinicVisit::findOrFail($second)->queue_number);
        $this->assertSame(1, DoctorClinicVisit::findOrFail($third)->queue_number);
        $this->getJson('/api/doctor-portal/clinic/visits?date=2026-11-01')
            ->assertOk()->assertJsonCount(2, 'visits')
            ->assertJsonPath('stats.waiting', 2)
            ->assertJsonPath('visits.0.patient.name', 'Synthetic clinic patient');

        $record = [
            'status' => 'completed',
            'chief_complaint' => 'Headache',
            'symptoms' => ['صداع', 'دوخة'],
            'history_of_present_illness' => 'Symptoms for two days',
            'allergies' => 'Unknown — needs review',
            'vitals' => ['blood_pressure' => '120/80', 'heart_rate' => '80'],
            'assessment' => 'Clinical assessment entered by doctor',
            'lab_requests' => [['name' => 'Synthetic lab test', 'notes' => 'Fasting status specified by clinician']],
            'imaging_requests' => [['modality' => 'MRI', 'body_part' => 'Head', 'notes' => 'Clinical indication']],
            'prescriptions' => [[
                'generic_name' => 'Synthetic medication name', 'strength' => 'test strength',
                'form' => 'tablet', 'dose' => 'test dose', 'route' => 'oral',
                'frequency' => 'once daily', 'duration' => 'test duration',
                'quantity' => 'test quantity', 'instructions' => 'sample instructions',
            ]],
        ];
        $this->putJson('/api/doctor-portal/clinic/visits/'.$first, $record)
            ->assertOk()->assertJsonPath('status', 'completed')
            ->assertJsonPath('prescriptions.0.generic_name', 'Synthetic medication name')
            ->assertJsonPath('lab_requests.0.name', 'Synthetic lab test')
            ->assertJsonPath('imaging_requests.0.modality', 'MRI')
            ->assertJsonPath('age_at_visit', 34);
        $this->assertNotNull(DoctorClinicVisit::findOrFail($first)->completed_at);
        $this->putJson('/api/doctor-portal/clinic/visits/'.$first, ['assessment' => 'Documented amendment'])
            ->assertOk()->assertJsonPath('assessment', 'Documented amendment');
        $this->getJson('/api/doctor-portal/clinic/visits/'.$first.'/revisions')
            ->assertOk()->assertJsonCount(2);
        $this->assertSame(34, DoctorClinicVisit::findOrFail($first)->age_at_visit);
    }

    public function test_prescription_must_be_complete_before_visit_completion(): void
    {
        $id = $this->visit($this->patient());
        $this->putJson('/api/doctor-portal/clinic/visits/'.$id, [
            'status' => 'completed', 'assessment' => 'Clinical assessment',
            'prescriptions' => [['generic_name' => 'Synthetic name', 'frequency' => 'PRN']],
        ])->assertUnprocessable();
        $this->assertSame('waiting', DoctorClinicVisit::findOrFail($id)->status);

        $this->putJson('/api/doctor-portal/clinic/visits/'.$id, [
            'status' => 'completed', 'assessment' => 'Clinical assessment',
            'prescriptions' => [[
                'generic_name' => 'Synthetic name','strength' => 'test strength','form' => 'tablet',
                'dose' => 'test dose','route' => 'oral','frequency' => 'PRN',
                'duration' => 'test duration','quantity' => 'test quantity',
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('prescriptions.0.prn_indication');

        $this->putJson('/api/doctor-portal/clinic/visits/'.$id, ['status' => 'completed'])
            ->assertUnprocessable()->assertJsonValidationErrors('assessment');
    }

    public function test_other_doctors_and_lab_accounts_cannot_access_or_modify_clinic_data(): void
    {
        $patientId = $this->patient();
        $visitId = $this->visit($patientId);
        $this->actingAs($this->other);
        $this->getJson('/api/doctor-portal/clinic/patients/'.$patientId)->assertNotFound();
        $this->getJson('/api/doctor-portal/clinic/visits/'.$visitId)->assertNotFound();
        $this->putJson('/api/doctor-portal/clinic/visits/'.$visitId, ['assessment' => 'Unauthorized'])->assertNotFound();
        $this->postJson('/api/doctor-portal/clinic/visits', [
            'clinic_patient_id_fk' => $patientId, 'visit_date' => '2026-11-01',
        ])->assertNotFound();
        $this->getJson('/api/doctor-portal/clinic/visits?date=2026-11-01')->assertOk()->assertJsonCount(0, 'visits');
        $lab = User::where('role_id', 2)->firstOrFail();
        $this->actingAs($lab);
        $this->getJson('/api/doctor-portal/clinic/patients')->assertForbidden();
        $this->postJson('/api/doctor-portal/clinic/patients', [
            'name' => 'Not allowed', 'age_value' => 20, 'age_unit' => 'years', 'phone' => '07801234567',
        ])->assertForbidden();
    }

    public function test_clinic_input_validation_and_cancelled_visit_protection(): void
    {
        $this->postJson('/api/doctor-portal/clinic/patients', [
            'name' => 'X', 'age_value' => 999, 'age_unit' => 'years', 'phone' => 'not phone',
        ])->assertUnprocessable();
        $id = $this->visit($this->patient());
        $this->putJson('/api/doctor-portal/clinic/visits/'.$id, ['status' => 'cancelled'])->assertOk();
        $this->putJson('/api/doctor-portal/clinic/visits/'.$id, ['status' => 'waiting'])->assertUnprocessable();
    }
}
