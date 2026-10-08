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
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->string('operator_name');
            $table->string('coach_number');
            $table->enum('coach_type', ['AC', 'Non AC']);
            $table->integer('total_seats')->default(36);
            $table->string('seat_layout')->default('2+2');
            $table->longText('seat_layout_grid')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('coach_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
