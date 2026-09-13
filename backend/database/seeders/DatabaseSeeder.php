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
        $titles = [
            ['title' => 'Mr'],
            ['title' => 'Mrs'],
            ['title' => 'Child'],
        ];

        foreach ($titles as $title) {
            Title::firstOrCreate($title);
        }

        $genders = [
            ['gender_type' => 'Male'],
            ['gender_type' => 'Female'],
            ['gender_type' => 'Both'],
        ];

        foreach ($genders as $gender) {
            Gender::firstOrCreate($gender);
        }

        $nationalities = [
            ['country_name' => 'Afghanistan'],
            ['country_name' => 'Albania'],
            ['country_name' => 'Algeria'],
            ['country_name' => 'Andorra'],
            ['country_name' => 'Angola'],
            ['country_name' => 'Antigua and Barbuda'],
            ['country_name' => 'Argentina'],
            ['country_name' => 'Armenia'],
            ['country_name' => 'Australia'],
            ['country_name' => 'Austria'],
            ['country_name' => 'Azerbaijan'],
            ['country_name' => 'Bahamas'],
            ['country_name' => 'Bahrain'],
            ['country_name' => 'Bangladesh'],
            ['country_name' => 'Barbados'],
            ['country_name' => 'Belarus'],
            ['country_name' => 'Belgium'],
            ['country_name' => 'Belize'],
            ['country_name' => 'Benin'],
            ['country_name' => 'Bhutan'],
            ['country_name' => 'Bolivia'],
            ['country_name' => 'Bosnia and Herzegovina'],
            ['country_name' => 'Botswana'],
            ['country_name' => 'Brazil'],
            ['country_name' => 'Brunei'],
            ['country_name' => 'Bulgaria'],
            ['country_name' => 'Burkina Faso'],
            ['country_name' => 'Burundi'],
            ['country_name' => 'Cabo Verde'],
            ['country_name' => 'Cambodia'],
            ['country_name' => 'Cameroon'],
            ['country_name' => 'Canada'],
            ['country_name' => 'Central African Republic'],
            ['country_name' => 'Chad'],
            ['country_name' => 'Chile'],
            ['country_name' => 'China'],
            ['country_name' => 'Colombia'],
            ['country_name' => 'Comoros'],
            ['country_name' => 'Congo'],
            ['country_name' => 'Congo (Democratic Republic)'],
            ['country_name' => 'Costa Rica'],
            ['country_name' => 'Croatia'],
            ['country_name' => 'Cuba'],
            ['country_name' => 'Cyprus'],
            ['country_name' => 'Czech Republic'],
            ['country_name' => 'Denmark'],
            ['country_name' => 'Djibouti'],
            ['country_name' => 'Dominica'],
            ['country_name' => 'Dominican Republic'],
            ['country_name' => 'East Timor'],
            ['country_name' => 'Ecuador'],
            ['country_name' => 'Egypt'],
            ['country_name' => 'El Salvador'],
            ['country_name' => 'Equatorial Guinea'],
            ['country_name' => 'Eritrea'],
            ['country_name' => 'Estonia'],
            ['country_name' => 'Eswatini'],
            ['country_name' => 'Ethiopia'],
            ['country_name' => 'Fiji'],
            ['country_name' => 'Finland'],
            ['country_name' => 'France'],
            ['country_name' => 'Gabon'],
            ['country_name' => 'Gambia'],
            ['country_name' => 'Georgia'],
            ['country_name' => 'Germany'],
            ['country_name' => 'Ghana'],
            ['country_name' => 'Greece'],
            ['country_name' => 'Grenada'],
            ['country_name' => 'Guatemala'],
            ['country_name' => 'Guinea'],
            ['country_name' => 'Guinea-Bissau'],
            ['country_name' => 'Guyana'],
            ['country_name' => 'Haiti'],
            ['country_name' => 'Honduras'],
            ['country_name' => 'Hungary'],
            ['country_name' => 'Iceland'],
            ['country_name' => 'India'],
            ['country_name' => 'Indonesia'],
            ['country_name' => 'Iran'],
            ['country_name' => 'Iraq'],
            ['country_name' => 'Ireland'],
            ['country_name' => 'Italy'],
            ['country_name' => 'Ivory Coast'],
            ['country_name' => 'Jamaica'],
            ['country_name' => 'Japan'],
            ['country_name' => 'Jordan'],
            ['country_name' => 'Kazakhstan'],
            ['country_name' => 'Kenya'],
            ['country_name' => 'Kiribati'],
            ['country_name' => 'Kuwait'],
            ['country_name' => 'Kyrgyzstan'],
            ['country_name' => 'Laos'],
            ['country_name' => 'Latvia'],
            ['country_name' => 'Lebanon'],
            ['country_name' => 'Lesotho'],
            ['country_name' => 'Liberia'],
            ['country_name' => 'Libya'],
            ['country_name' => 'Liechtenstein'],
            ['country_name' => 'Lithuania'],
            ['country_name' => 'Luxembourg'],
            ['country_name' => 'Madagascar'],
            ['country_name' => 'Malawi'],
            ['country_name' => 'Malaysia'],
            ['country_name' => 'Maldives'],
            ['country_name' => 'Mali'],
            ['country_name' => 'Malta'],
            ['country_name' => 'Marshall Islands'],
            ['country_name' => 'Mauritania'],
            ['country_name' => 'Mauritius'],
            ['country_name' => 'Mexico'],
            ['country_name' => 'Micronesia'],
            ['country_name' => 'Moldova'],
            ['country_name' => 'Monaco'],
            ['country_name' => 'Mongolia'],
            ['country_name' => 'Montenegro'],
            ['country_name' => 'Morocco'],
            ['country_name' => 'Mozambique'],
            ['country_name' => 'Myanmar'],
            ['country_name' => 'Namibia'],
            ['country_name' => 'Nauru'],
            ['country_name' => 'Nepal'],
            ['country_name' => 'Netherlands'],
            ['country_name' => 'New Zealand'],
            ['country_name' => 'Nicaragua'],
            ['country_name' => 'Niger'],
            ['country_name' => 'Nigeria'],
            ['country_name' => 'North Korea'],
            ['country_name' => 'North Macedonia'],
            ['country_name' => 'Norway'],
            ['country_name' => 'Oman'],
            ['country_name' => 'Pakistan'],
            ['country_name' => 'Palau'],
            ['country_name' => 'Palestine'],
            ['country_name' => 'Panama'],
            ['country_name' => 'Papua New Guinea'],
            ['country_name' => 'Paraguay'],
            ['country_name' => 'Peru'],
            ['country_name' => 'Philippines'],
            ['country_name' => 'Poland'],
            ['country_name' => 'Portugal'],
            ['country_name' => 'Qatar'],
            ['country_name' => 'Romania'],
            ['country_name' => 'Russia'],
            ['country_name' => 'Rwanda'],
            ['country_name' => 'Saint Kitts and Nevis'],
            ['country_name' => 'Saint Lucia'],
            ['country_name' => 'Saint Vincent and the Grenadines'],
            ['country_name' => 'Samoa'],
            ['country_name' => 'San Marino'],
            ['country_name' => 'Sao Tome and Principe'],
            ['country_name' => 'Saudi Arabia'],
            ['country_name' => 'Senegal'],
            ['country_name' => 'Serbia'],
            ['country_name' => 'Seychelles'],
            ['country_name' => 'Sierra Leone'],
            ['country_name' => 'Singapore'],
            ['country_name' => 'Slovakia'],
            ['country_name' => 'Slovenia'],
            ['country_name' => 'Solomon Islands'],
            ['country_name' => 'Somalia'],
            ['country_name' => 'South Africa'],
            ['country_name' => 'South Korea'],
            ['country_name' => 'South Sudan'],
            ['country_name' => 'Spain'],
            ['country_name' => 'Sri Lanka'],
            ['country_name' => 'Sudan'],
            ['country_name' => 'Suriname'],
            ['country_name' => 'Sweden'],
            ['country_name' => 'Switzerland'],
            ['country_name' => 'Syria'],
            ['country_name' => 'Taiwan'],
            ['country_name' => 'Tajikistan'],
            ['country_name' => 'Tanzania'],
            ['country_name' => 'Thailand'],
            ['country_name' => 'Togo'],
            ['country_name' => 'Tonga'],
            ['country_name' => 'Trinidad and Tobago'],
            ['country_name' => 'Tunisia'],
            ['country_name' => 'Turkey'],
            ['country_name' => 'Turkmenistan'],
            ['country_name' => 'Tuvalu'],
            ['country_name' => 'Uganda'],
            ['country_name' => 'Ukraine'],
            ['country_name' => 'United Arab Emirates'],
            ['country_name' => 'United Kingdom'],
            ['country_name' => 'United States'],
            ['country_name' => 'Uruguay'],
            ['country_name' => 'Uzbekistan'],
            ['country_name' => 'Vanuatu'],
            ['country_name' => 'Vatican City'],
            ['country_name' => 'Venezuela'],
            ['country_name' => 'Vietnam'],
            ['country_name' => 'Yemen'],
            ['country_name' => 'Zambia'],
            ['country_name' => 'Zimbabwe'],
        ];

        foreach ($nationalities as $nationality) {
            Nationality::firstOrCreate($nationality);
        }

        $ageUnits = [
            ['unit_name' => 'Years'],
            ['unit_name' => 'Months'],
            ['unit_name' => 'Days'],
        ];

        foreach ($ageUnits as $ageUnit) {
            AgeUnit::firstOrCreate($ageUnit);
        }

        // Seeding Duration Units
        $duration_units = [
            ['unit' => 'Minutes'],
            ['unit' => 'Hours'],
            ['unit' => 'Days'],
        ];

        foreach ($duration_units as $duration_unit) {
            DurationUnit::firstOrCreate($duration_unit);
        }

        // Seeding Result Types
        $result_types = [
            ['result_type_name' => 'Number'],
            ['result_type_name' => 'Range Number'],
            ['result_type_name' => 'Text'],
            ['result_type_name' => 'Selection'],
        ];

        foreach ($result_types as $result_type) {
            ResultType::firstOrCreate($result_type);
        }

        // Seeding Answer Types
        $answer_types = [
            ['answer' => 'checkbox'],
            ['answer' => 'number'],
            ['answer' => 'date'],
            ['answer' => 'text'],
            ['answer' => 'selection'],
        ];

        foreach ($answer_types as $answer_type) {
            AnswerType::firstOrCreate($answer_type);
        }

        $result_states = [
            ['status' => 'high'],
            ['status' => 'normal'],
            ['status' => 'abnormal'],
            ['status' => 'low'],
            ['status' => 'ask doctor'],
        ];

        foreach ($result_states as $result_state) {
            ResultStatus::firstOrCreate($result_state);
        }

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
