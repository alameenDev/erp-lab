<?php

namespace Database\Seeders;

use App\Models\PriceList;
use App\Models\Referal;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReferralsSeeder extends Seeder
{
    public function run(): void
    {
        $labOwner = User::where('email', 'lab@medicallab.com')->first();

        if (!$labOwner) {
            $this->command->error('Lab owner not found. Run UsersSeeder first.');
            return;
        }

        $labId = $labOwner->id;

        // Get all doctors
        $doctors = User::where('role_id', 5)->get();

        // Get price lists
        $priceLists = PriceList::where('lab_id_fk', $labId)->get();
        $doctorPriceList = $priceLists->where('name', 'Referral Doctor Discount')->first();

        $commissions = [10, 15, 20, 25, 30];

        foreach ($doctors as $doctor) {
            Referal::firstOrCreate(
                [
                    'lab_id_fk' => $labId,
                    'referral_id_fk' => $doctor->id,
                ],
                [
                    'commission' => $commissions[array_rand($commissions)],
                    'price_list_id_fk' => $doctorPriceList?->id,
                ]
            );
        }

        $this->command->info('Created referral relationships for ' . $doctors->count() . ' doctors.');

        // Get all branch labs (role_id = 4 for Branch_lab role)
        $branchLabs = User::where('role_id', 4)->get();
        $labPriceList = $priceLists->where('name', 'Standard Price List')->first();

        foreach ($branchLabs as $branchLab) {
            Referal::firstOrCreate(
                [
                    'lab_id_fk' => $labId,
                    'referral_id_fk' => $branchLab->id,
                ],
                [
                    'commission' => $commissions[array_rand($commissions)],
                    'price_list_id_fk' => $labPriceList?->id,
                ]
            );
        }

        $this->command->info('Created referral relationships for ' . $branchLabs->count() . ' branch labs.');
    }
}
