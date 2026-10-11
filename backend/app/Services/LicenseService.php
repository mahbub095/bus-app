<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envato purchase code verification for CodeCanyon products.
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │  VERIFICATION RULES                                             │
 * │                                                                 │
 * │  1. Wrong code      → rejected immediately                      │
 * │  2. Other product   → rejected (item ID mismatch)               │
 * │  3. Max 5 domains   → 6th unique domain is rejected             │
 * │  4. Bypass code     → only the exact DEFAULT_BYPASS_CODE passes  │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * .env write policy:
 *   $persistDb = false  (wizard step 1) → NO writes (keeps artisan serve alive)
 *   $persistDb = true   (re-verify)     → writes APP_LICENSED=true to .env
 *   persistVerifiedLicense()            → called from background command, safe
 */
class LicenseService
{
    private const AUTHOR_SALE_URL    = 'https://api.envato.com/v3/market/author/sale';
    private const BUYER_PURCHASE_URL = 'https://api.envato.com/v3/market/buyer/purchase';
    private const CACHE_KEY          = 'license_verified';
    private const CACHE_TTL_MINUTES  = 1440; // 24 h

    /**
     * Seller-only bypass code.
     * ONLY this exact UUID passes without a real Envato API call.
     * Any other UUID — including codes differing by one character — is rejected
     * or sent to real Envato verification.
     */
    private const DEFAULT_BYPASS_CODE = '621d1ac1-0b6f-4e8c-9b41-4903fe022678';

    public function __construct(protected EnvFileWriter $envFileWriter) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Verify a purchase code against all rules.
     *
     * @param  string  $purchaseCode
     * @param  bool    $persistDb    false during wizard step 1 to avoid writing
     *                               .env (which restarts php artisan serve).
     * @param  string  $currentDomain  The domain being activated (APP_URL).
     */
    public function verify(
        string $purchaseCode,
        bool   $persistDb     = true,
        string $currentDomain = ''
    ): array {
        $purchaseCode  = trim($purchaseCode);
        $currentDomain = $currentDomain ?: config('app.url', '');

        // ── Rule 0: Format check ─────────────────────────────────────────────
        if (! $this->isValidUuid($purchaseCode)) {
            return $this->fail(
                'Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'
            );
        }

        // ── Rule 4: Seller bypass code ───────────────────────────────────────
        // ONLY the exact DEFAULT_BYPASS_CODE passes without an API call.
        if (strcasecmp($purchaseCode, self::DEFAULT_BYPASS_CODE) === 0) {
            return $this->handleBypass($purchaseCode, $persistDb, $currentDomain);
        }

        // ── Rules 1–3: Real Envato API verification ──────────────────────────
        $authorToken  = config('envato.personal_token', '');
        $envatoItemId = config('envato.item_id', '');

        if (empty($authorToken)) {
            return $this->fail('License verification is not configured on this server. Please contact the seller.');
        }
        if (empty($envatoItemId)) {
            return $this->fail('Server configuration error (ENVATO_ITEM_ID missing). Please contact the seller.');
        }

        try {
            $response = $this->callApi(self::AUTHOR_SALE_URL, $purchaseCode, $authorToken);

            // Fallback endpoint on 401/403
            if (! $response['ok'] && in_array($response['status'], [401, 403], true)) {
                $fallback = $this->callApi(self::BUYER_PURCHASE_URL, $purchaseCode, $authorToken);
                if ($fallback['ok']) {
                    $response = $fallback;
                }
            }

            $data = $response['data'];

            // ── Rule 1: Wrong / non-existent code ────────────────────────────
            if ($response['status'] === 404) {
                return $this->fail(
                    'Invalid purchase code. This code was not found on Envato Market. ' .
                    'Please check the code from your CodeCanyon download page.'
                );
            }

            if (in_array($response['status'], [401, 403], true)) {
                return $this->fail('License verification failed. Please contact the seller.');
            }

            if (! $response['ok']) {
                return $this->fail(
                    'Could not reach Envato verification server. Please try again in a moment.'
                );
            }

            // ── Rule 2: Must belong to THIS product ──────────────────────────
            $returnedItemId = (string) ($data['item']['id'] ?? '');
            if ($returnedItemId !== (string) $envatoItemId) {
                return $this->fail(
                    'This purchase code is for a different product. ' .
                    'Please use the purchase code from your SonyaBus download.'
                );
            }

            // ── Rule 3: Domain limit (max 5) ─────────────────────────────────
            $domainCheck = $this->checkDomainLimit($purchaseCode, $currentDomain);
            if (! $domainCheck['ok']) {
                return $this->fail($domainCheck['message']);
            }

            return $this->buildResult($purchaseCode, $persistDb, $data, $currentDomain);

        } catch (\Throwable $e) {
            Log::error('LicenseService::verify exception.', ['error' => $e->getMessage()]);
            return $this->fail('An unexpected error occurred during verification. Please try again.');
        }
    }

    /**
     * Persist a verified license after migrations have run.
     * Called from InstallFinalize background command — safe to write .env here.
     */
    public function persistVerifiedLicense(string $purchaseCode, array $apiData): License
    {
        $this->envFileWriter->set(['APP_LICENSED' => 'true']);

        $domain  = config('app.url', '');
        $license = $this->saveLicense($purchaseCode, $apiData, $domain);

        Cache::put(self::CACHE_KEY, true, now()->addMinutes(self::CACHE_TTL_MINUTES));
        return $license;
    }

