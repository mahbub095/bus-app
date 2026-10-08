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
 *   1. Read the author's personal token from config (envato.personal_token).
 *   2. Call Envato Market /author/sale API with the buyer's purchase code.
 *   3. Confirm the item ID returned matches ENVATO_ITEM_ID.
 *   4. Store the result locally and cache the verified status.
 *
 * Local development bypass (no real Envato token needed):
 *   Set in .env:  APP_ENV=local  ENVATO_SKIP_VERIFY=true
 *   Any valid-format purchase code will be accepted.
 */
class LicenseService
{
    private const AUTHOR_SALE_URL     = 'https://api.envato.com/v3/market/author/sale';
    private const BUYER_PURCHASE_URL  = 'https://api.envato.com/v3/market/buyer/purchase';
    private const CACHE_KEY           = 'license_verified';
    private const CACHE_TTL_MINUTES   = 1440;

    public function __construct(
        protected EnvFileWriter $envFileWriter
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Verify a purchase code against the Envato API and (optionally) persist.
     *
     * @param  string  $purchaseCode
     * @param  bool    $persistDb  If false, only validate via API/env; caller
     *                             must later call persistVerifiedLicense()
     *                             (used during install flow before DB exists).
     * @return array{success: bool, message: string, license?: License, api_data?: array}
     */
    public function verify(string $purchaseCode, bool $persistDb = true): array
    {
        $purchaseCode = trim($purchaseCode);

        if (! $this->isValidPurchaseCodeFormat($purchaseCode)) {
            return [
                'success' => false,
                'message' => 'Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            ];
        }

        // ─── Local-dev bypass ─────────────────────────────────────────────
        if ($this->isLocalBypassEnabled()) {
            return $this->finishLocalDevVerification($purchaseCode, $persistDb);
        }

        $personalToken = config('envato.personal_token');
        if (empty($personalToken)) {
            Log::error('Envato personal token is missing from config (.env).');
            return [
                'success' => false,
                'message' => 'Server configuration error: Envato personal token is not set. Please contact support.',
            ];
        }

        $envatoItemId = config('envato.item_id');
        if (empty($envatoItemId)) {
            return [
                'success' => false,
                'message' => 'Server configuration error: ENVATO_ITEM_ID is not set. Please contact support.',
            ];
        }

        try {
            // Primary: author/sale endpoint (requires author token with sales permission)
            $response = $this->callEnvatoApi(self::AUTHOR_SALE_URL, $purchaseCode, $personalToken);
            $data     = $response['data'];

            // Fallback: if author/sale fails with 403/401, try buyer/purchase endpoint
            if (! $response['ok'] && in_array($response['status'], [401, 403], true)) {
                $fallback = $this->callEnvatoApi(self::BUYER_PURCHASE_URL, $purchaseCode, $personalToken);
                if ($fallback['ok']) {
                    $response = $fallback;
                    $data     = $fallback['data'];
                }
            }

            if ($response['status'] === 404) {
                return [
                    'success' => false,
                    'message' => 'Purchase code not found. Please check the code and try again.',
                ];
            }

            if ($response['status'] === 403 || $response['status'] === 401) {
                Log::warning('Envato API rejected token (401/403). Token may be expired or missing required scopes.', [
                    'status' => $response['status'],
                    'body'   => is_array($response['body']) ? json_encode($response['body']) : $response['body'],
                ]);
                return [
                    'success' => false,
                    'message' => 'Server configuration error: Envato personal token is invalid or missing required permissions. Please contact support.',
                ];
            }

            if (! $response['ok']) {
                Log::warning('Envato API returned unexpected status.', [
                    'status' => $response['status'],
                    'body'   => is_array($response['body']) ? json_encode($response['body']) : $response['body'],
                ]);
                return [
                    'success' => false,
                    'message' => 'Could not reach Envato verification server. Please try again in a few minutes.',
                ];
            }

            // Validate that the purchase code belongs to this product
            $itemId = (string) ($data['item']['id'] ?? '');
            if ($itemId !== (string) $envatoItemId) {
                return [
                    'success' => false,
                    'message' => 'This purchase code is for a different product. Please use the purchase code from your SonyaBus download.',
                ];
            }

            $this->envFileWriter->set(['APP_LICENSED' => 'true']);

            if ($persistDb) {
                $license = $this->persistLicense($purchaseCode, $data);
                Cache::put(self::CACHE_KEY, true, now()->addMinutes(self::CACHE_TTL_MINUTES));
                return [
                    'success'   => true,
                    'message'   => 'License verified successfully! Welcome to SonyaBus.',
                    'license'   => $license,
                    'api_data'  => $data,
                ];
            }

            return [
                'success'  => true,
                'message'  => 'License verified successfully!',
                'api_data' => $data,
            ];

        } catch (\Throwable $e) {
            Log::error('License verification exception.', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'An unexpected error occurred during verification. Please try again.',
            ];
        }
    }

    /**
     * Persist a previously-verified license to the database.
     *
     * Used by the install wizard in the "finalize" step AFTER the
     * migrations have run (so the `license` table is guaranteed to exist).
     *
     * @param  string                   $purchaseCode
     * @param  array<array-key, mixed>  $apiData  Response body from Envato API
     *                                            (or the pseudo-response from
     *                                            the local-dev bypass).
     */
    public function persistVerifiedLicense(string $purchaseCode, array $apiData): License
    {
        $this->envFileWriter->set(['APP_LICENSED' => 'true']);

        $license = $this->persistLicense($purchaseCode, $apiData);

        Cache::put(self::CACHE_KEY, true, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $license;
    }

    /**
     * Check whether the application has a verified license.
     *
     * Fast path: if APP_LICENSED is not truthy, return false immediately.
     * Otherwise, the DB-backed result is cached for 24 hours.
     *
     * NOTE: Gracefully swallows PDO / Query exceptions so that the
     * installation wizard (and /up health check) can run before the
     * `license` DB table has been created by migrations.
     */
    public function isActivated(): bool
    {
        $flag = env('APP_LICENSED', false);
        if ($flag === false || $flag === 'false' || $flag === '' || $flag === null) {
            return false;
        }

        try {
            return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
                return License::isActivated();
            });
        } catch (\Throwable $e) {
            // DB table missing, no DB connection, etc. — treat as not activated
            // during the installation phase.
            Log::debug('LicenseService::isActivated() DB check skipped.', [
                'reason' => $e->getMessage(),
            ]);
            return false;
        }
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
    // Local development bypass
    // -------------------------------------------------------------------------

    /**
     * Skip real Envato API calls when running locally with the flag enabled.
     * Allows any valid-format purchase code to be accepted.
     */
    private function isLocalBypassEnabled(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        $flag = env('ENVATO_SKIP_VERIFY', false);

        // Accept boolean true, string "true" (case-insensitive), or int 1
        return $flag === true
            || $flag === 1
            || (is_string($flag) && strtolower($flag) === 'true')
            || (is_string($flag) && $flag === '1');
    }

    /**
     * @return array{success: bool, message: string, license?: License, api_data: array}
     */
    private function finishLocalDevVerification(string $purchaseCode, bool $persistDb): array
    {
        $pseudoData = [
            'item'        => ['id' => (string) config('envato.item_id', '0')],
            'buyer'       => 'local_developer',
            'buyer_email' => 'dev@localhost.test',
            'sold_at'     => now()->toIso8601String(),
            'license'     => 'Regular License',
            '_local_bypass' => true,
        ];

        $this->envFileWriter->set(['APP_LICENSED' => 'true']);

        if ($persistDb) {
            $license = $this->persistLicense($purchaseCode, $pseudoData);
            Cache::put(self::CACHE_KEY, true, now()->addMinutes(self::CACHE_TTL_MINUTES));
            return [
                'success'  => true,
                'message'  => 'License verified (local dev mode) — Welcome to SonyaBus!',
                'license'  => $license,
                'api_data' => $pseudoData,
            ];
        }

        return [
            'success'  => true,
            'message'  => 'License verified (local dev mode) — continue to database setup.',
            'api_data' => $pseudoData,
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Call an Envato Market API endpoint and return a structured result.
     *
     * @return array{ok: bool, status: int, body: mixed, data: array<array-key, mixed>}
     */
    private function callEnvatoApi(string $url, string $purchaseCode, string $personalToken): array
    {
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $personalToken,
                'User-Agent'    => 'SonyaBus License Verification/1.0',
                'Accept'        => 'application/json',
            ])
            ->get($url, ['code' => $purchaseCode]);

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => $body,
            'data'   => $body,
        ];
    }

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
     *
     * Handles both /author/sale and /buyer/purchase response shapes since
     * the exact fields vary by endpoint and API version.
     */
    private function persistLicense(string $purchaseCode, array $data): License
    {
        $license = License::firstOrNew(['purchase_code' => $purchaseCode]);

        $soldAt = $data['sold_at'] ?? $data['purchase']['sold_at'] ?? null;
        if (is_string($soldAt)) {
            $timestamp = strtotime($soldAt);
            if ($timestamp !== false) {
                $soldAt = date('Y-m-d H:i:s', $timestamp);
            } else {
                $soldAt = null;
            }
        } else {
            $soldAt = null;
        }

        $buyer = $data['buyer']
            ?? $data['purchase']['buyer']
            ?? $data['buyer_info']['first_name']
            ?? null;

        $email = $data['buyer_email']
            ?? $data['purchase']['buyer_email']
            ?? null;

        $licenseType = $data['license']
            ?? $data['purchase']['license']
            ?? 'Regular License';

        $license->fill([
            'purchase_code'   => $purchaseCode,
            'envato_username' => is_string($buyer) ? $buyer : null,
            'buyer_email'     => is_string($email) ? $email : null,
            'purchase_date'   => $soldAt,
            'license_type'    => $licenseType,
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
