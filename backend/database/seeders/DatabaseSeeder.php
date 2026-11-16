<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            StationSeeder::class,
            BusSeeder::class,
            RouteSeeder::class,
            RoutePointsSeeder::class,
            ScheduleSeeder::class,
            BookingSeeder::class,
            PromotionSeeder::class,
            SmsConfigSeeder::class,
        ]);
    }
}
