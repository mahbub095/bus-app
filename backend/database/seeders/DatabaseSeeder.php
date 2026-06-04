<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Admin User
        User::updateOrCreate(
            ['email' => 'admin@sonyabus.com'],
            [
                'name' => 'Sonya Admin',
                'password' => bcrypt('password123'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@sonyabus.com'],
            [
                'name' => 'Demo Customer',
                'password' => bcrypt('password123'),
                'role' => 'user',
            ]
        );

        // 2. Call Modular Entity Seeders in Order
        $this->call([
            StationSeeder::class,
            BusSeeder::class,
            RouteSeeder::class,
            RoutePointsSeeder::class,
            ScheduleSeeder::class,
            BookingSeeder::class,
            PromotionSeeder::class,
        ]);
    }
}
