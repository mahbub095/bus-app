<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license', function (Blueprint $table) {
            // JSON array of activated domain URLs, max 5 per purchase code
            $table->json('activated_domains')->nullable()->after('app_url');
        });
    }

    public function down(): void
    {
        Schema::table('license', function (Blueprint $table) {
            $table->dropColumn('activated_domains');
        });
    }
};
