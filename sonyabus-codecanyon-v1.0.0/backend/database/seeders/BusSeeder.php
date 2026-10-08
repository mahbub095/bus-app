<?php

namespace Database\Seeders;

use App\Models\Bus;
use Illuminate\Database\Seeder;

class BusSeeder extends Seeder
{
    public function run(): void
    {
        $busesData = [
            ['operator_name' => 'Sonya Enterprise', 'coach_number' => 'SE-4029', 'coach_type' => 'AC', 'total_seats' => 36],
            ['operator_name' => 'Sonya Enterprise', 'coach_number' => 'SE-1090', 'coach_type' => 'Non AC', 'total_seats' => 36],
            ['operator_name' => 'Hanif Enterprise', 'coach_number' => 'HE-8820', 'coach_type' => 'AC', 'total_seats' => 36],
            ['operator_name' => 'Hanif Enterprise', 'coach_number' => 'HE-4152', 'coach_type' => 'Non AC', 'total_seats' => 36],
            ['operator_name' => 'Green Line Paribahan', 'coach_number' => 'GL-3345', 'coach_type' => 'AC', 'total_seats' => 36],
            ['operator_name' => 'Shyamoli Paribahan', 'coach_number' => 'SP-9912', 'coach_type' => 'Non AC', 'total_seats' => 36],
            ['operator_name' => 'Ena Transport', 'coach_number' => 'ET-7711', 'coach_type' => 'Non AC', 'total_seats' => 36],
        ];

        foreach ($busesData as $data) {
            Bus::updateOrCreate(['coach_number' => $data['coach_number']], $data);
        }
    }
}
