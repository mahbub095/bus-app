<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\SmsConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SmsGatewayService
{
    /**
     * @return array{success: bool, message: string}
     */
    public function sendBookingVerification(Booking $booking): array
    {
        $config = $this->resolveConfig();
        if ($config === null) {
            return ['success' => false, 'message' => 'SMS gateway is not configured or inactive.'];
        }

        $issue = $this->configIssue($config);
        if ($issue !== null) {
            Log::warning('SMS skipped: incomplete gateway configuration.', [
                'booking_id' => $booking->id,
                'reason' => $issue,
            ]);

            return ['success' => false, 'message' => $issue];
        }

        $phone = $this->normalizePhone($booking->passenger_phone);
        if ($phone === null) {
            Log::warning('SMS skipped: invalid passenger phone number.', [
                'booking_id' => $booking->id,
                'phone' => $booking->passenger_phone,
            ]);

            return ['success' => false, 'message' => 'Passenger phone number is not a valid Bangladesh mobile number.'];
        }

        $message = $this->buildBookingMessage($booking, $config);

        return $this->sendMessage($config, $phone, $message, [
            'booking_id' => $booking->id,
        ]);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function sendTestMessage(string $rawPhone, ?string $customMessage = null): array
    {
        $config = $this->resolveConfig();
        if ($config === null) {
            return ['success' => false, 'message' => 'SMS gateway is not configured or inactive.'];
        }

        $issue = $this->configIssue($config);
        if ($issue !== null) {
            return ['success' => false, 'message' => $issue];
        }

        $phone = $this->normalizePhone($rawPhone);
        if ($phone === null) {
            return ['success' => false, 'message' => 'Enter a valid Bangladesh mobile number (e.g. 01712345678).'];
        }

        $message = $customMessage ?: 'SonyaBus SMS test: your gateway configuration is working.';

        return $this->sendMessage($config, $phone, $message, ['test' => true]);
    }

    public function resolveConfig(): ?SmsConfig
    {
        if (! Schema::hasTable('sms_configs')) {
            return null;
        }

        $config = SmsConfig::query()->latest('id')->first();
        if (! $config || ! $config->is_active) {
            return null;
        }

        return $config;
    }

    public function configIssue(SmsConfig $config): ?string
    {
        if (! $config->api_url) {
            return 'Gateway API URL is required.';
        }

        if (! $config->api_key) {
            return 'API key is required.';
        }

        if (! $config->sender_id) {
            return 'Sender ID is required.';
        }

        return null;
    }

    public function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '880' . substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '880' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '880' . $digits;
        }

        return null;
    }

    protected function buildBookingMessage(Booking $booking, SmsConfig $config): string
    {
        $pnr = 'SE' . str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT);
        $template = trim((string) $config->message_template);

        if ($template !== '') {
            return str_replace(
                ['{PNR}', '{SEATS}', '{FARE}', '{STATUS}'],
                [$pnr, $booking->seat_numbers, number_format((float) $booking->total_fare, 2), $booking->status],
                $template
            );
        }

        return "SonyaBus ticket confirmed. PNR {$pnr}, Seats {$booking->seat_numbers}, Fare BDT {$booking->total_fare}.";
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{success: bool, message: string}
     */
    protected function sendMessage(SmsConfig $config, string $phone, string $message, array $context = []): array
    {
        $driver = strtolower(trim((string) ($config->gateway_driver ?: 'custom')));

        try {
            $response = match ($driver) {
                'bulksmsbd' => $this->sendViaBulkSmsBd($config, $phone, $message),
                'get_query' => $this->sendViaGetQuery($config, $phone, $message),
                default => $this->sendViaCustomForm($config, $phone, $message),
            };

            if ($response->successful()) {
                Log::info('Booking SMS sent successfully.', array_merge($context, [
                    'phone' => $phone,
                    'driver' => $driver,
                    'status' => $response->status(),
                ]));

                return ['success' => true, 'message' => 'SMS sent successfully.'];
            }

            $body = $response->body();
            Log::warning('SMS gateway rejected the request.', array_merge($context, [
                'phone' => $phone,
                'driver' => $driver,
                'http_status' => $response->status(),
                'response' => $body,
            ]));

            return [
                'success' => false,
                'message' => 'Gateway returned HTTP ' . $response->status() . '. Check laravel.log for details.',
            ];
        } catch (\Throwable $exception) {
            Log::error('SMS gateway request failed.', array_merge($context, [
                'phone' => $phone,
                'driver' => $driver,
                'error' => $exception->getMessage(),
            ]));

            return [
                'success' => false,
                'message' => 'Could not reach SMS gateway: ' . $exception->getMessage(),
            ];
        }
    }

    protected function sendViaBulkSmsBd(SmsConfig $config, string $phone, string $message): \Illuminate\Http\Client\Response
    {
        return Http::timeout(15)
            ->asForm()
            ->post($config->api_url, [
                'api_key' => $config->api_key,
                'type' => 'text',
                'number' => $phone,
                'senderid' => $config->sender_id,
                'message' => $message,
            ]);
    }

    protected function sendViaCustomForm(SmsConfig $config, string $phone, string $message): \Illuminate\Http\Client\Response
    {
        return Http::timeout(15)
            ->asForm()
            ->post($config->api_url, [
                'api_key' => $config->api_key,
                'sender_id' => $config->sender_id,
                'mobile' => $phone,
                'message' => $message,
            ]);
    }

    protected function sendViaGetQuery(SmsConfig $config, string $phone, string $message): \Illuminate\Http\Client\Response
    {
        $query = http_build_query([
            'api_key' => $config->api_key,
            'sender_id' => $config->sender_id,
            'mobile' => $phone,
            'message' => $message,
        ]);

        $url = $config->api_url;
        $separator = str_contains($url, '?') ? '&' : '?';

        return Http::timeout(15)->get($url . $separator . $query);
    }
}
