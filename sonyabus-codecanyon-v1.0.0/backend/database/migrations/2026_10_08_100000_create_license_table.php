<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_code', 100)->unique();
            $table->string('envato_username', 100)->nullable();
            $table->string('buyer_email', 255)->nullable();
            $table->timestamp('purchase_date')->nullable();
            $table->string('license_type', 50)->default('Regular License');
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('app_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license');
    }
};
