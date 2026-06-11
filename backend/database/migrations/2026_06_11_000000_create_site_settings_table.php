<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        $defaults = [
            'site_title' => 'SonyaBus | Premium Bus Ticket Reservation',
            'favicon_url' => '/favicon.svg',
            'footer_company_name' => 'SonyaBus Enterprise',
            'footer_copyright' => '© 2026 SonyaBus Enterprise Ltd. All rights reserved.',
            'footer_links' => json_encode([
                ['label' => 'Search Buses', 'tab' => 'home'],
                ['label' => 'My Tickets', 'tab' => 'cancel'],
                ['label' => 'Special Promotions', 'tab' => 'offers'],
                ['label' => 'My Profile', 'tab' => 'profile'],
            ]),
            'maintenance_mode' => 'false',
            'maintenance_message' => 'We are currently performing scheduled maintenance. Our booking platform will be back online shortly. Thank you for your patience.',
            'seo_meta_description' => 'Book premium bus tickets across Bangladesh with SonyaBus. Secure online booking, custom seat selection, instant PNR invoice, and promo codes.',
            'seo_meta_keywords' => 'bus tickets, Bangladesh, SonyaBus, online booking, bus reservation, seat selection',
            'seo_og_title' => 'SonyaBus | Premium Bus Ticket Reservation',
            'seo_og_description' => 'Book premium bus tickets across Bangladesh with SonyaBus. Secure online booking, custom seat selection, instant PNR invoice, and promo codes.',
            'seo_og_image' => '',
            'seo_google_analytics_id' => '',
        ];

        $now = now();
        foreach ($defaults as $key => $value) {
            DB::table('site_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
