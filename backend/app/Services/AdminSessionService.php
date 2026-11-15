<?php

namespace App\Services;

use App\Models\SiteSetting;
use Throwable;

/**
 * Resolves the admin panel session expiry configured by the site owner under
 * Site Settings → Session & Security ("admin_session_lifetime", in minutes).
 *
 * Customer logins use Sanctum API tokens and are not affected by this value.
 */
class AdminSessionService
{
    /** Key stored in the site_settings table. */
    public const SETTING_KEY = 'admin_session_lifetime';

    /** Idle timeout used when the setting is missing or invalid. */
    public const DEFAULT_MINUTES = 120;

    /** Guard rails: never instantly lock the operator out, never run forever. */
    public const MIN_MINUTES = 1;
    public const MAX_MINUTES = 10080; // 7 days

    /**
     * Idle timeout in minutes, clamped to the supported range.
     */
    public function minutes(): int
    {
        try {
            $value = SiteSetting::getValue(self::SETTING_KEY);
        } catch (Throwable) {
            // Database unavailable (install wizard, migration in progress).
            return self::DEFAULT_MINUTES;
        }

        if (! is_numeric($value)) {
            return self::DEFAULT_MINUTES;
        }

        return max(self::MIN_MINUTES, min(self::MAX_MINUTES, (int) $value));
    }
}
