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
        Schema::create('sms_configs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_name')->default('Generic Bangladesh SMS Gateway');
            $table->string('api_url')->nullable();
            $table->string('api_key')->nullable();
            $table->string('sender_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('message_template')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_configs');
    }
};
