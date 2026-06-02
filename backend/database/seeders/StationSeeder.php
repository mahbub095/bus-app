<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    public function run(): void
    {
        $stationsData = [
            ['name' => 'DHAKA', 'district' => 'Dhaka'],
            ['name' => 'CHITTAGONG', 'district' => 'Chittagong'],
            ['name' => 'COX\'S BAZAR', 'district' => 'Cox\'s Bazar'],
            ['name' => 'RAJSHAHI', 'district' => 'Rajshahi'],
            ['name' => 'SYEDPUR', 'district' => 'Nilphamari'],
            ['name' => 'RANGPUR', 'district' => 'Rangpur'],
            ['name' => 'TANGAIL', 'district' => 'Tangail'],
            ['name' => 'SIRAJGANJ', 'district' => 'Sirajganj'],
            ['name' => 'NATORE', 'district' => 'Natore'],
            ['name' => 'SAVAR', 'district' => 'Dhaka'],
            ['name' => 'FENI', 'district' => 'Feni'],
            ['name' => 'COMILLA(CUMILLA)', 'district' => 'Comilla'],
        ];

        foreach ($stationsData as $data) {
            Station::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}
