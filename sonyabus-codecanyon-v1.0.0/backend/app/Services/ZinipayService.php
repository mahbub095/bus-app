<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZinipayService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.zinipay.api_key') ?? '';
        $this->baseUrl = config('services.zinipay.base_url') ?? 'https://api.zinipay.com';
    }

    /**
     * Create a ZiniPay payment invoice.
     *
     * @param Booking $booking
     * @param string $source 'frontend' or 'admin'
     * @return array|null Returns array with payment_url and invoice_id, or null on failure.
     */
    public function createInvoice(Booking $booking, string $source = 'frontend'): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('ZiniPay API integration failed: API key is not configured.');
            return null;
        }

        $redirectUrl = route('payment.callback', [
            'booking_id' => $booking->id,
            'source' => $source
        ]);

        $cancelUrl = route('payment.cancel', [
            'booking_id' => $booking->id,
            'source' => $source
        ]);

        $webhookUrl = route('payment.webhook');

        // Only force 127.0.0.1 in local/testing environments to bypass sandbox domain checks
        if (app()->environment('local', 'testing')) {
            $redirectUrl = preg_replace('/:\/\/[^\/:]+/', '://127.0.0.1', $redirectUrl);
            $cancelUrl = preg_replace('/:\/\/[^\/:]+/', '://127.0.0.1', $cancelUrl);
            $webhookUrl = preg_replace('/:\/\/[^\/:]+/', '://127.0.0.1', $webhookUrl);
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'zini-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/v1/payment/create", [
                    'cus_name' => $booking->passenger_name,
                    'cus_email' => $booking->passenger_email,
                    'amount' => (float) $booking->total_fare,
                    'metadata' => [
                        'booking_id' => $booking->id,
                        'source' => $source
                    ],
                    'redirect_url' => $redirectUrl,
                    'cancel_url' => $cancelUrl,
                    'webhook_url' => $webhookUrl,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['payment_url'])) {
                    return [
                        'payment_url' => $data['payment_url'],
                        'invoice_id' => $data['invoice_id'] ?? null,
                    ];
                }
                Log::error('ZiniPay create invoice returned successful but missing payment_url.', [
                    'response' => $data
                ]);
            } else {
                Log::error('ZiniPay create invoice request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Exception during ZiniPay create invoice request.', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Verify payment status for a ZiniPay invoice.
     *
     * @param string $invoiceId
     * @return string|null Returns the status ('COMPLETED', 'PENDING', 'FAILED', etc.) or null on error.
     */
    public function verifyPayment(string $invoiceId): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('ZiniPay API verification failed: API key is not configured.');
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'zini-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/v1/payment/verify", [
                    'invoice_id' => $invoiceId
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['status'] ?? null;
            }

            Log::error('ZiniPay verify payment request failed.', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Throwable $e) {
            Log::error('Exception during ZiniPay payment verification.', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }
}
