<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->string('passenger_name');
            $table->string('passenger_phone');
            $table->string('passenger_email');
            $table->string('seat_numbers'); // Comma-separated like A1,A2 or B3
            $table->decimal('total_fare', 10, 2);
            $table->string('payment_method')->default('bKash');
            $table->enum('status', ['PAID', 'CANCELLED'])->default('PAID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
