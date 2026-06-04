<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->json('boarding_points')->nullable()->after('duration');
            $table->json('dropping_points')->nullable()->after('boarding_points');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn(['boarding_points', 'dropping_points']);
        });
    }
};
