<?php

namespace Tests\Feature;

use App\Models\{Patient, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PatientPhoneTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name' => 'Phone test lab', 'email' => 'phone-lab@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $lab->assignRole('Lab');
        $this->actingAs($lab);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'Phone test patient', 'title_id_fk' => 1, 'gender_id_fk' => 1, 'age' => 30, 'age_unit_id_fk' => 1], $extra);
    }

    public function test_patient_phone_can_be_preserved_changed_and_cleared(): void
    {
        $id = $this->postJson('/api/patients/create', $this->payload(['phone_number' => '+9647701234567']))
            ->assertCreated()->assertJsonPath('phone_number', '+9647701234567')->json('id');
        $this->postJson('/api/patients/update', $this->payload(['id' => $id, 'name' => 'Renamed patient', 'phone_number' => '+9647701234567']))
            ->assertOk()->assertJsonPath('patient.name', 'Renamed patient')->assertJsonPath('patient.phone_number', '+9647701234567');
        $this->postJson('/api/patients/update', $this->payload(['id' => $id]))
            ->assertOk()->assertJsonPath('patient.phone_number', '+9647701234567');
        foreach (['+9647807654321', '', '+9647701234567', null] as $phone) {
            $expected = $phone === '' ? null : $phone;
            $this->postJson('/api/patients/update', $this->payload(['id' => $id, 'phone_number' => $phone]))
                ->assertOk()->assertJsonPath('patient.phone_number', $expected);
            $this->assertDatabaseHas('users', ['id' => Patient::findOrFail($id)->user_id, 'phone_number' => $expected]);
            $this->getJson('/api/patients')->assertOk()->assertJsonPath('0.phone_number', $expected);
        }
    }

    public function test_invalid_phone_cannot_change_the_patient(): void
    {
        $id = $this->postJson('/api/patients/create', $this->payload(['phone_number' => '+9647701234567']))->assertCreated()->json('id');
        foreach (['+964770123', '+96477012345678', '+971501234567', 'letters'] as $phone) {
            $this->postJson('/api/patients/update', $this->payload(['id' => $id, 'name' => 'Must not save', 'phone_number' => $phone]))
                ->assertUnprocessable()->assertJsonValidationErrors('phone_number');
        }
        $this->assertDatabaseHas('users', ['id' => Patient::findOrFail($id)->user_id, 'name' => 'Phone test patient', 'phone_number' => '+9647701234567']);
    }

    public function test_another_lab_cannot_edit_or_clear_the_phone(): void
    {
        $id = $this->postJson('/api/patients/create', $this->payload(['phone_number' => '+9647701234567']))->assertCreated()->json('id');
        $other = User::create(['name' => 'Other lab', 'email' => 'other-phone@example.test', 'password' => 'test-password', 'role_id' => 2]);
        $other->assignRole('Lab');
        $this->actingAs($other)->postJson('/api/patients/update', $this->payload(['id' => $id, 'phone_number' => null]))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => Patient::findOrFail($id)->user_id, 'phone_number' => '+9647701234567']);
    }
}
