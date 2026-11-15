<?php

namespace App\Services;

use App\Models\SiteSetting;
use Throwable;

/**
 * Resolves the session/login lifetimes configured by the site owner under
 * Site Settings → Session Security.
 *
 *  - admin_session_lifetime     → idle timeout of the Blade admin panel
 *  - customer_session_lifetime  → lifetime of a customer Sanctum API token
 *                                 (0 means the token never expires)
 */
class AdminSessionService
{
    /** Idle timeout of the admin panel, stored in site_settings. */
    public const SETTING_KEY = 'admin_session_lifetime';

    /** Lifetime of a customer API token, stored in site_settings. */
    public const CUSTOMER_SETTING_KEY = 'customer_session_lifetime';

    /** Admin panel idle timeout used when the setting is missing or invalid. */
    public const DEFAULT_MINUTES = 120;

    /** Customer token lifetime used when the setting is missing or invalid. */
    public const DEFAULT_CUSTOMER_MINUTES = 1440; // 24 hours

    /** Guard rails: never instantly lock the operator out, never run forever. */
    public const MIN_MINUTES = 1;
    public const MAX_MINUTES = 10080; // 7 days

    /** Customer tokens accept "0 = never expire" plus a 30 day ceiling. */
    public const MIN_CUSTOMER_MINUTES = 0;
    public const MAX_CUSTOMER_MINUTES = 43200; // 30 days

    /**
     * Admin panel idle timeout in minutes, clamped to the supported range.
     */
    public function minutes(): int
    {
        return $this->clamp(
            $this->read(self::SETTING_KEY),
            self::DEFAULT_MINUTES,
            self::MIN_MINUTES,
            self::MAX_MINUTES
        );
    }

    /**
     * Admin panel idle timeout in seconds.
     */
    public function seconds(): int
    {
        return $this->minutes() * 60;
    }

    /**
     * Customer API token lifetime in minutes as stored (0 = never expires).
     */
    public function customerSettingMinutes(): int
    {
        return $this->clamp(
            $this->read(self::CUSTOMER_SETTING_KEY),
            self::DEFAULT_CUSTOMER_MINUTES,
            self::MIN_CUSTOMER_MINUTES,
            self::MAX_CUSTOMER_MINUTES
        );
    }

    /**
     * Customer API token lifetime in minutes, or null when tokens should
     * never expire.
     */
    public function customerMinutes(): ?int
    {
        $minutes = $this->customerSettingMinutes();

        return $minutes === 0 ? null : $minutes;
    }

    /**
     * Timestamp at which a token issued right now stops being valid,
     * or null when customer tokens never expire.
     */
    public function customerTokenExpiresAt(): ?\Illuminate\Support\Carbon
    {
        $minutes = $this->customerMinutes();

        return $minutes === null ? null : now()->addMinutes($minutes);
    }

    /**
     * Raw setting value, or null when it cannot be read.
     */
    private function read(string $key): ?string
    {
        try {
            $value = SiteSetting::getValue($key);
        } catch (Throwable) {
            // Database unavailable (install wizard, migration in progress).
            return null;
        }

        return is_numeric($value) ? (string) $value : null;
    }

    /**
     * Apply the default and the allowed range to a raw setting value.
     */
    private function clamp(?string $value, int $default, int $min, int $max): int
    {
        if ($value === null) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }
}
