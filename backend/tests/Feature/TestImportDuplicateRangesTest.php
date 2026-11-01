<?php

namespace Tests\Feature;

use App\Models\{Category, Sample, Test as LabTest, TestReferenceRange, User};
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TestImportDuplicateRangesTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $lab = User::create(['name' => 'Import lab', 'email' => 'import-ranges@example.test',
            'password' => 'test-password', 'role_id' => 2]);
        $lab->assignRole('Lab');
        $this->actingAs($lab);
        Category::create(['lab_id_fk' => $lab->id, 'name' => 'Hematology']);
        Sample::create(['lab_id_fk' => $lab->id, 'sample_name' => 'Blood']);
    }

    private function row(string $name, string $shortcut, string $gender, int $ageFrom, int $ageTo,
        string $normalFrom, string $normalTo, array $changes = []): array
    {
        return array_replace([
            $name, $shortcut, $name, 'Hematology', 'Blood', 'Number', 'g/dL',
            '10000', '', '', '', '', '', $gender, 'Years', (string) $ageFrom,
            (string) $ageTo, $normalFrom, $normalTo, '',
        ], $changes);
    }

    private function upload(array $data): \Illuminate\Testing\TestResponse
    {
        $headers = [
            'name', 'shortcut', 'report_name', 'category', 'sample', 'result_type', 'unit', 'price',
            'for_customer_price', 'order', 'test_duration', 'duration_unit', 'interface_code',
            'gender', 'age_unit', 'age_from', 'age_to', 'range_from', 'range_to', 'range_notes',
        ];
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray(array_merge([$headers], $data));
        $temp = tempnam(sys_get_temp_dir(), 'dl-tests-');
        $filename = $temp.'.xlsx';
        rename($temp, $filename);
        (new Xlsx($book))->save($filename);
        $book->disconnectWorksheets();
        try {
            return $this->post('/api/tests/import?locale=en', [
                'file' => new UploadedFile($filename, 'tests.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ], ['Accept' => 'application/json']);
        } finally {
            @unlink($filename);
        }
    }

    public function test_repeated_name_merges_only_distinct_gender_and_age_ranges(): void
    {
        $male = $this->row('Hemoglobin', 'HGB', 'Male', 18, 90, '13', '17');
        $female = $this->row('Hemoglobin', '', 'Female', 18, 90, '12', '15');
        $child = $this->row('Hemoglobin', 'HGB', 'Male', 0, 17, '11', '15');
        $badUnit = $this->row('Hemoglobin', 'HGB', 'Female', 18, 90, '12', '15', [6 => 'mmol/L']);
        $overlap = $this->row('Hemoglobin', 'HGB', 'Male', 18, 90, '14', '16');

        $result = $this->upload([$male, $female, $child, $female, $badUnit, $overlap]);
        $result->assertOk()->assertJsonPath('created', 1)->assertJsonPath('ranges_added', 3)
            ->assertJsonPath('merged', 2)->assertJsonPath('duplicate_ranges', 1)
            ->assertJsonPath('skipped', 3);
        $this->assertCount(2, $result->json('errors'));
        $this->assertDatabaseCount('tests', 1);
        $this->assertDatabaseCount('test_reference_range', 3);
        $test = LabTest::firstOrFail();
        $this->assertEqualsCanonicalizing(['Male', 'Female', 'Male'],
            TestReferenceRange::where('test_id', $test->id)->with('gender')->get()
                ->map(fn ($r) => $r->gender->gender_type)->toArray());
    }

    public function test_same_name_with_different_shortcut_and_reused_shortcut_are_not_merged(): void
    {
        $rows = [
            $this->row('Vitamin D', 'VD', 'Both', 0, 99, '20', '70'),
            $this->row('Vitamin D', 'VD2', 'Female', 0, 99, '25', '60'),
            $this->row('Vitamin B12', 'VD', 'Both', 0, 99, '200', '900'),
        ];
        $result = $this->upload($rows);
        $result->assertOk()->assertJsonPath('created', 1)->assertJsonPath('skipped', 2);
        $this->assertDatabaseCount('tests', 1);
        $this->assertDatabaseCount('test_reference_range', 1);
    }

    public function test_existing_test_gets_only_new_ranges_without_overwriting_catalog(): void
    {
        $male = $this->row('HGB', 'HGB', 'Male', 18, 90, '13', '17');
        $female = $this->row('HGB', 'HGB', 'Female', 18, 90, '12', '15');

        $this->upload([$male])->assertOk()->assertJsonPath('created', 1);
        $result = $this->upload([$male, $female]);
        $result->assertOk()->assertJsonPath('created', 0)
            ->assertJsonPath('ranges_added', 1)->assertJsonPath('merged', 1)
            ->assertJsonPath('duplicate_ranges', 1);
        $this->assertDatabaseCount('tests', 1);
        $this->assertDatabaseCount('test_reference_range', 2);
        $test = LabTest::firstOrFail();
        $this->assertSame('HGB', $test->shortcut);
        $this->assertSame('g/dL', $test->unit);
        $this->assertSame(10000, (int) $test->price);
    }

    public function test_age_and_gender_generic_overlap_is_rejected_to_protect_patient_normals(): void
    {
        $general = $this->row('Glucose', 'GLU', 'Both', 0, 99, '70', '110');
        $male = $this->row('Glucose', 'GLU', 'Male', 18, 50, '80', '120');
        $res = $this->upload([$general, $male]);
        $res->assertOk()->assertJsonPath('created', 1)->assertJsonPath('skipped', 1);
        $this->assertDatabaseCount('test_reference_range', 1);
        $this->assertStringContainsString('overlaps', $res->json('errors.0'));
    }
}
