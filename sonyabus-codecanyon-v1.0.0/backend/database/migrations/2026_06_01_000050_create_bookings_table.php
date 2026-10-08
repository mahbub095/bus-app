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
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->string('passenger_name');
            $table->string('passenger_phone');
            $table->string('passenger_email');
            $table->enum('passenger_gender', ['M', 'F'])->default('M');
            $table->string('boarding_point')->nullable();
            $table->string('dropping_point')->nullable();
            $table->string('seat_class', 50)->default('E-Class');
            $table->string('seat_numbers'); // Comma-separated like A1,A2 or B3
            $table->decimal('total_fare', 10, 2);
            $table->string('payment_method')->default('bKash');
            $table->string('payment_invoice_id')->nullable();
            $table->enum('status', ['PENDING', 'PAID', 'SOLD', 'BOOKED', 'CANCEL_REQUESTED', 'CANCELLED'])->default('PENDING');
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('schedule_id');
            $table->index('status');
            $table->index('passenger_email');
            $table->index('passenger_phone');
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['schedule_id', 'status']);
            $table->index(['status', 'payment_method']);
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
