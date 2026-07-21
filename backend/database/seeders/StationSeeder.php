<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    public function run(): void
    {
        $stations = [
            'DHAKA',
            'CHITTAGONG',
            "COX'S BAZAR",
            'RAJSHAHI',
            'SYEDPUR',
            'RANGPUR',
            'TANGAIL',
            'SIRAJGANJ',
            'NATORE',
            'SAVAR',
            'FENI',
            'COMILLA(CUMILLA)',
        ];

        foreach ($stations as $name) {
            Station::updateOrCreate(['name' => $name]);
        }
    }
}
