<?php

namespace Database\Seeders;

use App\Models\Antibiotics;
use App\Models\Category;
use App\Models\Culture;
use App\Models\Package;
use App\Models\PatientQuestion;
use App\Models\PriceList;
use App\Models\Sample;
use App\Models\Test;
use App\Models\TestGroup;
use App\Models\TestReferenceRange;
use App\Models\User;
use Illuminate\Database\Seeder;

class LabEntitiesSeeder extends Seeder
{
    public function run(): void
    {
        $labOwner = User::where('email', 'lab@medicallab.com')->first();

        if (!$labOwner) {
            $this->command->error('Lab owner not found. Run UsersSeeder first.');
            return;
        }

        $labId = $labOwner->id;

        // Create Categories
        $categories = $this->createCategories($labId);

        // Create Samples
        $samples = $this->createSamples($labId);

        // Create Test Groups and Tests
        $this->createTestGroupsAndTests($labId, $categories, $samples);

        // Create Cultures
        $this->createCultures($labId, $categories, $samples);

        // Create Packages
        $this->createPackages($labId);

        // Create Price Lists
        $this->createPriceLists($labId);

        // Create Antibiotics
        $this->createAntibiotics($labId);

        // Create Patient Questions
        $this->createPatientQuestions($labId);
    }

    private function createCategories(int $labId): array
    {
        $categoryData = [
            'Hematology',
            'Clinical Chemistry',
            'Immunology',
            'Microbiology',
            'Urinalysis',
            'Hormones',
            'Tumor Markers',
            'Coagulation',
            'Serology',
            'Lipid Profile',
            'Liver Function',
            'Kidney Function',
            'Thyroid Function',
            'Diabetes Panel',
            'Cardiac Markers',
        ];

        $categories = [];
        foreach ($categoryData as $name) {
            $categories[$name] = Category::firstOrCreate(
                ['lab_id_fk' => $labId, 'name' => $name]
            );
        }

        return $categories;
    }

    private function createSamples(int $labId): array
    {
        $sampleData = [
            'Whole Blood (EDTA)',
            'Serum',
            'Plasma',
            'Urine (Random)',
            'Urine (24-hour)',
            'Stool',
            'Sputum',
            'CSF',
            'Synovial Fluid',
            'Swab',
            'Tissue',
            'Whole Blood (Citrate)',
            'Capillary Blood',
        ];

        $samples = [];
        foreach ($sampleData as $name) {
            $samples[$name] = Sample::firstOrCreate(
                ['lab_id_fk' => $labId, 'sample_name' => $name]
            );
        }

        return $samples;
    }

    private function createTestGroupsAndTests(int $labId, array $categories, array $samples): void
    {
        // Complete Blood Count (CBC)
        $cbcGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Complete Blood Count (CBC)'],
            [
                'shortcut' => 'CBC',
                'category_id_fk' => $categories['Hematology']->id,
                'sample_id_fk' => $samples['Whole Blood (EDTA)']->id,
                'original_price' => 2500,
                'for_customer_price' => 3500,
                'test_duration' => 2,
                'duration_unit_id_fk' => 2, // Hours
                'precautions' => 'Fasting not required. Avoid strenuous exercise before test.',
            ]
        );

