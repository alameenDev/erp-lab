<?php

namespace Database\Seeders;

use App\Models\DiscountType;
use Illuminate\Database\Seeder;

class DiscountTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['id' => 1, 'type' => 'none'],
            ['id' => 2, 'type' => 'percentage'],
            ['id' => 3, 'type' => 'fixed'],
        ];

        foreach ($types as $row) {
            DiscountType::firstOrCreate(['id' => $row['id']], ['type' => $row['type']]);
        }
    }
}
