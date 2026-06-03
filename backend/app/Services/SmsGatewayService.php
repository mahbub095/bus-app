<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\SmsConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SmsGatewayService
{
    public function sendBookingVerification(Booking $booking): void
    {
        if (! Schema::hasTable('sms_configs')) {
            Log::warning('SMS gateway configuration table missing; SMS notification skipped.', [
                'booking_id' => $booking->id,
            ]);
            return;
        }

        $config = SmsConfig::query()->latest('id')->first();

        if (! $config || ! $config->is_active || ! $config->api_url || ! $config->api_key || ! $config->sender_id) {
            return;
        }

        $pnr = 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT);
        $template = trim((string) $config->message_template);
        $message = $template !== ''
            ? str_replace(
                ['{PNR}', '{SEATS}', '{FARE}', '{STATUS}'],
                [$pnr, $booking->seat_numbers, number_format((float) $booking->total_fare, 2), $booking->status],
                $template
            )
            : "SonyaBus ticket confirmed. PNR {$pnr}, Seats {$booking->seat_numbers}, Fare BDT {$booking->total_fare}.";

        try {
            $response = Http::timeout(10)
                ->asForm()
                ->post($config->api_url, [
                    'api_key' => $config->api_key,
                    'sender_id' => $config->sender_id,
                    'mobile' => $booking->passenger_phone,
                    'message' => $message,
                ]);

            if (! $response->successful()) {
                Log::warning('Failed to send booking verification SMS.', [
                    'booking_id' => $booking->id,
                    'phone' => $booking->passenger_phone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to send booking verification SMS.', [
                'booking_id' => $booking->id,
                'phone' => $booking->passenger_phone,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