        $cbcTests = [
            ['name' => 'Hemoglobin (Hb)', 'shortcut' => 'HB', 'unit' => 'g/dL', 'price' => 500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '13.5', 'to' => '17.5'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '12.0', 'to' => '16.0'],
            ]],
            ['name' => 'Red Blood Cells (RBC)', 'shortcut' => 'RBC', 'unit' => 'million/µL', 'price' => 400, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '4.5', 'to' => '5.5'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '4.0', 'to' => '5.0'],
            ]],
            ['name' => 'White Blood Cells (WBC)', 'shortcut' => 'WBC', 'unit' => 'cells/µL', 'price' => 400, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '4000', 'to' => '11000'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '4000', 'to' => '11000'],
            ]],
            ['name' => 'Platelets (PLT)', 'shortcut' => 'PLT', 'unit' => 'cells/µL', 'price' => 400, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '150000', 'to' => '400000'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '150000', 'to' => '400000'],
            ]],
            ['name' => 'Hematocrit (HCT)', 'shortcut' => 'HCT', 'unit' => '%', 'price' => 300, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '38.8', 'to' => '50.0'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '34.9', 'to' => '44.5'],
            ]],
            ['name' => 'MCV', 'shortcut' => 'MCV', 'unit' => 'fL', 'price' => 300, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '80', 'to' => '100'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '80', 'to' => '100'],
            ]],
            ['name' => 'MCH', 'shortcut' => 'MCH', 'unit' => 'pg', 'price' => 300, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '27', 'to' => '33'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '27', 'to' => '33'],
            ]],
            ['name' => 'MCHC', 'shortcut' => 'MCHC', 'unit' => 'g/dL', 'price' => 300, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '32', 'to' => '36'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '32', 'to' => '36'],
            ]],
        ];

        $this->createTestsForGroup($labId, $cbcGroup, $cbcTests, $categories['Hematology'], $samples['Whole Blood (EDTA)']);

        // Lipid Profile
        $lipidGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Lipid Profile'],
            [
                'shortcut' => 'LIPID',
                'category_id_fk' => $categories['Lipid Profile']->id,
                'sample_id_fk' => $samples['Serum']->id,
                'original_price' => 3000,
                'for_customer_price' => 4500,
                'test_duration' => 4,
                'duration_unit_id_fk' => 2,
                'precautions' => 'Fasting for 12-14 hours required. Water is allowed.',
            ]
        );

        $lipidTests = [
            ['name' => 'Total Cholesterol', 'shortcut' => 'CHOL', 'unit' => 'mg/dL', 'price' => 800, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '200', 'notes' => 'Desirable: <200, Borderline: 200-239, High: ≥240'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '200', 'notes' => 'Desirable: <200, Borderline: 200-239, High: ≥240'],
            ]],
            ['name' => 'Triglycerides', 'shortcut' => 'TG', 'unit' => 'mg/dL', 'price' => 700, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '150'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '150'],
            ]],
            ['name' => 'HDL Cholesterol', 'shortcut' => 'HDL', 'unit' => 'mg/dL', 'price' => 800, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '40', 'to' => '60', 'notes' => 'Higher is better'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '50', 'to' => '60', 'notes' => 'Higher is better'],
            ]],
            ['name' => 'LDL Cholesterol', 'shortcut' => 'LDL', 'unit' => 'mg/dL', 'price' => 800, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '100', 'notes' => 'Optimal: <100, Near optimal: 100-129'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '100', 'notes' => 'Optimal: <100, Near optimal: 100-129'],
            ]],
            ['name' => 'VLDL Cholesterol', 'shortcut' => 'VLDL', 'unit' => 'mg/dL', 'price' => 600, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '5', 'to' => '40'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '5', 'to' => '40'],
            ]],
        ];

        $this->createTestsForGroup($labId, $lipidGroup, $lipidTests, $categories['Lipid Profile'], $samples['Serum']);

        // Liver Function Tests
        $lftGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Liver Function Tests (LFT)'],
            [
                'shortcut' => 'LFT',
                'category_id_fk' => $categories['Liver Function']->id,
                'sample_id_fk' => $samples['Serum']->id,
                'original_price' => 3500,
                'for_customer_price' => 5000,
                'test_duration' => 4,
                'duration_unit_id_fk' => 2,
                'precautions' => 'Fasting for 8-12 hours recommended.',
            ]
        );

        $lftTests = [
            ['name' => 'ALT (SGPT)', 'shortcut' => 'ALT', 'unit' => 'U/L', 'price' => 600, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '7', 'to' => '56'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '7', 'to' => '45'],
            ]],
            ['name' => 'AST (SGOT)', 'shortcut' => 'AST', 'unit' => 'U/L', 'price' => 600, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '10', 'to' => '40'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '9', 'to' => '32'],
            ]],
            ['name' => 'Alkaline Phosphatase (ALP)', 'shortcut' => 'ALP', 'unit' => 'U/L', 'price' => 600, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '44', 'to' => '147'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '44', 'to' => '147'],
            ]],
            ['name' => 'Total Bilirubin', 'shortcut' => 'TBIL', 'unit' => 'mg/dL', 'price' => 500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.1', 'to' => '1.2'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.1', 'to' => '1.2'],
            ]],
            ['name' => 'Direct Bilirubin', 'shortcut' => 'DBIL', 'unit' => 'mg/dL', 'price' => 500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '0.3'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '0.3'],
            ]],
            ['name' => 'Total Protein', 'shortcut' => 'TP', 'unit' => 'g/dL', 'price' => 500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '6.0', 'to' => '8.3'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '6.0', 'to' => '8.3'],
            ]],
            ['name' => 'Albumin', 'shortcut' => 'ALB', 'unit' => 'g/dL', 'price' => 500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '3.5', 'to' => '5.0'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '3.5', 'to' => '5.0'],
            ]],
            ['name' => 'GGT', 'shortcut' => 'GGT', 'unit' => 'U/L', 'price' => 600, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '9', 'to' => '48'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '9', 'to' => '36'],
            ]],
        ];

        $this->createTestsForGroup($labId, $lftGroup, $lftTests, $categories['Liver Function'], $samples['Serum']);

        // Kidney Function Tests
        $kftGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Kidney Function Tests (KFT)'],
            [
                'shortcut' => 'KFT',
                'category_id_fk' => $categories['Kidney Function']->id,
                'sample_id_fk' => $samples['Serum']->id,
                'original_price' => 2500,
                'for_customer_price' => 3500,
                'test_duration' => 3,
                'duration_unit_id_fk' => 2,
                'precautions' => 'Fasting not required. Stay hydrated.',
            ]
        );

        $kftTests = [
            ['name' => 'Blood Urea Nitrogen (BUN)', 'shortcut' => 'BUN', 'unit' => 'mg/dL', 'price' => 500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '7', 'to' => '20'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '7', 'to' => '20'],
            ]],
            ['name' => 'Creatinine', 'shortcut' => 'CREA', 'unit' => 'mg/dL', 'price' => 500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.7', 'to' => '1.3'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.6', 'to' => '1.1'],
            ]],
            ['name' => 'Uric Acid', 'shortcut' => 'UA', 'unit' => 'mg/dL', 'price' => 500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '3.4', 'to' => '7.0'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '2.4', 'to' => '6.0'],
            ]],
            ['name' => 'eGFR', 'shortcut' => 'EGFR', 'unit' => 'mL/min/1.73m²', 'price' => 600, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '90', 'to' => '120', 'notes' => '>90 Normal, 60-89 Mild decrease, <60 Moderate decrease'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '90', 'to' => '120', 'notes' => '>90 Normal, 60-89 Mild decrease, <60 Moderate decrease'],
            ]],
        ];

        $this->createTestsForGroup($labId, $kftGroup, $kftTests, $categories['Kidney Function'], $samples['Serum']);

        // Thyroid Function Tests
        $thyroidGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Thyroid Function Tests'],
            [
                'shortcut' => 'TFT',
                'category_id_fk' => $categories['Thyroid Function']->id,
                'sample_id_fk' => $samples['Serum']->id,
                'original_price' => 4000,
                'for_customer_price' => 6000,
                'test_duration' => 6,
                'duration_unit_id_fk' => 2,
                'precautions' => 'Fasting not required. Inform about thyroid medications.',
            ]
        );

        $thyroidTests = [
            ['name' => 'TSH', 'shortcut' => 'TSH', 'unit' => 'mIU/L', 'price' => 1500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.4', 'to' => '4.0'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.4', 'to' => '4.0'],
            ]],
            ['name' => 'Free T3', 'shortcut' => 'FT3', 'unit' => 'pg/mL', 'price' => 1200, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '2.0', 'to' => '4.4'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '2.0', 'to' => '4.4'],
            ]],
            ['name' => 'Free T4', 'shortcut' => 'FT4', 'unit' => 'ng/dL', 'price' => 1200, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.8', 'to' => '1.8'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.8', 'to' => '1.8'],
            ]],
        ];

        $this->createTestsForGroup($labId, $thyroidGroup, $thyroidTests, $categories['Thyroid Function'], $samples['Serum']);

        // Diabetes Panel
        $diabetesGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Diabetes Panel'],
            [
                'shortcut' => 'DM',
                'category_id_fk' => $categories['Diabetes Panel']->id,
                'sample_id_fk' => $samples['Plasma']->id,
                'original_price' => 2000,
                'for_customer_price' => 3000,
                'test_duration' => 3,
                'duration_unit_id_fk' => 2,
                'precautions' => 'Fasting for 8-12 hours required for FBS.',
            ]
        );

        $diabetesTests = [
            ['name' => 'Fasting Blood Sugar (FBS)', 'shortcut' => 'FBS', 'unit' => 'mg/dL', 'price' => 400, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '70', 'to' => '100', 'notes' => 'Normal: 70-100, Prediabetes: 100-125, Diabetes: ≥126'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '70', 'to' => '100', 'notes' => 'Normal: 70-100, Prediabetes: 100-125, Diabetes: ≥126'],
            ]],
            ['name' => 'Random Blood Sugar (RBS)', 'shortcut' => 'RBS', 'unit' => 'mg/dL', 'price' => 400, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '70', 'to' => '140'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '70', 'to' => '140'],
            ]],
            ['name' => 'HbA1c', 'shortcut' => 'HBA1C', 'unit' => '%', 'price' => 1200, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '4.0', 'to' => '5.6', 'notes' => 'Normal: <5.7%, Prediabetes: 5.7-6.4%, Diabetes: ≥6.5%'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '4.0', 'to' => '5.6', 'notes' => 'Normal: <5.7%, Prediabetes: 5.7-6.4%, Diabetes: ≥6.5%'],
            ]],
        ];

        $this->createTestsForGroup($labId, $diabetesGroup, $diabetesTests, $categories['Diabetes Panel'], $samples['Plasma']);

        // Urinalysis
        $urineGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Complete Urinalysis'],
            [
                'shortcut' => 'UA',
                'category_id_fk' => $categories['Urinalysis']->id,
                'sample_id_fk' => $samples['Urine (Random)']->id,
                'original_price' => 1500,
                'for_customer_price' => 2000,
                'test_duration' => 2,
                'duration_unit_id_fk' => 2,
                'precautions' => 'Collect midstream urine sample. First morning sample preferred.',
            ]
        );

        $urineTests = [
            ['name' => 'Urine Color', 'shortcut' => 'UCOL', 'unit' => '', 'price' => 100, 'result_type' => 4, 'options' => ['Pale Yellow', 'Yellow', 'Dark Yellow', 'Amber', 'Red', 'Brown']],
            ['name' => 'Urine Appearance', 'shortcut' => 'UAPP', 'unit' => '', 'price' => 100, 'result_type' => 4, 'options' => ['Clear', 'Slightly Cloudy', 'Cloudy', 'Turbid']],
            ['name' => 'Urine pH', 'shortcut' => 'UPH', 'unit' => '', 'price' => 150, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '4.5', 'to' => '8.0'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '4.5', 'to' => '8.0'],
            ]],
            ['name' => 'Urine Specific Gravity', 'shortcut' => 'USG', 'unit' => '', 'price' => 150, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '1.005', 'to' => '1.030'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '1.005', 'to' => '1.030'],
            ]],
            ['name' => 'Urine Protein', 'shortcut' => 'UPRO', 'unit' => '', 'price' => 200, 'result_type' => 4, 'options' => ['Negative', 'Trace', '+1', '+2', '+3', '+4']],
            ['name' => 'Urine Glucose', 'shortcut' => 'UGLU', 'unit' => '', 'price' => 200, 'result_type' => 4, 'options' => ['Negative', 'Trace', '+1', '+2', '+3', '+4']],
            ['name' => 'Urine RBC', 'shortcut' => 'URBC', 'unit' => '/HPF', 'price' => 200, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '0', 'to' => '2'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '0', 'to' => '2'],
            ]],
            ['name' => 'Urine WBC', 'shortcut' => 'UWBC', 'unit' => '/HPF', 'price' => 200, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '0', 'to' => '5'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '0', 'to' => '5'],
            ]],
        ];

        $this->createTestsForGroup($labId, $urineGroup, $urineTests, $categories['Urinalysis'], $samples['Urine (Random)']);

        // Coagulation Profile
        $coagGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Coagulation Profile'],
            [
                'shortcut' => 'COAG',
                'category_id_fk' => $categories['Coagulation']->id,
                'sample_id_fk' => $samples['Whole Blood (Citrate)']->id,
                'original_price' => 2500,
                'for_customer_price' => 3500,
                'test_duration' => 4,
                'duration_unit_id_fk' => 2,
                'precautions' => 'Inform about anticoagulant medications.',
            ]
        );

        $coagTests = [
            ['name' => 'Prothrombin Time (PT)', 'shortcut' => 'PT', 'unit' => 'seconds', 'price' => 800, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '11', 'to' => '13.5'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '11', 'to' => '13.5'],
            ]],
            ['name' => 'INR', 'shortcut' => 'INR', 'unit' => '', 'price' => 500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.8', 'to' => '1.1'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0.8', 'to' => '1.1'],
            ]],
            ['name' => 'APTT', 'shortcut' => 'APTT', 'unit' => 'seconds', 'price' => 800, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '25', 'to' => '35'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '25', 'to' => '35'],
            ]],
        ];

        $this->createTestsForGroup($labId, $coagGroup, $coagTests, $categories['Coagulation'], $samples['Whole Blood (Citrate)']);

        // Cardiac Markers
        $cardiacGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Cardiac Markers'],
            [
                'shortcut' => 'CARD',
                'category_id_fk' => $categories['Cardiac Markers']->id,
                'sample_id_fk' => $samples['Serum']->id,
                'original_price' => 5000,
                'for_customer_price' => 7500,
                'test_duration' => 4,
                'duration_unit_id_fk' => 2,
                'precautions' => 'Inform about chest pain or cardiac symptoms.',
            ]
        );

        $cardiacTests = [
            ['name' => 'Troponin I', 'shortcut' => 'TROP', 'unit' => 'ng/mL', 'price' => 2500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '0.04', 'notes' => '<0.04 Normal, 0.04-0.39 Elevated, ≥0.40 High'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '0.04', 'notes' => '<0.04 Normal, 0.04-0.39 Elevated, ≥0.40 High'],
            ]],
            ['name' => 'CK-MB', 'shortcut' => 'CKMB', 'unit' => 'ng/mL', 'price' => 1500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '5.0'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '5.0'],
            ]],
            ['name' => 'BNP', 'shortcut' => 'BNP', 'unit' => 'pg/mL', 'price' => 2000, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '100', 'notes' => '<100 Normal, 100-400 Borderline, >400 Heart failure likely'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '100', 'notes' => '<100 Normal, 100-400 Borderline, >400 Heart failure likely'],
            ]],
        ];

        $this->createTestsForGroup($labId, $cardiacGroup, $cardiacTests, $categories['Cardiac Markers'], $samples['Serum']);

        // Hormones Panel
        $hormoneGroup = TestGroup::firstOrCreate(
            ['lab_id_fk' => $labId, 'group_name' => 'Hormone Panel'],
            [
                'shortcut' => 'HORM',
                'category_id_fk' => $categories['Hormones']->id,
                'sample_id_fk' => $samples['Serum']->id,
                'original_price' => 6000,
                'for_customer_price' => 9000,
                'test_duration' => 24,
                'duration_unit_id_fk' => 2,
                'precautions' => 'Collect sample in the morning. Inform about hormone medications.',
            ]
        );

        $hormoneTests = [
            ['name' => 'Testosterone', 'shortcut' => 'TEST', 'unit' => 'ng/dL', 'price' => 2000, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '270', 'to' => '1070'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '15', 'to' => '70'],
            ]],
            ['name' => 'Estradiol', 'shortcut' => 'E2', 'unit' => 'pg/mL', 'price' => 2000, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '10', 'to' => '40'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '30', 'to' => '400', 'notes' => 'Varies with menstrual cycle'],
            ]],
            ['name' => 'Prolactin', 'shortcut' => 'PRL', 'unit' => 'ng/mL', 'price' => 1500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '2', 'to' => '18'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '2', 'to' => '29'],
            ]],
            ['name' => 'FSH', 'shortcut' => 'FSH', 'unit' => 'mIU/mL', 'price' => 1500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '1.5', 'to' => '12.4'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '3.5', 'to' => '12.5', 'notes' => 'Follicular phase'],
            ]],
            ['name' => 'LH', 'shortcut' => 'LH', 'unit' => 'mIU/mL', 'price' => 1500, 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '1.7', 'to' => '8.6'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '2.4', 'to' => '12.6', 'notes' => 'Follicular phase'],
            ]],
        ];

        $this->createTestsForGroup($labId, $hormoneGroup, $hormoneTests, $categories['Hormones'], $samples['Serum']);

        // Individual tests not in groups
        $individualTests = [
            ['name' => 'Vitamin D (25-OH)', 'shortcut' => 'VITD', 'unit' => 'ng/mL', 'price' => 2500, 'category' => 'Clinical Chemistry', 'sample' => 'Serum', 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '30', 'to' => '100', 'notes' => '<20 Deficient, 20-29 Insufficient, 30-100 Sufficient'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '30', 'to' => '100', 'notes' => '<20 Deficient, 20-29 Insufficient, 30-100 Sufficient'],
            ]],
            ['name' => 'Vitamin B12', 'shortcut' => 'B12', 'unit' => 'pg/mL', 'price' => 2000, 'category' => 'Clinical Chemistry', 'sample' => 'Serum', 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '200', 'to' => '900'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '200', 'to' => '900'],
            ]],
            ['name' => 'Ferritin', 'shortcut' => 'FER', 'unit' => 'ng/mL', 'price' => 1500, 'category' => 'Hematology', 'sample' => 'Serum', 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '20', 'to' => '250'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '10', 'to' => '120'],
            ]],
            ['name' => 'Iron', 'shortcut' => 'FE', 'unit' => 'µg/dL', 'price' => 1000, 'category' => 'Hematology', 'sample' => 'Serum', 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '60', 'to' => '170'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '37', 'to' => '145'],
            ]],
            ['name' => 'CRP (C-Reactive Protein)', 'shortcut' => 'CRP', 'unit' => 'mg/L', 'price' => 1200, 'category' => 'Immunology', 'sample' => 'Serum', 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '0', 'to' => '3', 'notes' => '<1 Low risk, 1-3 Average risk, >3 High risk'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 0, 'age_to' => 120, 'from' => '0', 'to' => '3', 'notes' => '<1 Low risk, 1-3 Average risk, >3 High risk'],
            ]],
            ['name' => 'ESR', 'shortcut' => 'ESR', 'unit' => 'mm/hr', 'price' => 500, 'category' => 'Hematology', 'sample' => 'Whole Blood (EDTA)', 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 50, 'from' => '0', 'to' => '15'],
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 50, 'age_to' => 120, 'from' => '0', 'to' => '20'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 50, 'from' => '0', 'to' => '20'],
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 50, 'age_to' => 120, 'from' => '0', 'to' => '30'],
            ]],
            ['name' => 'PSA (Prostate Specific Antigen)', 'shortcut' => 'PSA', 'unit' => 'ng/mL', 'price' => 2500, 'category' => 'Tumor Markers', 'sample' => 'Serum', 'result_type' => 1, 'ranges' => [
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 40, 'age_to' => 49, 'from' => '0', 'to' => '2.5'],
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 50, 'age_to' => 59, 'from' => '0', 'to' => '3.5'],
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 60, 'age_to' => 69, 'from' => '0', 'to' => '4.5'],
                ['gender' => 1, 'age_unit' => 1, 'age_from' => 70, 'age_to' => 120, 'from' => '0', 'to' => '6.5'],
            ]],
            ['name' => 'CA-125', 'shortcut' => 'CA125', 'unit' => 'U/mL', 'price' => 3000, 'category' => 'Tumor Markers', 'sample' => 'Serum', 'result_type' => 1, 'ranges' => [
                ['gender' => 2, 'age_unit' => 1, 'age_from' => 18, 'age_to' => 120, 'from' => '0', 'to' => '35'],
            ]],
        ];

        $order = 1;
        foreach ($individualTests as $testData) {
            $test = Test::firstOrCreate(
                ['lab_id_fk' => $labId, 'name' => $testData['name']],
                [
                    'shortcut' => $testData['shortcut'],
                    'unit' => $testData['unit'],
                    'price' => $testData['price'],
                    'for_customer_price' => intval($testData['price'] * 1.5),
                    'category_id_fk' => $categories[$testData['category']]->id,
                    'sample_id_fk' => $samples[$testData['sample']]->id,
                    'result_type_id_fk' => $testData['result_type'],
                    'order' => $order++,
                    'test_duration' => 4,
                    'duration_unit_id_fk' => 2,
                ]
            );

            if (isset($testData['ranges'])) {
                foreach ($testData['ranges'] as $range) {
                    TestReferenceRange::firstOrCreate(
                        [
                            'lab_id_fk' => $labId,
                            'test_id' => $test->id,
                            'gender_id_fk' => $range['gender'],
                            'age_unit_id_fk' => $range['age_unit'],
                            'age_from' => $range['age_from'],
                            'age_to' => $range['age_to'],
                        ],
                        [
                            'from' => $range['from'],
                            'to' => $range['to'],
                            'notes' => $range['notes'] ?? null,
                        ]
                    );
                }
            }
        }
    }

    private function createTestsForGroup(int $labId, TestGroup $group, array $tests, Category $category, Sample $sample): void
    {
        $order = 1;
        foreach ($tests as $testData) {
            $test = Test::firstOrCreate(
                ['lab_id_fk' => $labId, 'name' => $testData['name']],
                [
                    'test_group_id_fk' => $group->id,
                    'shortcut' => $testData['shortcut'],
                    'unit' => $testData['unit'],
                    'price' => $testData['price'],
                    'for_customer_price' => intval($testData['price'] * 1.5),
                    'category_id_fk' => $category->id,
                    'sample_id_fk' => $sample->id,
                    'result_type_id_fk' => $testData['result_type'],
                    'order' => $order++,
                    'test_duration' => 2,
                    'duration_unit_id_fk' => 2,
                    'selection_type_options' => isset($testData['options']) ? json_encode($testData['options']) : null,
                ]
            );

            if (isset($testData['ranges'])) {
                foreach ($testData['ranges'] as $range) {
                    TestReferenceRange::firstOrCreate(
                        [
                            'lab_id_fk' => $labId,
                            'test_id' => $test->id,
                            'gender_id_fk' => $range['gender'],
                            'age_unit_id_fk' => $range['age_unit'],
                            'age_from' => $range['age_from'],
                            'age_to' => $range['age_to'],
                        ],
                        [
                            'from' => $range['from'],
                            'to' => $range['to'],
                            'notes' => $range['notes'] ?? null,
                        ]
                    );
                }
            }
        }
    }

    private function createCultures(int $labId, array $categories, array $samples): void
    {
        $cultures = [
            [
                'name' => 'Blood Culture',
                'category' => 'Microbiology',
                'sample' => 'Whole Blood (EDTA)',
                'price' => 5000,
                'precautions' => 'Collect before antibiotic therapy. Use aseptic technique.',
            ],
            [
                'name' => 'Urine Culture',
                'category' => 'Microbiology',
                'sample' => 'Urine (Random)',
                'price' => 3000,
                'precautions' => 'Midstream clean catch sample. Collect in sterile container.',
            ],
            [
                'name' => 'Stool Culture',
                'category' => 'Microbiology',
                'sample' => 'Stool',
                'price' => 3500,
                'precautions' => 'Fresh sample preferred. Transport within 2 hours.',
            ],
            [
                'name' => 'Sputum Culture',
                'category' => 'Microbiology',
                'sample' => 'Sputum',
                'price' => 3500,
                'precautions' => 'Early morning sample. Deep cough specimen.',
            ],
            [
                'name' => 'Wound Culture',
                'category' => 'Microbiology',
                'sample' => 'Swab',
                'price' => 3000,
                'precautions' => 'Clean wound area before collection. Collect from wound bed.',
            ],
            [
                'name' => 'Throat Culture',
                'category' => 'Microbiology',
                'sample' => 'Swab',
                'price' => 2500,
                'precautions' => 'Do not eat or drink 30 minutes before collection.',
            ],
            [
                'name' => 'CSF Culture',
                'category' => 'Microbiology',
                'sample' => 'CSF',
                'price' => 6000,
                'precautions' => 'STAT processing required. Sterile collection only.',
            ],
        ];

        foreach ($cultures as $cultureData) {
            Culture::firstOrCreate(
                ['lab_id_fk' => $labId, 'name' => $cultureData['name']],
                [
                    'category_id_fk' => $categories[$cultureData['category']]->id,
                    'sample_id_fk' => $samples[$cultureData['sample']]->id,
                    'price' => $cultureData['price'],
                    'price_for_customer' => intval($cultureData['price'] * 1.5),
                    'precautions' => $cultureData['precautions'],
                    'test_duration' => 3,
                    'duration_unit_id_fk' => 3, // Days
                ]
            );
        }
    }

    private function createPackages(int $labId): void
    {
        $packages = [
            ['name' => 'Basic Health Checkup', 'shortcut' => 'BHC', 'price' => 15000],
            ['name' => 'Comprehensive Health Package', 'shortcut' => 'CHP', 'price' => 35000],
            ['name' => 'Diabetes Care Package', 'shortcut' => 'DCP', 'price' => 8000],
            ['name' => 'Cardiac Risk Assessment', 'shortcut' => 'CRA', 'price' => 20000],
            ['name' => 'Women Health Package', 'shortcut' => 'WHP', 'price' => 25000],
            ['name' => 'Men Health Package', 'shortcut' => 'MHP', 'price' => 25000],
            ['name' => 'Senior Citizen Package', 'shortcut' => 'SCP', 'price' => 30000],
            ['name' => 'Pre-Employment Checkup', 'shortcut' => 'PEC', 'price' => 12000],
        ];

        foreach ($packages as $packageData) {
            Package::firstOrCreate(
                ['lab_id_fk' => $labId, 'name' => $packageData['name']],
                [
                    'shortcut' => $packageData['shortcut'],
                    'price' => $packageData['price'],
                    'is_constant_price' => true,
                ]
            );
        }
    }

    private function createPriceLists(int $labId): void
    {
        $priceLists = [
            ['name' => 'Standard Price List', 'discount' => 0],
            ['name' => 'Corporate Discount', 'discount' => 15],
            ['name' => 'Insurance Panel A', 'discount' => 20],
            ['name' => 'Insurance Panel B', 'discount' => 25],
            ['name' => 'VIP Pricing', 'discount' => 10],
            ['name' => 'Referral Doctor Discount', 'discount' => 30],
        ];

        foreach ($priceLists as $priceListData) {
            PriceList::firstOrCreate(
                ['lab_id_fk' => $labId, 'name' => $priceListData['name']],
                ['discount' => $priceListData['discount']]
            );
        }
    }

    private function createAntibiotics(int $labId): void
    {
        $antibiotics = [
            ['scientific' => 'Amoxicillin', 'common' => 'Amoxil', 'short' => 'AMX'],
            ['scientific' => 'Ampicillin', 'common' => 'Principen', 'short' => 'AMP'],
            ['scientific' => 'Azithromycin', 'common' => 'Zithromax', 'short' => 'AZM'],
            ['scientific' => 'Ceftriaxone', 'common' => 'Rocephin', 'short' => 'CRO'],
            ['scientific' => 'Ciprofloxacin', 'common' => 'Cipro', 'short' => 'CIP'],
            ['scientific' => 'Clarithromycin', 'common' => 'Biaxin', 'short' => 'CLR'],
            ['scientific' => 'Clindamycin', 'common' => 'Cleocin', 'short' => 'CLI'],
            ['scientific' => 'Doxycycline', 'common' => 'Vibramycin', 'short' => 'DOX'],
            ['scientific' => 'Erythromycin', 'common' => 'Erythrocin', 'short' => 'ERY'],
            ['scientific' => 'Gentamicin', 'common' => 'Garamycin', 'short' => 'GEN'],
            ['scientific' => 'Levofloxacin', 'common' => 'Levaquin', 'short' => 'LVX'],
            ['scientific' => 'Metronidazole', 'common' => 'Flagyl', 'short' => 'MTZ'],
            ['scientific' => 'Penicillin G', 'common' => 'Pfizerpen', 'short' => 'PEN'],
            ['scientific' => 'Tetracycline', 'common' => 'Sumycin', 'short' => 'TET'],
            ['scientific' => 'Trimethoprim-Sulfamethoxazole', 'common' => 'Bactrim', 'short' => 'SXT'],
            ['scientific' => 'Vancomycin', 'common' => 'Vancocin', 'short' => 'VAN'],
            ['scientific' => 'Meropenem', 'common' => 'Merrem', 'short' => 'MEM'],
            ['scientific' => 'Imipenem', 'common' => 'Primaxin', 'short' => 'IPM'],
        ];

        foreach ($antibiotics as $abData) {
            Antibiotics::firstOrCreate(
                ['lab_id' => $labId, 'scientific_name' => $abData['scientific']],
                [
                    'common_name' => $abData['common'],
                    'short_name' => $abData['short'],
                ]
            );
        }
    }

    private function createPatientQuestions(int $labId): void
    {
        $questions = [
            ['question' => 'Are you currently fasting?', 'answer_type' => 1], // checkbox
            ['question' => 'How many hours since last meal?', 'answer_type' => 2], // number
            ['question' => 'Last menstrual period date?', 'answer_type' => 3], // date
            ['question' => 'Current medications?', 'answer_type' => 4], // text
            ['question' => 'Any known allergies?', 'answer_type' => 4], // text
            ['question' => 'Are you pregnant?', 'answer_type' => 1], // checkbox
            ['question' => 'Do you have diabetes?', 'answer_type' => 1], // checkbox
            ['question' => 'Blood pressure medication?', 'answer_type' => 1], // checkbox
        ];

        foreach ($questions as $q) {
            PatientQuestion::firstOrCreate(
                ['lab_id_fk' => $labId, 'question' => $q['question']],
                ['answer_type_id_fk' => $q['answer_type']]
            );
        }
    }
}
