<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PatientsSeeder extends Seeder
{
    public function run(): void
    {
        $labOwner = User::where('email', 'lab@medicallab.com')->first();

        if (!$labOwner) {
            $this->command->error('Lab owner not found. Run UsersSeeder first.');
            return;
        }

        $patients = $this->getPatientData();

        foreach ($patients as $patientData) {
            // Create user account for patient
            $user = User::firstOrCreate(
                ['email' => $patientData['email']],
                [
                    'name' => $patientData['name'],
                    'password' => Hash::make('patient123'),
                    'phone_number' => $patientData['phone'],
                    'address' => $patientData['address'],
                    'role_id' => 3, // Patient role
                    'creator_id' => $labOwner->id,
                    'email_verified_at' => now(),
                ]
            );
            $user->assignRole('Patient');

            // Create patient record
            Patient::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'code' => $patientData['code'],
                    'title_id_fk' => $patientData['title'],
                    'gender_id_fk' => $patientData['gender'],
                    'nationality_id_fk' => $patientData['nationality'],
                    'dob' => $patientData['dob'],
                    'age' => $patientData['age'],
                    'age_unit_id_fk' => 1, // Years
                    'address' => $patientData['address'],
                    'national_id_no' => $patientData['national_id'],
                    'passport_no' => $patientData['passport'] ?? null,
                    'barcode' => 'PAT' . str_pad($patientData['code'], 8, '0', STR_PAD_LEFT),
                    'creator_id' => $labOwner->id,
                    'parent_id' => $labOwner->id,
                    'contract_id_fk' => $patientData['contract'] ?? null,
                ]
            );
        }

        $this->command->info('Created ' . count($patients) . ' patients with user accounts.');
    }

    private function getPatientData(): array
    {
        return [
            // Adult Males
            [
                'code' => '10001',
                'name' => 'Ahmed Hassan Mohammed',
                'email' => 'ahmed.hassan@email.com',
                'phone' => '+1-555-101-0001',
                'address' => '123 Oak Street, Downtown',
                'title' => 1, // Mr
                'gender' => 1, // Male
                'nationality' => 151, // Saudi Arabia
                'dob' => '1985-03-15',
                'age' => 39,
                'national_id' => 1234567890,
                'passport' => 'SA12345678',
                'contract' => 1,
            ],
            [
                'code' => '10002',
                'name' => 'Mohammed Ali Ibrahim',
                'email' => 'mohammed.ali@email.com',
                'phone' => '+1-555-101-0002',
                'address' => '456 Pine Avenue, Medical District',
                'title' => 1,
                'gender' => 1,
                'nationality' => 151, // Saudi Arabia
                'dob' => '1978-07-22',
                'age' => 46,
                'national_id' => 2345678901,
            ],
            [
                'code' => '10003',
                'name' => 'Khalid Omar Nasser',
                'email' => 'khalid.omar@email.com',
                'phone' => '+1-555-101-0003',
                'address' => '789 Cedar Lane, Westside',
                'title' => 1,
                'gender' => 1,
                'nationality' => 151, // Saudi Arabia
                'dob' => '1992-11-08',
                'age' => 32,
                'national_id' => 3456789012,
                'contract' => 2,
            ],
            [
                'code' => '10004',
                'name' => 'Faisal Abdullah Ahmad',
                'email' => 'faisal.abdullah@email.com',
                'phone' => '+1-555-101-0004',
                'address' => '321 Maple Drive, North District',
                'title' => 1,
                'gender' => 1,
                'nationality' => 52, // Egypt
                'dob' => '1965-04-30',
                'age' => 59,
                'national_id' => 4567890123,
                'passport' => 'EG87654321',
            ],
            [
                'code' => '10005',
                'name' => 'Youssef Hamad Salem',
                'email' => 'youssef.hamad@email.com',
                'phone' => '+1-555-101-0005',
                'address' => '654 Birch Road, East Side',
                'title' => 1,
                'gender' => 1,
                'nationality' => 86, // Jordan
                'dob' => '1988-09-12',
                'age' => 36,
                'national_id' => 5678901234,
            ],
            [
                'code' => '10006',
                'name' => 'Omar Saeed Rashid',
                'email' => 'omar.saeed@email.com',
                'phone' => '+1-555-101-0006',
                'address' => '987 Willow Street, South Area',
                'title' => 1,
                'gender' => 1,
                'nationality' => 184, // UAE
                'dob' => '1975-12-25',
                'age' => 49,
                'national_id' => 6789012345,
                'contract' => 3,
            ],
            [
                'code' => '10007',
                'name' => 'Tariq Mansour Ali',
                'email' => 'tariq.mansour@email.com',
                'phone' => '+1-555-101-0007',
                'address' => '147 Elm Court, Central',
                'title' => 1,
                'gender' => 1,
                'nationality' => 90, // Kuwait
                'dob' => '1995-02-18',
                'age' => 29,
                'national_id' => 7890123456,
            ],
            [
                'code' => '10008',
                'name' => 'Nasser Fahad Hassan',
                'email' => 'nasser.fahad@email.com',
                'phone' => '+1-555-101-0008',
                'address' => '258 Spruce Lane, Business District',
                'title' => 1,
                'gender' => 1,
                'nationality' => 13, // Bahrain
                'dob' => '1982-06-05',
                'age' => 42,
                'national_id' => 8901234567,
            ],

            // Adult Females
            [
                'code' => '10009',
                'name' => 'Fatima Ahmed Mohammed',
                'email' => 'fatima.ahmed@email.com',
                'phone' => '+1-555-102-0001',
                'address' => '369 Rose Street, Residential Area',
                'title' => 2, // Mrs
                'gender' => 2, // Female
                'nationality' => 151, // Saudi Arabia
                'dob' => '1990-08-20',
                'age' => 34,
                'national_id' => 9012345678,
                'contract' => 1,
            ],
            [
                'code' => '10010',
                'name' => 'Sara Hassan Ali',
                'email' => 'sara.hassan@email.com',
                'phone' => '+1-555-102-0002',
                'address' => '741 Jasmine Avenue, Garden District',
                'title' => 2,
                'gender' => 2,
                'nationality' => 151, // Saudi Arabia
                'dob' => '1987-01-14',
                'age' => 37,
                'national_id' => 1123456789,
            ],
            [
                'code' => '10011',
                'name' => 'Maryam Khalid Nasser',
                'email' => 'maryam.khalid@email.com',
                'phone' => '+1-555-102-0003',
                'address' => '852 Lily Lane, Family Zone',
                'title' => 2,
                'gender' => 2,
                'nationality' => 52, // Egypt
                'dob' => '1993-05-28',
                'age' => 31,
                'national_id' => 2234567890,
                'contract' => 2,
            ],
            [
                'code' => '10012',
                'name' => 'Aisha Omar Salem',
                'email' => 'aisha.omar@email.com',
                'phone' => '+1-555-102-0004',
                'address' => '963 Daisy Drive, Quiet Suburb',
                'title' => 2,
                'gender' => 2,
                'nationality' => 86, // Jordan
                'dob' => '1970-10-03',
                'age' => 54,
                'national_id' => 3345678901,
            ],
            [
                'code' => '10013',
                'name' => 'Nora Abdullah Hassan',
                'email' => 'nora.abdullah@email.com',
                'phone' => '+1-555-102-0005',
                'address' => '174 Tulip Street, Green Valley',
                'title' => 2,
                'gender' => 2,
                'nationality' => 94, // Lebanon
                'dob' => '1985-12-11',
                'age' => 39,
                'national_id' => 4456789012,
            ],
            [
                'code' => '10014',
                'name' => 'Layla Faisal Ahmad',
                'email' => 'layla.faisal@email.com',
                'phone' => '+1-555-102-0006',
                'address' => '285 Orchid Road, Hillside',
                'title' => 2,
                'gender' => 2,
                'nationality' => 151, // Saudi Arabia
                'dob' => '1998-04-07',
                'age' => 26,
                'national_id' => 5567890123,
            ],
            [
                'code' => '10015',
                'name' => 'Hana Youssef Mohammed',
                'email' => 'hana.youssef@email.com',
                'phone' => '+1-555-102-0007',
                'address' => '396 Violet Court, Riverside',
                'title' => 2,
                'gender' => 2,
                'nationality' => 131, // Pakistan
                'dob' => '1979-07-19',
                'age' => 45,
                'national_id' => 6678901234,
                'passport' => 'PK11223344',
            ],
            [
                'code' => '10016',
                'name' => 'Reem Tariq Ali',
                'email' => 'reem.tariq@email.com',
                'phone' => '+1-555-102-0008',
                'address' => '417 Peony Lane, Lake View',
                'title' => 2,
                'gender' => 2,
                'nationality' => 184, // UAE
                'dob' => '1991-02-23',
                'age' => 33,
                'national_id' => 7789012345,
                'contract' => 3,
            ],

            // Children
            [
                'code' => '10017',
                'name' => 'Hassan Ahmed Mohammed',
                'email' => 'hassan.child@email.com',
                'phone' => '+1-555-103-0001',
                'address' => '528 Sunflower Street, Family Area',
                'title' => 3, // Child
                'gender' => 1,
                'nationality' => 151, // Saudi Arabia
                'dob' => '2015-06-15',
                'age' => 9,
                'national_id' => 8890123456,
            ],
            [
                'code' => '10018',
                'name' => 'Zainab Khalid Salem',
                'email' => 'zainab.child@email.com',
                'phone' => '+1-555-103-0002',
                'address' => '639 Marigold Avenue, School District',
                'title' => 3,
                'gender' => 2,
                'nationality' => 151, // Saudi Arabia
                'dob' => '2018-09-22',
                'age' => 6,
                'national_id' => 9901234567,
            ],
            [
                'code' => '10019',
                'name' => 'Ali Omar Hassan',
                'email' => 'ali.child@email.com',
                'phone' => '+1-555-103-0003',
                'address' => '740 Daffodil Lane, Playground Area',
                'title' => 3,
                'gender' => 1,
                'nationality' => 52, // Egypt
                'dob' => '2020-03-10',
                'age' => 4,
                'national_id' => 1012345678,
            ],
            [
                'code' => '10020',
                'name' => 'Mariam Faisal Ali',
                'email' => 'mariam.child@email.com',
                'phone' => '+1-555-103-0004',
                'address' => '851 Clover Court, Park View',
                'title' => 3,
                'gender' => 2,
                'nationality' => 86, // Jordan
                'dob' => '2012-11-05',
                'age' => 12,
                'national_id' => 1123456780,
            ],

            // Elderly Patients
            [
                'code' => '10021',
                'name' => 'Ibrahim Abdullah Nasser',
                'email' => 'ibrahim.elderly@email.com',
                'phone' => '+1-555-104-0001',
                'address' => '962 Senior Lane, Retirement Community',
                'title' => 1,
                'gender' => 1,
                'nationality' => 151, // Saudi Arabia
                'dob' => '1950-01-25',
                'age' => 74,
                'national_id' => 1234567891,
                'contract' => 1,
            ],
            [
                'code' => '10022',
                'name' => 'Khadija Hassan Omar',
                'email' => 'khadija.elderly@email.com',
                'phone' => '+1-555-104-0002',
                'address' => '173 Golden Years Drive, Care Center',
                'title' => 2,
                'gender' => 2,
                'nationality' => 151, // Saudi Arabia
                'dob' => '1945-08-12',
                'age' => 79,
                'national_id' => 2345678902,
            ],
            [
                'code' => '10023',
                'name' => 'Saleh Mohammed Ahmed',
                'email' => 'saleh.elderly@email.com',
                'phone' => '+1-555-104-0003',
                'address' => '284 Wisdom Street, Heritage District',
                'title' => 1,
                'gender' => 1,
                'nationality' => 52, // Egypt
                'dob' => '1948-04-30',
                'age' => 76,
                'national_id' => 3456789013,
            ],

            // International Patients
            [
                'code' => '10024',
                'name' => 'John William Smith',
                'email' => 'john.smith@email.com',
                'phone' => '+1-555-105-0001',
                'address' => '395 International Blvd, Expat Zone',
                'title' => 1,
                'gender' => 1,
                'nationality' => 185, // UK
                'dob' => '1980-07-04',
                'age' => 44,
                'national_id' => 4567890124,
                'passport' => 'UK99887766',
            ],
            [
                'code' => '10025',
                'name' => 'Emily Rose Johnson',
                'email' => 'emily.johnson@email.com',
                'phone' => '+1-555-105-0002',
                'address' => '406 Global Street, Diplomatic Quarter',
                'title' => 2,
                'gender' => 2,
                'nationality' => 186, // USA
                'dob' => '1988-11-28',
                'age' => 36,
                'national_id' => 5678901235,
                'passport' => 'US55443322',
                'contract' => 2,
            ],
            [
                'code' => '10026',
                'name' => 'Raj Kumar Patel',
                'email' => 'raj.patel@email.com',
                'phone' => '+1-555-105-0003',
                'address' => '517 World Trade Center, Business Hub',
                'title' => 1,
                'gender' => 1,
                'nationality' => 77, // India
                'dob' => '1975-03-15',
                'age' => 49,
                'national_id' => 6789012346,
                'passport' => 'IN77889900',
            ],
            [
                'code' => '10027',
                'name' => 'Maria Santos Garcia',
                'email' => 'maria.garcia@email.com',
                'phone' => '+1-555-105-0004',
                'address' => '628 Embassy Road, Consular District',
                'title' => 2,
                'gender' => 2,
                'nationality' => 138, // Philippines
                'dob' => '1992-05-20',
                'age' => 32,
                'national_id' => 7890123457,
                'passport' => 'PH11223355',
            ],
            [
                'code' => '10028',
                'name' => 'Chen Wei Zhang',
                'email' => 'chen.zhang@email.com',
                'phone' => '+1-555-105-0005',
                'address' => '739 Trade Center, Financial District',
                'title' => 1,
                'gender' => 1,
                'nationality' => 36, // China
                'dob' => '1983-09-08',
                'age' => 41,
                'national_id' => 8901234568,
                'passport' => 'CN44556677',
                'contract' => 3,
            ],
            [
                'code' => '10029',
                'name' => 'Fatou Amadou Diallo',
                'email' => 'fatou.diallo@email.com',
                'phone' => '+1-555-105-0006',
                'address' => '840 African Quarter, Cultural Center',
                'title' => 2,
                'gender' => 2,
                'nationality' => 152, // Senegal
                'dob' => '1995-12-02',
                'age' => 29,
                'national_id' => 9012345679,
                'passport' => 'SN88990011',
            ],
            [
                'code' => '10030',
                'name' => 'Abdullah Rashid Al-Maktoum',
                'email' => 'abdullah.rashid@email.com',
                'phone' => '+1-555-101-0030',
                'address' => '100 VIP Boulevard, Premium District',
                'title' => 1,
                'gender' => 1,
                'nationality' => 184, // UAE
                'dob' => '1972-06-18',
                'age' => 52,
                'national_id' => 1122334455,
                'passport' => 'AE12121212',
                'contract' => 1,
            ],
        ];
    }
}
