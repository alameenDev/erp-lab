<?php

namespace Database\Seeders;

use App\Models\Culture;
use App\Models\Invoice;
use App\Models\InvoiceTestRel;
use App\Models\Package;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Test;
use App\Models\TestGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InvoicesSeeder extends Seeder
{
    public function run(): void
    {
        $labOwner = User::where('email', 'lab@medicallab.com')->first();

        if (!$labOwner) {
            $this->command->error('Lab owner not found. Run UsersSeeder first.');
            return;
        }

        $labId = $labOwner->id;

        // Create payment methods first
        $this->createPaymentMethods($labId);

        // Get doctors for referrals
        $doctors = User::where('role_id', 5)->pluck('id')->toArray();

        // Get sample collectors
        $collectors = User::where('role_id', 6)->pluck('id')->toArray();

        // Get all patients
        $patients = Patient::with('user')->get();

        // Get test groups
        $testGroups = TestGroup::where('lab_id_fk', $labId)->get();

        // Get individual tests
        $tests = Test::where('lab_id_fk', $labId)->whereNull('test_group_id_fk')->get();

        // Get cultures
        $cultures = Culture::where('lab_id_fk', $labId)->get();

        // Get packages
        $packages = Package::where('lab_id_fk', $labId)->get();

        $invoiceCount = 0;

        // Create invoices for the past 6 months
        for ($month = 5; $month >= 0; $month--) {
            $invoicesThisMonth = rand(15, 30);

            for ($i = 0; $i < $invoicesThisMonth; $i++) {
                $patient = $patients->random();
                $registrationDate = Carbon::now()->subMonths($month)->subDays(rand(0, 28));

                // Randomly select what to include
                $includeTestGroup = rand(0, 100) > 20; // 80% chance
                $includeIndividualTest = rand(0, 100) > 40; // 60% chance
                $includeCulture = rand(0, 100) > 70; // 30% chance
                $includePackage = rand(0, 100) > 80; // 20% chance

                if (!$includeTestGroup && !$includeIndividualTest && !$includeCulture && !$includePackage) {
                    $includeTestGroup = true;
                }

                $subTotal = 0;
                $invoiceItems = [];

                // Add test groups
                if ($includeTestGroup && $testGroups->count() > 0) {
                    $selectedGroups = $testGroups->random(rand(1, min(3, $testGroups->count())));
                    foreach ($selectedGroups as $group) {
                        $price = $group->for_customer_price ?? $group->original_price ?? 2000;
                        $subTotal += $price;
                        $invoiceItems[] = [
                            'type' => 'test_group',
                            'id' => $group->id,
                            'price' => $price,
                        ];
                    }
                }

                // Add individual tests
                if ($includeIndividualTest && $tests->count() > 0) {
                    $selectedTests = $tests->random(rand(1, min(4, $tests->count())));
                    foreach ($selectedTests as $test) {
                        $price = $test->for_customer_price ?? $test->price ?? 500;
                        $subTotal += $price;
                        $invoiceItems[] = [
                            'type' => 'test',
                            'id' => $test->id,
                            'price' => $price,
                        ];
                    }
                }

                // Add cultures
                if ($includeCulture && $cultures->count() > 0) {
                    $selectedCultures = $cultures->random(rand(1, min(2, $cultures->count())));
                    foreach ($selectedCultures as $culture) {
                        $price = $culture->price_for_customer ?? $culture->price ?? 3000;
                        $subTotal += $price;
                        $invoiceItems[] = [
                            'type' => 'culture',
                            'id' => $culture->id,
                            'price' => $price,
                        ];
                    }
                }

                // Add packages
                if ($includePackage && $packages->count() > 0) {
                    $package = $packages->random();
                    $subTotal += $package->price ?? 10000;
                    $invoiceItems[] = [
                        'type' => 'package',
                        'id' => $package->id,
                        'price' => $package->price ?? 10000,
                    ];
                }

                // Calculate discount
                $discountPercent = rand(0, 20);
                $discount = intval($subTotal * $discountPercent / 100);
                $total = $subTotal - $discount;

                // Payment status
                $isPaid = rand(0, 100) > 15; // 85% paid
                $paid = $isPaid ? $total : rand(0, intval($total * 0.5));

                // Completion status (older invoices more likely to be complete)
                $isDone = $month > 0 ? rand(0, 100) > 10 : rand(0, 100) > 40;

                // Result date (1-3 days after registration for completed invoices)
                $resultDate = $isDone ? $registrationDate->copy()->addDays(rand(1, 3)) : null;

                // Create invoice
                $invoice = Invoice::create([
                    'patient_id_fk' => $patient->id,
                    'lab_id_fk' => $labId,
                    'from_lab_id_fk' => $labId,
                    'referral_id_fk' => rand(0, 100) > 50 && count($doctors) > 0 ? $doctors[array_rand($doctors)] : null,
                    'sample_collector_id_fk' => count($collectors) > 0 ? $collectors[array_rand($collectors)] : null,
                    'contract_id_fk' => $patient->contract_id_fk,
                    'registration_date' => $registrationDate,
                    'result_date' => $resultDate,
                    'sub_total' => $subTotal,
                    'discount' => $discount,
                    'total' => $total,
                    'paid' => $paid,
                    'is_done' => $isDone,
                    'is_printed' => $isDone && rand(0, 100) > 30,
                    'is_signed' => $isDone && rand(0, 100) > 20,
                    'signed_by_id_fk' => $isDone && count($doctors) > 0 ? $doctors[array_rand($doctors)] : null,
                    'barcode' => 'INV' . $registrationDate->format('Ymd') . str_pad($invoiceCount + 1, 5, '0', STR_PAD_LEFT),
                    'qr_code' => Str::uuid()->toString(),
                    'notes' => $this->getRandomNote(),
                    'show_result_date' => true,
                    'show_patient_card_id' => rand(0, 1),
                    'created_at' => $registrationDate,
                    'updated_at' => $resultDate ?? $registrationDate,
                ]);

                // Create invoice test relations
                foreach ($invoiceItems as $item) {
                    $testRelData = [
                        'invoice_id_fk' => $invoice->id,
                        'price' => $item['price'],
                        'is_done' => $isDone,
                        'is_sample_received' => true,
                        'result_status_id_fk' => $isDone ? rand(1, 5) : null,
                    ];

                    if ($item['type'] === 'test_group') {
                        $testRelData['test_group_id_fk'] = $item['id'];
                        if ($isDone) {
                            $testRelData['result'] = $this->generateTestGroupResult();
                        }
                    } elseif ($item['type'] === 'test') {
                        $testRelData['test_id_fk'] = $item['id'];
                        if ($isDone) {
                            $testRelData['result'] = $this->generateTestResult();
                        }
                    } elseif ($item['type'] === 'culture') {
                        $testRelData['culture_id_fk'] = $item['id'];
                        if ($isDone) {
                            $testRelData['result'] = $this->generateCultureResult();
                        }
                    } elseif ($item['type'] === 'package') {
                        $testRelData['package_id_fk'] = $item['id'];
                        if ($isDone) {
                            $testRelData['result'] = 'Package completed';
                        }
                    }

                    InvoiceTestRel::create($testRelData);
                }

                $invoiceCount++;
            }
        }

        $this->command->info("Created {$invoiceCount} invoices with test relations.");
    }

    private function createPaymentMethods(int $labId): void
    {
        $methods = [
            'Cash',
            'Credit Card',
            'Debit Card',
            'Bank Transfer',
            'Insurance',
            'Online Payment',
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['lab_id_fk' => $labId, 'name' => $method]
            );
        }
    }

    private function getRandomNote(): ?string
    {
        $notes = [
            null,
            null,
            null,
            'Patient requested urgent processing',
            'Sample collected at home',
            'Follow-up test requested',
            'Referred by insurance company',
            'Pre-surgery screening',
            'Annual health checkup',
            'Routine monitoring',
            'Employee health screening',
            'Patient has history of diabetes',
            'Blood pressure medication noted',
            'Fasting sample collected',
            'Non-fasting sample',
        ];

        return $notes[array_rand($notes)];
    }

    private function generateTestResult(): string
    {
        $results = [
            '12.5',
            '85',
            '120',
            '98.6',
            '5.5',
            '140',
            '3.2',
            '45',
            '200',
            'Normal',
            'Within range',
            '7.2',
            '15.3',
            '250000',
            '4500',
        ];

        return $results[array_rand($results)];
    }

    private function generateTestGroupResult(): string
    {
        $results = [
            'All values within normal range',
            'Slightly elevated - monitor recommended',
            'Normal findings',
            'One parameter mildly abnormal',
            'Results satisfactory',
            'Minor variations noted',
            'Within expected limits',
        ];

        return $results[array_rand($results)];
    }

    private function generateCultureResult(): string
    {
        $results = [
            'No growth after 48 hours - Negative',
            'No pathogenic organisms isolated',
            'E. coli isolated - Sensitive to Amoxicillin, Ciprofloxacin',
            'Staphylococcus aureus - MRSA negative',
            'Normal flora',
            'Klebsiella pneumoniae isolated',
            'No significant growth',
            'Mixed flora - No predominant pathogen',
        ];

        return $results[array_rand($results)];
    }
}
