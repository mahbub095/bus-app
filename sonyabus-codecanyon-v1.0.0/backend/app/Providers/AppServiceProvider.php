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
                if (isset($settings['mail_encryption'])) {
                    config(['mail.mailers.smtp.scheme' => $settings['mail_encryption']]);
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
        View::composer('admin.layout', function ($view) {
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

