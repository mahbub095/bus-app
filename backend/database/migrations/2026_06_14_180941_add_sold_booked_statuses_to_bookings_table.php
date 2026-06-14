<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('PENDING', 'PAID', 'SOLD', 'BOOKED', 'CANCEL_REQUESTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('PENDING', 'PAID', 'CANCEL_REQUESTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING'");
        }
    }
};
