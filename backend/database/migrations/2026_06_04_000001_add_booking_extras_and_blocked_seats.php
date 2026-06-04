<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('passenger_gender', ['M', 'F'])->default('M')->after('passenger_email');
            $table->string('boarding_point')->nullable()->after('passenger_gender');
            $table->string('dropping_point')->nullable()->after('boarding_point');
            $table->string('seat_class', 50)->default('E-Class')->after('dropping_point');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->text('blocked_seats')->nullable()->after('fare');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['passenger_gender', 'boarding_point', 'dropping_point', 'seat_class']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('blocked_seats');
        });
    }
};
