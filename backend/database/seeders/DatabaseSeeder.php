<?php

namespace Database\Seeders;

use App\Models\AgeUnit;
use App\Models\AnswerType;
use App\Models\Contract;
use App\Models\DurationUnit;
use App\Models\Gender;
use App\Models\Nationality;
use App\Models\ResultStatus;
use App\Models\ResultType;
use App\Models\Title;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo data is disabled in production. Use erp:install instead.');
        }
        $this->call(ReferenceDataSeeder::class);

        // Call seeders in dependency order
        $this->call([
            // 1. Permissions and Roles (required for user roles)
            PermissionsSeeder::class,

            // 2. Reference data (discount types — FK target for invoices.discount_type_id_fk)
            DiscountTypesSeeder::class,

            // 3. Users (admin, lab, doctors, collectors, staff)
            UsersSeeder::class,
        ]);

        // Seed contracts AFTER UsersSeeder so lab user exists
        $labUser = User::where('email', 'lab@medicallab.com')->first();
        $labId = $labUser ? $labUser->id : null;

        $contracts = [
            [
                'name' => 'Contract A',
                'payment_percent' => 80,
                'maximum_payment_per_invoice' => 10000,
                'credit_limit' => 50000,
                'price_limit' => 20000,
                'discount_percentage' => 10,
                'address' => '123 Main St, City, Country',
                'phone_number' => '123-456-7890',
                'email' => 'contractA@example.com',
                'lab_id_fk' => $labId,
            ],
            [
                'name' => 'Contract B',
                'payment_percent' => 70,
                'maximum_payment_per_invoice' => 15000,
                'credit_limit' => 60000,
                'price_limit' => 25000,
                'discount_percentage' => 15,
                'address' => '456 Elm St, City, Country',
                'phone_number' => '098-765-4321',
                'email' => 'contractB@example.com',
                'lab_id_fk' => $labId,
            ],
            [
                'name' => 'Contract C',
                'payment_percent' => 85,
                'maximum_payment_per_invoice' => 12000,
                'credit_limit' => 55000,
                'price_limit' => 22000,
                'discount_percentage' => 20,
                'address' => '789 Oak St, City, Country',
                'phone_number' => '555-123-4567',
                'email' => 'contractC@example.com',
                'lab_id_fk' => $labId,
            ],
        ];

        foreach ($contracts as $contract) {
            Contract::firstOrCreate(['name' => $contract['name'], 'lab_id_fk' => $contract['lab_id_fk']], $contract);
        }

        $this->call([
            // 3. Lab Entities (categories, samples, test groups, tests, cultures, packages, price lists)
            LabEntitiesSeeder::class,

            // 4. Patients (requires users, titles, genders, nationalities, contracts)
            PatientsSeeder::class,

            // 5. Referrals (links doctors to labs with commissions)
            ReferralsSeeder::class,

            // 6. Invoices (requires patients, tests, cultures, packages)
            InvoicesSeeder::class,
        ]);
    }
}
