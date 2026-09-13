<?php

namespace Database\Seeders;

use App\Models\Lab;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@medicallab.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password123'),
                'phone_number' => '+1-555-000-0001',
                'address' => '100 Admin Tower, Medical District',
                'role_id' => 1,
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('Admin');

        // Create Main Lab Owner
        $labOwner = User::firstOrCreate(
            ['email' => 'lab@medicallab.com'],
            [
                'name' => 'Central Medical Laboratory',
                'password' => Hash::make('password123'),
                'phone_number' => '+1-555-100-0001',
                'address' => '500 Healthcare Boulevard, Medical Center',
                'role_id' => 2,
                'email_verified_at' => now(),
            ]
        );
        $labOwner->assignRole('Lab');

        // Create Lab record for main lab
        Lab::firstOrCreate(
            ['user_id_fk' => $labOwner->id],
            [
                'discount_percentage' => 10,
            ]
        );

        // Create Branch Labs
        $branches = [
            [
                'name' => 'Downtown Branch Laboratory',
                'email' => 'downtown@medicallab.com',
                'phone' => '+1-555-200-0001',
                'address' => '123 Downtown Street, City Center',
            ],
            [
                'name' => 'Westside Medical Lab',
                'email' => 'westside@medicallab.com',
                'phone' => '+1-555-200-0002',
                'address' => '456 West Avenue, Westside District',
            ],
            [
                'name' => 'Northgate Laboratory',
                'email' => 'northgate@medicallab.com',
                'phone' => '+1-555-200-0003',
                'address' => '789 North Road, Northgate Plaza',
            ],
        ];

        foreach ($branches as $branch) {
            $branchUser = User::firstOrCreate(
                ['email' => $branch['email']],
                [
                    'name' => $branch['name'],
                    'password' => Hash::make('password123'),
                    'phone_number' => $branch['phone'],
                    'address' => $branch['address'],
                    'role_id' => 4,
                    'creator_id' => $labOwner->id,
                    'email_verified_at' => now(),
                ]
            );
            $branchUser->assignRole('Branch Lab');

            Lab::firstOrCreate(
                ['user_id_fk' => $branchUser->id],
                [
                    'parent_lab_id_fk' => $labOwner->id,
                    'discount_percentage' => 5,
                ]
            );
        }

        // Create Doctors (Referrals)
        $doctors = [
            [
                'name' => 'Dr. Ahmed Hassan',
                'email' => 'dr.ahmed@hospital.com',
                'phone' => '+1-555-300-0001',
                'address' => 'City General Hospital, Room 301',
            ],
            [
                'name' => 'Dr. Sarah Mitchell',
                'email' => 'dr.sarah@clinic.com',
                'phone' => '+1-555-300-0002',
                'address' => 'Mitchell Family Clinic, Suite 105',
            ],
            [
                'name' => 'Dr. Mohammed Ali',
                'email' => 'dr.mohammed@medcenter.com',
                'phone' => '+1-555-300-0003',
                'address' => 'Medical Center, Building B',
            ],
            [
                'name' => 'Dr. Emily Chen',
                'email' => 'dr.emily@wellness.com',
                'phone' => '+1-555-300-0004',
                'address' => 'Wellness Health Center',
            ],
            [
                'name' => 'Dr. James Wilson',
                'email' => 'dr.james@cardiac.com',
                'phone' => '+1-555-300-0005',
                'address' => 'Cardiac Care Specialists',
            ],
        ];

        foreach ($doctors as $doctor) {
            $doctorUser = User::firstOrCreate(
                ['email' => $doctor['email']],
                [
                    'name' => $doctor['name'],
                    'password' => Hash::make('password123'),
                    'phone_number' => $doctor['phone'],
                    'address' => $doctor['address'],
                    'role_id' => 5,
                    'creator_id' => $labOwner->id,
                    'email_verified_at' => now(),
                ]
            );
            $doctorUser->assignRole('Doctor');
        }

        // Create Sample Collectors
        $collectors = [
            [
                'name' => 'John Smith',
                'email' => 'john.collector@medicallab.com',
                'phone' => '+1-555-400-0001',
                'address' => '100 Collector Lane',
            ],
            [
                'name' => 'Maria Garcia',
                'email' => 'maria.collector@medicallab.com',
                'phone' => '+1-555-400-0002',
                'address' => '200 Sample Street',
            ],
            [
                'name' => 'David Brown',
                'email' => 'david.collector@medicallab.com',
                'phone' => '+1-555-400-0003',
                'address' => '300 Collection Ave',
            ],
        ];

        foreach ($collectors as $collector) {
            $collectorUser = User::firstOrCreate(
                ['email' => $collector['email']],
                [
                    'name' => $collector['name'],
                    'password' => Hash::make('password123'),
                    'phone_number' => $collector['phone'],
                    'address' => $collector['address'],
                    'role_id' => 6,
                    'creator_id' => $labOwner->id,
                    'email_verified_at' => now(),
                ]
            );
            $collectorUser->assignRole('Sample Collector');
        }

        // Create Staff Users
        $staff = [
            [
                'name' => 'Reception Staff - Anna',
                'email' => 'anna.staff@medicallab.com',
                'phone' => '+1-555-500-0001',
            ],
            [
                'name' => 'Lab Technician - Mike',
                'email' => 'mike.tech@medicallab.com',
                'phone' => '+1-555-500-0002',
            ],
        ];

        foreach ($staff as $member) {
            $staffUser = User::firstOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => Hash::make('password123'),
                    'phone_number' => $member['phone'],
                    'role_id' => 7,
                    'creator_id' => $labOwner->id,
                    'email_verified_at' => now(),
                ]
            );
            $staffUser->assignRole('User');
        }
    }
}
