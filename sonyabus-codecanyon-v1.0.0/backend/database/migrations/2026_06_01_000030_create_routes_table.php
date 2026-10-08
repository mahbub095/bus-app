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
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departure_station_id')->constrained('stations')->onDelete('cascade');
            $table->foreignId('arrival_station_id')->constrained('stations')->onDelete('cascade');
            $table->string('distance')->nullable();
            $table->string('duration')->nullable();
            $table->json('boarding_points')->nullable();
            $table->json('dropping_points')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('departure_station_id');
            $table->index('arrival_station_id');
            $table->index(['departure_station_id', 'arrival_station_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
