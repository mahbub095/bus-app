<?php

namespace Database\Seeders;

use App\Models\Route;
use Illuminate\Database\Seeder;

class RoutePointsSeeder extends Seeder
{
    public function run(): void
    {
        $defaultBoarding = [
            ['name' => 'Gabtoli', 'reporting_time' => '06:30 AM', 'departure_time' => '07:00 AM'],
            ['name' => 'Kalyanpur', 'reporting_time' => '06:50 AM', 'departure_time' => '07:20 AM'],
            ['name' => 'Kolabagan', 'reporting_time' => '07:00 AM', 'departure_time' => '07:30 AM'],
            ['name' => 'Panthapath', 'reporting_time' => '07:05 AM', 'departure_time' => '07:35 AM'],
            ['name' => 'Fakirapool', 'reporting_time' => '07:20 AM', 'departure_time' => '07:50 AM'],
            ['name' => 'Arambagh', 'reporting_time' => '07:25 AM', 'departure_time' => '07:55 AM'],
            ['name' => 'Sayedabad', 'reporting_time' => '07:40 AM', 'departure_time' => '08:10 AM'],
            ['name' => 'Abdullahpur', 'reporting_time' => '06:00 AM', 'departure_time' => '06:30 AM'],
            ['name' => 'Mohakhali', 'reporting_time' => '06:15 AM', 'departure_time' => '06:45 AM'],
        ];

        $defaultDropping = [
            ['name' => 'Dampara Bus Terminal', 'arrival_time' => '02:30 PM'],
            ['name' => 'AK Khan', 'arrival_time' => '02:10 PM'],
            ['name' => 'Oxygen Mor', 'arrival_time' => '02:20 PM'],
            ['name' => 'Bahaddarhat', 'arrival_time' => '02:45 PM'],
            ['name' => 'Alongkar', 'arrival_time' => '02:00 PM'],
            ['name' => 'Chawkbazar', 'arrival_time' => '03:00 PM'],
        ];

        Route::query()->each(function (Route $route) use ($defaultBoarding, $defaultDropping) {
            if (empty($route->boarding_points)) {
                $route->boarding_points = $defaultBoarding;
            }
            if (empty($route->dropping_points)) {
                $route->dropping_points = $defaultDropping;
            }
            $route->save();
        });
    }
}
