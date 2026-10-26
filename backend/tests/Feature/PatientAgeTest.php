<?php

namespace Tests\Feature;

use App\Models\{AgeUnit, Patient, User};
use App\Services\PatientAge;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PatientAgeTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00:00', 'Asia/Baghdad'));
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name' => 'Age entry lab', 'email' => 'age-lab@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $lab->assignRole('Lab');
        $this->actingAs($lab);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'Age test patient', 'title_id_fk' => 1, 'gender_id_fk' => 1], $extra);
    }

    public function test_exact_birth_date_is_authoritative_and_survives_patient_and_invoice_lookup(): void
    {
        $response = $this->postJson('/api/patients/create', $this->payload(['dob' => '2000-10-27', 'dob_is_estimated' => false, 'age' => 99, 'age_unit_id_fk' => 1]))
            ->assertCreated()->assertJsonPath('age', 25)->assertJsonPath('dob', '2000-10-27')->assertJsonPath('dob_is_estimated', false);
        $this->assertDatabaseHas('patients', ['id' => $response->json('id'), 'age' => 25, 'dob' => '2000-10-27', 'dob_is_estimated' => false]);
        $this->getJson('/api/patients')->assertOk()->assertJsonPath('0.dob', '2000-10-27');
        $this->postJson('/api/patients/search-name', ['name' => 'Age test'])->assertOk()->assertJsonPath('0.dob', '2000-10-27')->assertJsonPath('0.dob_is_estimated', false);
    }

    public function test_date_only_entry_handles_infants_and_newborn_zero(): void
    {
        foreach ([['2026-06-15', 4, 'Months'], ['2026-10-20', 6, 'Days'], ['2026-10-26', 0, 'Days']] as [$dob, $age, $unit]) {
            $this->postJson('/api/patients/create', $this->payload(['dob' => $dob, 'dob_is_estimated' => false]))
                ->assertCreated()->assertJsonPath('age', $age)->assertJsonPath('age_unit_id_fk', AgeUnit::where('unit_name', $unit)->value('id'));
        }
    }

    public function test_age_entry_marks_estimate_and_changing_to_exact_date_clears_it(): void
    {
        $response = $this->postJson('/api/patients/create', $this->payload(['age' => 30, 'age_unit_id_fk' => 1, 'dob_is_estimated' => true]))
            ->assertCreated()->assertJsonPath('dob', '1996-10-26')->assertJsonPath('dob_is_estimated', true);
        $this->postJson('/api/patients/update', $this->payload(['id' => $response->json('id'), 'dob' => '1996-12-15', 'dob_is_estimated' => false, 'age' => 30, 'age_unit_id_fk' => 1]))
            ->assertOk()->assertJsonPath('patient.age', 29)->assertJsonPath('patient.dob', '1996-12-15')->assertJsonPath('patient.dob_is_estimated', false);
    }

    public function test_saved_estimate_is_not_moved_by_an_unrelated_edit(): void
    {
        $response = $this->postJson('/api/patients/create', $this->payload(['age' => 6, 'age_unit_id_fk' => 2, 'dob_is_estimated' => true]))->assertCreated();
        $this->travel(2)->days();
        $this->postJson('/api/patients/update', $this->payload(['id' => $response->json('id'), 'name' => 'Renamed patient', 'age' => 6, 'age_unit_id_fk' => 2, 'dob' => '2026-04-26', 'dob_is_estimated' => true]))
            ->assertOk()->assertJsonPath('patient.dob', '2026-04-26')->assertJsonPath('patient.dob_is_estimated', true);
    }

    public function test_legacy_age_only_clients_remain_compatible(): void
    {
        $this->postJson('/api/patients/create', $this->payload(['age' => 5, 'age_unit_id_fk' => 3]))
            ->assertCreated()->assertJsonPath('age', 5)->assertJsonPath('dob', null)->assertJsonPath('dob_is_estimated', false);
    }

    public function test_future_invalid_dates_and_invalid_ages_are_rejected_before_creating_patient(): void
    {
        foreach (['2026-10-27', '2026-02-29', 'not-a-date'] as $dob) {
            $this->postJson('/api/patients/create', $this->payload(['dob' => $dob, 'dob_is_estimated' => false]))->assertUnprocessable();
        }
        foreach ([-1, 1.5, 50000] as $age) {
            $this->postJson('/api/patients/create', $this->payload(['age' => $age, 'age_unit_id_fk' => 1, 'dob_is_estimated' => true]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseMissing('users', ['name' => 'Age test patient']);
    }

    public function test_calendar_boundaries_match_frontend_and_use_baghdad_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2025-02-28 12:00', 'Asia/Baghdad'));
        $result = app(PatientAge::class)->normalize(['dob' => '2024-02-29', 'dob_is_estimated' => false]);
        $this->assertSame(1, $result['age']);
        $this->assertEquals(1, $result['age_unit_id_fk']);
        $this->travelTo(CarbonImmutable::parse('2026-03-31 12:00', 'Asia/Baghdad'));
        $this->assertSame('2026-02-28', app(PatientAge::class)->normalize(['age' => 1, 'age_unit_id_fk' => 2, 'dob_is_estimated' => true])['dob']);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 22:00', 'UTC'));
        $this->postJson('/api/patients/create', $this->payload(['dob' => '2026-10-26', 'dob_is_estimated' => false]))->assertCreated()->assertJsonPath('age', 0);
    }
}
