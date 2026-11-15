<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores Envato purchase license verifications.
 *
 * One row per purchase_code. A single purchase code can activate
 * up to MAX_DOMAINS different domain installations.
 */
class License extends Model
{
    protected $table = 'license';

    /** Maximum number of unique domains allowed per purchase code. */
    public const MAX_DOMAINS = 5;

    protected $fillable = [
        'purchase_code',
        'envato_username',
        'buyer_email',
        'purchase_date',
        'license_type',
        'is_verified',
        'verified_at',
        'app_url',
        'activated_domains',
    ];

    protected $casts = [
        'is_verified'       => 'boolean',
        'verified_at'       => 'datetime',
        'purchase_date'     => 'datetime',
        'activated_domains' => 'array',
    ];

    // ─── Finders ─────────────────────────────────────────────────────────────

    /** Latest license row regardless of domain, or null. */
    public static function current(): ?self
    {
        return static::orderBy('id', 'desc')->first();
    }

    /** Find a license record by purchase code. */
    public static function findByCode(string $code): ?self
    {
        return static::where('purchase_code', $code)->first();
    }

    // ─── Domain management ───────────────────────────────────────────────────

    /**
     * Return the list of domains already activated for this purchase code.
     *
     * @return string[]
     */
    public function getDomains(): array
    {
        return array_values(array_filter((array) ($this->activated_domains ?? [])));
    }

    /**
     * Check whether a domain is already registered for this purchase code.
     */
    public function hasDomain(string $domain): bool
    {
        return in_array($this->normalizeDomain($domain), $this->getDomains(), true);
    }

    /**
     * How many unique domain slots are still available.
     */
    public function remainingSlots(): int
    {
        return max(0, self::MAX_DOMAINS - count($this->getDomains()));
    }

    /**
     * Register a new domain for this license.
     * Returns false if the domain limit has been reached and the domain is new.
     */
    public function addDomain(string $domain): bool
    {
        $normalized = $this->normalizeDomain($domain);
        $existing   = $this->getDomains();

        // Already registered — no slot consumed
        if (in_array($normalized, $existing, true)) {
            return true;
        }

        // Slot limit reached
        if (count($existing) >= self::MAX_DOMAINS) {
            return false;
        }

        $existing[] = $normalized;
        $this->activated_domains = $existing;
        $this->save();

        return true;
    }

    /**
     * Strip scheme and trailing slashes for consistent comparison.
     * e.g. "https://example.com/" → "example.com"
     */
    public function normalizeDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        return strtolower(rtrim($host, '/'));
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    /** Whether this installation has an active verified license. */
    public static function isActivated(): bool
    {
        $license = static::current();
        return $license !== null && $license->is_verified;
    }
}
