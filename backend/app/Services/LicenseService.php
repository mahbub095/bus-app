<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles Envato purchase code verification for CodeCanyon products.
 *
 * Verification flow:
 *   1. Call the Envato Market API with the buyer's purchase code.
 *   2. Confirm the item ID returned matches this product's ENVATO_ITEM_ID.
 *   3. Store the result locally and cache the verified status.
 */
class LicenseService
{
    private const ENVATO_API_URL    = 'https://api.envato.com/v3/market/author/sale';
    private const CACHE_KEY         = 'license_verified';
    private const CACHE_TTL_MINUTES = 1440; // 24 hours

    public function __construct(
        protected EnvFileWriter $envFileWriter
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Verify a purchase code against the Envato API and persist the result.
     *
     * @return array{success: bool, message: string, license?: License}
     */
    public function verify(string $purchaseCode, string $personalToken): array
    {
        $purchaseCode = trim($purchaseCode);

        if (! $this->isValidPurchaseCodeFormat($purchaseCode)) {
            return [
                'success' => false,
                'message' => 'Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            ];
        }

        if (empty($personalToken)) {
            return ['success' => false, 'message' => 'Envato personal token is required for verification.'];
        }

        $envatoItemId = config('envato.item_id');
        if (empty($envatoItemId)) {
            return ['success' => false, 'message' => 'ENVATO_ITEM_ID is not configured. Please check your .env file.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $personalToken,
                    'User-Agent'    => 'SonyaBus License Verification/1.0',
                ])
                ->get(self::ENVATO_API_URL, ['code' => $purchaseCode]);

            if ($response->status() === 404) {
                return ['success' => false, 'message' => 'Purchase code not found. Please check the code and try again.'];
            }

            if ($response->status() === 403 || $response->status() === 401) {
                return ['success' => false, 'message' => 'Invalid or expired Envato personal token. Please generate a new one.'];
            }

            if (! $response->successful()) {
                Log::warning('Envato API returned unexpected status.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['success' => false, 'message' => 'Could not reach Envato verification server. Please try again.'];
            }

            $data = $response->json();

            // Validate that the purchase code belongs to this product
            $itemId = (string) ($data['item']['id'] ?? '');
            if ($itemId !== (string) $envatoItemId) {
                return [
                    'success' => false,
                    'message' => 'This purchase code is for a different product. Please use the correct purchase code.',
                ];
            }

            // Persist the verified license
            $license = $this->persistLicense($purchaseCode, $data);

            // Mark in .env so the install-guard middleware works on next boot
            $this->envFileWriter->set(['APP_LICENSED' => 'true']);

            // Cache the verified state
            Cache::put(self::CACHE_KEY, true, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return [
                'success' => true,
                'message' => 'License verified successfully! Welcome to SonyaBus.',
                'license' => $license,
            ];

        } catch (\Throwable $e) {
            Log::error('License verification exception.', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'An unexpected error occurred during verification. Please try again.'];
        }
    }

    /**
     * Check whether the application has a verified license.
     *
     * Fast path: if APP_LICENSED=true in .env and the DB record exists and
     * is_verified=true, returns true immediately from cache without an API call.
     */
    public function isActivated(): bool
    {
        // Fast path: env flag set to false — skip cache/DB entirely
        if (env('APP_LICENSED', 'false') === 'false') {
            return false;
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            return License::isActivated();
        });
    }

    /**
     * Clear the local license record and the cached state.
     * Use only in controlled reset / re-install scenarios.
     */
    public function deactivate(): void
    {
        License::truncate();
        Cache::forget(self::CACHE_KEY);
        $this->envFileWriter->set(['APP_LICENSED' => 'false']);
    }

    /**
     * Return the current license data formatted for display.
     *
     * @return array<string, mixed>
     */
    public function info(): array
    {
        $license = License::current();

        if (! $license) {
            return ['activated' => false];
        }

        return [
            'activated'       => $license->is_verified,
            'purchase_code'   => $this->maskPurchaseCode($license->purchase_code),
            'envato_username' => $license->envato_username,
            'buyer_email'     => $license->buyer_email,
            'license_type'    => $license->license_type,
            'purchase_date'   => $license->purchase_date?->toDateString(),
            'verified_at'     => $license->verified_at?->toDateTimeString(),
            'app_url'         => $license->app_url,
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Validate the UUID-based Envato purchase code format.
     */
    private function isValidPurchaseCodeFormat(string $code): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $code
        );
    }

    /**
     * Store or update the license record from Envato API response data.
     */
    private function persistLicense(string $purchaseCode, array $data): License
    {
        $license = License::firstOrNew(['purchase_code' => $purchaseCode]);

        $license->fill([
            'purchase_code'   => $purchaseCode,
            'envato_username' => $data['buyer'] ?? null,
            'buyer_email'     => $data['buyer_email'] ?? null,
            'purchase_date'   => isset($data['sold_at']) ? date('Y-m-d H:i:s', strtotime($data['sold_at'])) : null,
            'license_type'    => $data['license'] ?? 'Regular License',
            'is_verified'     => true,
            'verified_at'     => now(),
            'app_url'         => config('app.url'),
        ]);

        $license->save();

        return $license;
    }

    /**
     * Mask purchase code for safe display: show first 8 chars only.
     * Example: "a1b2c3d4-****-****-****-************"
     */
    private function maskPurchaseCode(string $code): string
    {
        if (strlen($code) < 36) {
            return '****-****-****-****';
        }

        return substr($code, 0, 8) . '-****-****-****-************';
    }
}
