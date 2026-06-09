<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Comprehensive database optimization with additional indexes and constraints
     */
    public function up(): void
    {
        // Optimize SCHEDULES table - add missing foreign key indexes
        Schema::table('schedules', function (Blueprint $table) {
            $table->index('bus_id');
            // Composite index for checking schedule availability by bus and date
            $table->index(['bus_id', 'departure_time']);
        });

        // Optimize ROUTES table - ensure foreign key indexes
        Schema::table('routes', function (Blueprint $table) {
            $table->index('departure_station_id');
            $table->index('arrival_station_id');
        });

        // Optimize USERS table - email index for login lookups
        Schema::table('users', function (Blueprint $table) {
            // Email is already unique but explicitly index for faster searches
            if (!Schema::hasColumn('users', 'phone')) {
                // Add phone if needed for future SMS features
            }
        });

        // Optimize BOOKINGS table - add composite indexes for common queries
        Schema::table('bookings', function (Blueprint $table) {
            // Composite index for user booking history with date filtering
            $table->index(['user_id', 'created_at']);
            
            // Composite index for schedule bookings with status
            $table->index(['schedule_id', 'status']);
            
            // Composite index for payment reconciliation
            $table->index(['status', 'payment_method']);
            
            // Index for email lookups (customer support queries)
            $table->index('passenger_email');
            
            // Index for phone lookups (SMS/contact tracing)
            $table->index('passenger_phone');
        });

        // Optimize PROMOTIONS table
        Schema::table('promotions', function (Blueprint $table) {
            // Already has unique on 'code' but ensure index for lookups
            if (!Schema::hasIndex('promotions', 'promotions_code_unique')) {
                // Code column already has unique constraint which provides index
            }
        });

        // Optimize SMS_CONFIGS table
        Schema::table('sms_configs', function (Blueprint $table) {
            // Index for finding active SMS config
            $table->index('is_active');
            
            // Index for gateway lookups
            $table->index('gateway_name');
        });

        // Optimize PERSONAL_ACCESS_TOKENS for API authentication
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Token lookups should be fast
            if (!Schema::hasIndex('personal_access_tokens', 'personal_access_tokens_token_index')) {
                $table->index('token');
            }
        });


        // Add full-text index on stations for search functionality (if using MySQL)
        if (DB::getDriverName() === 'mysql') {
            try {
                Schema::table('stations', function (Blueprint $table) {
                    $table->fullText('name');
                });
            } catch (\Exception $e) {
                // Full-text indexes might not be available in all MySQL versions
            }
        }

        // Optimize BUSES table - index for availability checks
        Schema::table('buses', function (Blueprint $table) {
            $table->index('coach_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new indexes from SCHEDULES
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['bus_id']);
            $table->dropIndex(['bus_id', 'departure_time']);
        });

        // Drop new indexes from ROUTES
        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex(['departure_station_id']);
            $table->dropIndex(['arrival_station_id']);
        });

        // Drop new indexes from BOOKINGS
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['schedule_id', 'status']);
            $table->dropIndex(['status', 'payment_method']);
            $table->dropIndex(['passenger_email']);
            $table->dropIndex(['passenger_phone']);
        });

        // Drop new indexes from SMS_CONFIGS
        Schema::table('sms_configs', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['gateway_name']);
        });

        // Drop full-text index from STATIONS
        if (DB::getDriverName() === 'mysql') {
            try {
                Schema::table('stations', function (Blueprint $table) {
                    $table->dropFullText('name');
                });
            } catch (\Exception $e) {
                // Ignore if full-text index doesn't exist
            }
        }

        // Drop new indexes from BUSES
        Schema::table('buses', function (Blueprint $table) {
            $table->dropIndex(['coach_number']);
        });
    }
};