    /** Returns true when a valid license is active for this installation. */
    public function isActivated(): bool
    {
        $flag = env('APP_LICENSED', false);
        if (! $flag || $flag === 'false') {
            return false;
        }

        try {
            return Cache::remember(
                self::CACHE_KEY,
                now()->addMinutes(self::CACHE_TTL_MINUTES),
                fn () => License::isActivated()
            );
        } catch (\Throwable $e) {
            // DB table not yet created during install — trust the env flag
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

        $domains = $license->getDomains();

        return [
            'activated'          => $license->is_verified,
            'purchase_code'      => $this->maskCode($license->purchase_code),
            'envato_username'    => $license->envato_username,
            'buyer_email'        => $license->buyer_email,
            'license_type'       => $license->license_type,
            'purchase_date'      => $license->purchase_date?->toDateString(),
            'verified_at'        => $license->verified_at?->toDateTimeString(),
            'app_url'            => $license->app_url,
            'activated_domains'  => $domains,
            'domain_slots_used'  => count($domains),
            'domain_slots_max'   => License::MAX_DOMAINS,
            'domain_slots_left'  => $license->remainingSlots(),
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Handle the seller's bypass code path.
     * Only DEFAULT_BYPASS_CODE arrives here — any other UUID was already
     * rejected by the format check or sent to real API verification above.
     */
    private function handleBypass(string $purchaseCode, bool $persistDb, string $domain): array
    {
        // Still enforce domain limit for the bypass code
        $domainCheck = $this->checkDomainLimit($purchaseCode, $domain);
        if (! $domainCheck['ok']) {
            return $this->fail($domainCheck['message']);
        }

        $pseudoData = [
            'item'        => ['id' => (string) config('envato.item_id', '0')],
            'buyer'       => 'seller_test_account',
            'buyer_email' => 'seller@sonyabus.test',
            'sold_at'     => now()->toIso8601String(),
            'license'     => 'Extended License',
        ];

        return $this->buildResult($purchaseCode, $persistDb, $pseudoData, $domain);
    }

    /**
     * Check domain limit for a given purchase code.
     * If the purchase code has no existing DB record yet (step 1 of wizard),
     * we skip the limit check because the record will be created on finalise.
     *
     * @return array{ok: bool, message: string}
     */
    private function checkDomainLimit(string $purchaseCode, string $domain): array
    {
        $existing = License::findByCode($purchaseCode);

        if (! $existing) {
            // First activation for this code — always allowed
            return ['ok' => true, 'message' => ''];
        }

        $normalized = $existing->normalizeDomain($domain);

        // Same domain re-verifying → always allowed (counts as renewal)
        if ($existing->hasDomain($domain)) {
            return ['ok' => true, 'message' => ''];
        }

        // New domain — check slot limit
        if ($existing->remainingSlots() <= 0) {
            $used = count($existing->getDomains());
            return [
                'ok'      => false,
                'message' => "This purchase code has reached the maximum of " . License::MAX_DOMAINS . " domain activations "
                    . "({$used}/" . License::MAX_DOMAINS . " used). "
                    . "Please purchase an additional license for new domains.",
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * Build a verified result, writing to DB/.env only when $persistDb = true.
     * When $persistDb = false (wizard step 1), no side-effects occur.
     */
    private function buildResult(
        string $purchaseCode,
        bool   $persistDb,
        array  $apiData,
        string $domain
    ): array {
        if ($persistDb) {
            $this->envFileWriter->set(['APP_LICENSED' => 'true']);
            $license = $this->saveLicense($purchaseCode, $apiData, $domain);
            Cache::put(self::CACHE_KEY, true, now()->addMinutes(self::CACHE_TTL_MINUTES));

            return [
                'success'  => true,
                'message'  => 'License verified successfully! Welcome to SonyaBus.',
                'license'  => $license,
                'api_data' => $apiData,
            ];
        }

        // Step 1 of wizard — validate only, no DB or .env writes
        return [
            'success'  => true,
            'message'  => 'License verified successfully!',
            'api_data' => $apiData,
        ];
    }

    /** Save / update the license record and register the domain. */
    private function saveLicense(string $purchaseCode, array $data, string $domain): License
    {
        $license = License::firstOrNew(['purchase_code' => $purchaseCode]);

        $soldAt = $data['sold_at'] ?? $data['purchase']['sold_at'] ?? null;
        if (is_string($soldAt) && ($ts = strtotime($soldAt)) !== false) {
            $soldAt = date('Y-m-d H:i:s', $ts);
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
            'app_url'         => $domain,
        ]);

        $license->save();

        // Register this domain (addDomain handles duplicates and limit)
        if (! empty($domain)) {
            $license->addDomain($domain);
        }

        return $license;
    }

    private function callApi(string $url, string $purchaseCode, string $token): array
    {
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'User-Agent'    => 'SonyaBus License Verification/1.0',
                'Accept'        => 'application/json',
            ])
            ->get($url, ['code' => $purchaseCode]);

        $body = $response->json();
        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'data'   => is_array($body) ? $body : [],
        ];
    }

    private function isValidUuid(string $code): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $code
        );
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    private function maskCode(string $code): string
    {
        if (strlen($code) < 36) return '****-****-****-****';
        return substr($code, 0, 8) . '-****-****-****-************';
    }
}
