<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        Promotion::updateOrCreate(
            ['code' => 'SONYANEW'],
            [
                'discount_amount' => 150.00,
                'description' => '150 BDT discount for your first ticket reservation'
            ]
        );

        Promotion::updateOrCreate(
            ['code' => 'TRAVEL2026'],
            [
                'discount_amount' => 250.00,
                'description' => '250 BDT flat discount for summer travel'
            ]
        );
    }
}
