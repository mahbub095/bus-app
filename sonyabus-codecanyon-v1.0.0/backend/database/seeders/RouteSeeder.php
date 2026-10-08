<?php

namespace Database\Seeders;

use App\Models\Route;
use App\Models\Station;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    public function run(): void
    {
        $stations = Station::all()->keyBy('name');

        $routesData = [
            [
                'dep' => 'DHAKA', 'arr' => 'CHITTAGONG',
                'distance' => '250 km', 'duration' => '5.5 Hours'
            ],
            [
                'dep' => 'CHITTAGONG', 'arr' => 'DHAKA',
                'distance' => '250 km', 'duration' => '5.5 Hours'
            ],
            [
                'dep' => 'DHAKA', 'arr' => 'COX\'S BAZAR',
                'distance' => '400 km', 'duration' => '8.5 Hours'
            ],
            [
                'dep' => 'COX\'S BAZAR', 'arr' => 'DHAKA',
                'distance' => '400 km', 'duration' => '8.5 Hours'
            ],
            [
                'dep' => 'DHAKA', 'arr' => 'RAJSHAHI',
                'distance' => '260 km', 'duration' => '6 Hours'
            ],
            [
                'dep' => 'DHAKA', 'arr' => 'SYEDPUR',
                'distance' => '320 km', 'duration' => '7.5 Hours'
            ],
            [
                'dep' => 'DHAKA', 'arr' => 'RANGPUR',
                'distance' => '300 km', 'duration' => '7 Hours'
            ],
        ];

        foreach ($routesData as $rData) {
            $depStation = $stations[$rData['dep']] ?? null;
            $arrStation = $stations[$rData['arr']] ?? null;

            if ($depStation && $arrStation) {
                Route::updateOrCreate([
                    'departure_station_id' => $depStation->id,
                    'arrival_station_id' => $arrStation->id
                ], [
                    'distance' => $rData['distance'],
                    'duration' => $rData['duration']
                ]);
            }
        }
    }
}
