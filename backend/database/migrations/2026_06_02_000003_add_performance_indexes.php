<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes for better query performance
     */
    public function up(): void
    {
        // Add indexes to bookings table
        Schema::table('bookings', function (Blueprint $table) {
            // Index for user bookings queries
            $table->index('user_id');
            
            // Index for seat conflict checks
            $table->index('schedule_id');
            
            // Index for status filtering
            $table->index('status');
            
            // Composite index for common filtering
            $table->index(['status', 'created_at']);
        });

        // Add indexes to schedules table
        Schema::table('schedules', function (Blueprint $table) {
            // Index for route-based searches
            $table->index('route_id');
            
            // Index for date range queries
            $table->index('departure_time');
            
            // Composite index for common search pattern
            $table->index(['route_id', 'departure_time']);
        });

        // Add indexes to routes table
        Schema::table('routes', function (Blueprint $table) {
            // Composite index for finding routes by stations
            $table->index(['departure_station_id', 'arrival_station_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['schedule_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['route_id']);
            $table->dropIndex(['departure_time']);
            $table->dropIndex(['route_id', 'departure_time']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex(['departure_station_id', 'arrival_station_id']);
        });
    }
};
