<?php

namespace Database\Seeders;

use App\Models\SmsConfig;
use Illuminate\Database\Seeder;

class SmsConfigSeeder extends Seeder
{
    /**
     * Seed SMS gateway configuration from config/sms.php (env-driven).
     */
    public function run(): void
    {
        SmsConfig::updateOrCreate(
            ['gateway_name' => config('sms.gateway_name')],
            [
                'gateway_driver' => config('sms.gateway_driver'),
                'api_url' => config('sms.api_url'),
                'api_key' => config('sms.api_key'),
                'sender_id' => config('sms.sender_id'),
                'is_active' => config('sms.is_active'),
                'message_template' => config('sms.message_template'),
            ]
        );
    }
}
