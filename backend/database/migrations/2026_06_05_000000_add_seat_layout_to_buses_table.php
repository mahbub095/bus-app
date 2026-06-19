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
        if (Schema::hasColumn('buses', 'seat_layout')) {
            return;
        }

        Schema::table('buses', function (Blueprint $table) {
            $table->string('seat_layout')->default('2+2')->after('total_seats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropColumn('seat_layout');
        });
    }
};
