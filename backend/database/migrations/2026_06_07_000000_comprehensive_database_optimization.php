<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Comprehensive database optimization with additional indexes and constraints
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->index('bus_id');
            $table->index(['bus_id', 'departure_time']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->index('departure_station_id');
            $table->index('arrival_station_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index(['schedule_id', 'status']);
            $table->index(['status', 'payment_method']);
            $table->index('passenger_email');
            $table->index('passenger_phone');
        });

        Schema::table('sms_configs', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('gateway_name');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (! Schema::hasIndex('personal_access_tokens', 'personal_access_tokens_token_index')) {
                $table->index('token');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            try {
                Schema::table('stations', function (Blueprint $table) {
                    $table->fullText('name');
                });
            } catch (\Exception $e) {
                // Full-text indexes might not be available in all MySQL versions
            }
        }

        Schema::table('buses', function (Blueprint $table) {
            $table->index('coach_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['bus_id']);
            $table->dropIndex(['bus_id', 'departure_time']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex(['departure_station_id']);
            $table->dropIndex(['arrival_station_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['schedule_id', 'status']);
            $table->dropIndex(['status', 'payment_method']);
            $table->dropIndex(['passenger_email']);
            $table->dropIndex(['passenger_phone']);
        });

        Schema::table('sms_configs', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['gateway_name']);
        });

        if (DB::getDriverName() === 'mysql') {
            try {
                Schema::table('stations', function (Blueprint $table) {
                    $table->dropFullText('name');
                });
            } catch (\Exception $e) {
                // Ignore if full-text index doesn't exist
            }
        }

        Schema::table('buses', function (Blueprint $table) {
            $table->dropIndex(['coach_number']);
        });
    }
};
