<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE bookings
                MODIFY status ENUM('PAID', 'CANCEL_REQUESTED', 'CANCELLED')
                NOT NULL DEFAULT 'PAID'
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE bookings
                SET status = 'PAID'
                WHERE status = 'CANCEL_REQUESTED'
            ");

            DB::statement("
                ALTER TABLE bookings
                MODIFY status ENUM('PAID', 'CANCELLED')
                NOT NULL DEFAULT 'PAID'
            ");
        }
    }
};
