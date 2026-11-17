<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use App\Models\SiteSetting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Skip all DB-dependent boot logic during the install wizard.
        // APP_INSTALLED=false means tables may not exist yet.
        $isInstalled = (function () {
            $flag = env('APP_INSTALLED', false);
            if ($flag === true || $flag === 1) return true;
            if (is_string($flag) && in_array(strtolower($flag), ['true', '1'], true)) return true;
            return file_exists(storage_path('framework/installed'));
        })();

        if (! $isInstalled) {
            // Share empty defaults so admin.layout doesn't crash
            View::composer(['admin.layout', 'admin.login', 'admin.dashboard'], function ($view) {
                $view->with('siteSettings', []);
                $view->with('smsConfig', null);
            });
            return;
        }

        // ── Always fix MAIL_ENCRYPTION → scheme mapping ───────────────────────
        // Laravel 12 / Symfony Mailer valid scheme values: 'smtp' (port 587
        // STARTTLS) or 'smtps' (port 465 SSL). Old MAIL_ENCRYPTION values
        // 'tls'/'ssl' must be mapped correctly.
        if (empty(config('mail.mailers.smtp.scheme'))) {
            $raw = env('MAIL_SCHEME') ?: env('MAIL_ENCRYPTION');
            $scheme = match (strtolower((string) $raw)) {
                'ssl', 'smtps' => 'smtps',  // port 465
                'tls', 'smtp'  => 'smtp',   // port 587 STARTTLS
                default        => null,     // auto-negotiate
            };
            if ($scheme) {
                config(['mail.mailers.smtp.scheme' => $scheme]);
            }
        }

        // Dynamic gateway and service configuration overrides from database
        try {
            if (Schema::hasTable('site_settings')) {
                $settings = SiteSetting::getAllCached();

                // Override mail configurations
                if (!empty($settings['mail_mailer'])) {
                    config(['mail.default' => $settings['mail_mailer']]);
                }
                if (!empty($settings['mail_host'])) {
                    config(['mail.mailers.smtp.host' => $settings['mail_host']]);
                }
                if (isset($settings['mail_port'])) {
                    config(['mail.mailers.smtp.port' => (int) $settings['mail_port']]);
                }
                if (isset($settings['mail_username'])) {
                    config(['mail.mailers.smtp.username' => $settings['mail_username']]);
                }
                if (isset($settings['mail_password'])) {
                    config(['mail.mailers.smtp.password' => $settings['mail_password']]);
                }
                if (array_key_exists('mail_encryption', $settings)) {
                    // DB stores 'tls', 'ssl', or null (none).
                    // Symfony Mailer valid values: 'smtp' (587) or 'smtps' (465).
                    $enc = $settings['mail_encryption'];
                    $scheme = match (strtolower((string) $enc)) {
                        'ssl', 'smtps' => 'smtps',
                        'tls', 'smtp'  => 'smtp',
                        default        => null,
                    };
                    config(['mail.mailers.smtp.scheme' => $scheme]);
                }
                if (!empty($settings['mail_from_address'])) {
                    config(['mail.from.address' => $settings['mail_from_address']]);
                }
                if (!empty($settings['mail_from_name'])) {
                    config(['mail.from.name' => $settings['mail_from_name']]);
                }

                // Override ZiniPay configurations
                if (!empty($settings['zinipay_api_key'])) {
                    config(['services.zinipay.api_key' => $settings['zinipay_api_key']]);
                }
                if (!empty($settings['zinipay_base_url'])) {
                    config(['services.zinipay.base_url' => $settings['zinipay_base_url']]);
                }
            }
        } catch (\Exception $e) {
            // Ignore database connection issues during migration / seeding phase
        }

        // Share site settings with the admin layout so the dynamic footer
        // works on all admin pages (dashboard, login, etc.)
        View::composer(['admin.layout', 'admin.login', 'admin.dashboard'], function ($view) {
            try {
                if (Schema::hasTable('site_settings')) {
                    $view->with('siteSettings', SiteSetting::getAllCached());
                } else {
                    $view->with('siteSettings', []);
                }

                if (Schema::hasTable('sms_configs')) {
                    $view->with('smsConfig', \App\Models\SmsConfig::query()->latest('id')->first());
                } else {
                    $view->with('smsConfig', null);
                }
            } catch (\Exception $e) {
                $view->with('siteSettings', []);
                $view->with('smsConfig', null);
            }
        });
    }
}

