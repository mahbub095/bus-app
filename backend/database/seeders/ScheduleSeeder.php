<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Route;
use App\Models\Schedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $buses = Bus::all();
        $routes = Route::all();

        if ($buses->isEmpty() || $routes->isEmpty()) {
            return;
        }

        $times = [
            ['dep' => '07:30:00', 'arr' => '13:00:00', 'fare' => 900.00],
            ['dep' => '10:00:00', 'arr' => '15:30:00', 'fare' => 850.00],
            ['dep' => '14:30:00', 'arr' => '20:00:00', 'fare' => 950.00],
            ['dep' => '21:00:00', 'arr' => '02:30:00', 'fare' => 1200.00],
            ['dep' => '23:30:00', 'arr' => '05:00:00', 'fare' => 1000.00]
        ];

        $today = Carbon::today();
        
        foreach ($routes as $route) {
            for ($dayOffset = 0; $dayOffset <= 7; $dayOffset++) {
                $date = $today->copy()->addDays($dayOffset);
                
                foreach ($times as $index => $t) {
                    $busIndex = ($route->id + $dayOffset + $index) % $buses->count();
                    $bus = $buses[$busIndex];
                    
                    $fare = $t['fare'];
                    if ($bus->coach_type === 'AC') {
                        $fare += 400.00;
                    }

                    $depDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $t['dep']);
                    $arrDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $t['arr']);
                    
                    if ($arrDateTime->lt($depDateTime)) {
                        $arrDateTime->addDay();
                    }

                    Schedule::create([
                        'bus_id' => $bus->id,
                        'route_id' => $route->id,
                        'departure_time' => $depDateTime,
                        'arrival_time' => $arrDateTime,
                        'fare' => $fare
                    ]);
                }
            }
        }
    }
}
