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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')->constrained('buses')->onDelete('cascade');
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->dateTime('departure_time');
            $table->dateTime('arrival_time');
            $table->decimal('fare', 8, 2);
            $table->text('blocked_seats')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('bus_id');
            $table->index('route_id');
            $table->index('departure_time');
            $table->index(['route_id', 'departure_time']);
            $table->index(['bus_id', 'departure_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
