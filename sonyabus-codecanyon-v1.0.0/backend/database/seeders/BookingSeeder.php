<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $schedules = Schedule::limit(5)->get();

        if ($schedules->isEmpty()) {
            return;
        }

        foreach ($schedules as $index => $sched) {
            // Alternate some active and cancelled seats
            Booking::create([
                'schedule_id' => $sched->id,
                'passenger_name' => 'Fahim Rahman',
                'passenger_phone' => '01712345678',
                'passenger_email' => 'fahim@gmail.com',
                'passenger_gender' => 'M',
                'seat_numbers' => 'A1,A2',
                'total_fare' => $sched->fare * 2,
                'payment_method' => 'bKash',
                'status' => 'PAID',
            ]);

            Booking::create([
                'schedule_id' => $sched->id,
                'passenger_name' => 'Tania Islam',
                'passenger_phone' => '01987654321',
                'passenger_email' => 'tania@yahoo.com',
                'passenger_gender' => 'F',
                'seat_numbers' => 'B3',
                'total_fare' => $sched->fare,
                'payment_method' => 'Cash',
                'status' => 'PAID',
            ]);

            Booking::create([
                'schedule_id' => $sched->id,
                'passenger_name' => 'Rahim Ali',
                'passenger_phone' => '01899001122',
                'passenger_email' => 'rahim@outlook.com',
                'seat_numbers' => 'C1,C2',
                'total_fare' => $sched->fare * 2,
                'payment_method' => 'Card',
                'status' => 'CANCELLED'
            ]);
        }
    }
}
