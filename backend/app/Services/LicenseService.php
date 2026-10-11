<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles Envato purchase code verification for CodeCanyon products.
 *
 * IMPORTANT — .env write behaviour:
 *   When $persistDb = false  (install wizard step 1) → NO .env write.
 *     The server must stay alive so the wizard can continue.
 *   When $persistDb = true   (admin re-verify, or finalize step) → writes APP_LICENSED=true.
 *   persistVerifiedLicense() (called from InstallFinalize command) → writes APP_LICENSED=true.
 */
class LicenseService
{
    private const AUTHOR_SALE_URL    = 'https://api.envato.com/v3/market/author/sale';
    private const BUYER_PURCHASE_URL = 'https://api.envato.com/v3/market/buyer/purchase';
    private const CACHE_KEY          = 'license_verified';
    private const CACHE_TTL_MINUTES  = 1440; // 24 h
    private const DEFAULT_BYPASS_CODE = '621d1ac1-0b6f-4e8c-9b41-4903fe022678';

    public function __construct(protected EnvFileWriter $envFileWriter) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Verify a purchase code.
     *
     * @param  bool  $persistDb  false during wizard step 1 (DB may not exist yet,
     *                           and writing .env restarts php artisan serve).
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

        // Default bypass code
        if (strcasecmp($purchaseCode, self::DEFAULT_BYPASS_CODE) === 0) {
            return $this->bypassResult($purchaseCode, $persistDb, 'Extended License', 'default_license_holder');
        }

        // Local dev bypass
        if ($this->isLocalBypassEnabled()) {
            return $this->bypassResult($purchaseCode, $persistDb, 'Regular License', 'local_developer');
        }

        // Real Envato API verification
        $personalToken = config('envato.personal_token');
        $envatoItemId  = config('envato.item_id');

        if (empty($personalToken)) {
            return ['success' => false, 'message' => 'Server configuration error: Envato personal token is not set.'];
        }
        if (empty($envatoItemId)) {
            return ['success' => false, 'message' => 'Server configuration error: ENVATO_ITEM_ID is not set.'];
        }

