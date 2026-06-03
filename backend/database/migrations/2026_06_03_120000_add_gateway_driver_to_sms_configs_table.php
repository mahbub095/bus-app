<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_configs', function (Blueprint $table) {
            $table->string('gateway_driver', 50)->default('custom')->after('gateway_name');
        });
    }

    public function down(): void
    {
        Schema::table('sms_configs', function (Blueprint $table) {
            $table->dropColumn('gateway_driver');
        });
    }
};