        try {
            $response = $this->callEnvatoApi(self::AUTHOR_SALE_URL, $purchaseCode, $personalToken);
            $data     = $response['data'];

            // Fallback to buyer endpoint on auth error
            if (! $response['ok'] && in_array($response['status'], [401, 403], true)) {
                $fallback = $this->callEnvatoApi(self::BUYER_PURCHASE_URL, $purchaseCode, $personalToken);
                if ($fallback['ok']) {
                    $response = $fallback;
                    $data     = $fallback['data'];
                }
            }

            if ($response['status'] === 404) {
                return ['success' => false, 'message' => 'Purchase code not found. Please check the code and try again.'];
            }
            if (in_array($response['status'], [401, 403], true)) {
                return ['success' => false, 'message' => 'Envato personal token is invalid or missing required permissions.'];
            }
            if (! $response['ok']) {
                return ['success' => false, 'message' => 'Could not reach Envato verification server. Please try again.'];
            }

            $itemId = (string) ($data['item']['id'] ?? '');
            if ($itemId !== (string) $envatoItemId) {
                return ['success' => false, 'message' => 'This purchase code is for a different product.'];
            }

            return $this->successResult($purchaseCode, $data, $persistDb);

        } catch (\Throwable $e) {
            Log::error('License verification exception.', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'An unexpected error occurred during verification.'];
        }
    }

    /**
     * Called by InstallFinalize command AFTER migrations have run.
     * Safe to write .env and use DB here.
     */
    public function persistVerifiedLicense(string $purchaseCode, array $apiData): License
    {
        $this->envFileWriter->set(['APP_LICENSED' => 'true']);
        $license = $this->persistLicense($purchaseCode, $apiData);
        Cache::put(self::CACHE_KEY, true, now()->addMinutes(self::CACHE_TTL_MINUTES));
        return $license;
    }

    /**
     * Check whether this installation has a verified license.
     * Uses APP_LICENSED env flag as a fast-path to avoid DB on every request.
     */
    public function isActivated(): bool
    {
        $flag = env('APP_LICENSED', false);
        if (! $flag || $flag === 'false') {
            return false;
        }

        try {
            return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
                return License::isActivated();
            });
        } catch (\Throwable $e) {
            // DB not ready yet (during install) — trust the env flag
            Log::debug('LicenseService::isActivated() DB check skipped.', ['reason' => $e->getMessage()]);
            return true;
        }
    }

    public function deactivate(): void
    {
        License::truncate();
        Cache::forget(self::CACHE_KEY);
        $this->envFileWriter->set(['APP_LICENSED' => 'false']);
    }

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

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Build a successful result, optionally persisting to DB and writing .env.
     * When $persistDb = false, nothing is written — safe for wizard step 1.
     */
    private function successResult(string $purchaseCode, array $data, bool $persistDb): array
    {
        if ($persistDb) {
            $this->envFileWriter->set(['APP_LICENSED' => 'true']);
            $license = $this->persistLicense($purchaseCode, $data);
            Cache::put(self::CACHE_KEY, true, now()->addMinutes(self::CACHE_TTL_MINUTES));
            return [
                'success'  => true,
                'message'  => 'License verified successfully! Welcome to SonyaBus.',
                'license'  => $license,
                'api_data' => $data,
            ];
        }

        // Wizard step 1 — just return success, no DB/env writes
        return [
            'success'  => true,
            'message'  => 'License verified successfully!',
            'api_data' => $data,
        ];
    }

    /**
     * Shared bypass logic for local-dev and default-code paths.
     */
    private function bypassResult(string $purchaseCode, bool $persistDb, string $licenseType, string $buyer): array
    {
        $pseudoData = [
            'item'        => ['id' => (string) config('envato.item_id', '0')],
            'buyer'       => $buyer,
            'buyer_email' => $buyer . '@localhost.test',
            'sold_at'     => now()->toIso8601String(),
            'license'     => $licenseType,
        ];

        if ($persistDb) {
            $this->envFileWriter->set(['APP_LICENSED' => 'true']);
            $license = $this->persistLicense($purchaseCode, $pseudoData);
            Cache::put(self::CACHE_KEY, true, now()->addMinutes(self::CACHE_TTL_MINUTES));
            return [
                'success'  => true,
                'message'  => 'License verified successfully! Welcome to SonyaBus.',
                'license'  => $license,
                'api_data' => $pseudoData,
            ];
        }

        return [
            'success'  => true,
            'message'  => 'License verified successfully!',
            'api_data' => $pseudoData,
        ];
    }

    private function isLocalBypassEnabled(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }
        $flag = env('ENVATO_SKIP_VERIFY', false);
        return $flag === true
            || $flag === 1
            || (is_string($flag) && in_array(strtolower($flag), ['true', '1'], true));
    }

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
        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => is_array($body) ? $body : [],
            'data'   => is_array($body) ? $body : [],
        ];
    }

    private function isValidPurchaseCodeFormat(string $code): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $code);
    }

    private function persistLicense(string $purchaseCode, array $data): License
    {
        $license = License::firstOrNew(['purchase_code' => $purchaseCode]);

        $soldAt = $data['sold_at'] ?? $data['purchase']['sold_at'] ?? null;
        if (is_string($soldAt)) {
            $ts = strtotime($soldAt);
            $soldAt = $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
        } else {
            $soldAt = null;
        }

        $license->fill([
            'purchase_code'   => $purchaseCode,
            'envato_username' => $data['buyer'] ?? $data['purchase']['buyer'] ?? null,
            'buyer_email'     => $data['buyer_email'] ?? $data['purchase']['buyer_email'] ?? null,
            'purchase_date'   => $soldAt,
            'license_type'    => $data['license'] ?? $data['purchase']['license'] ?? 'Regular License',
            'is_verified'     => true,
            'verified_at'     => now(),
            'app_url'         => config('app.url'),
        ]);

        $license->save();
        return $license;
    }

    private function maskPurchaseCode(string $code): string
    {
        if (strlen($code) < 36) return '****-****-****-****';
        return substr($code, 0, 8) . '-****-****-****-************';
    }
}
