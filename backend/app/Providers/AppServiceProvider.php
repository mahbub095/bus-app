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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share site settings with the admin layout so the dynamic footer
        // works on all admin pages (dashboard, login, etc.)
        View::composer('admin.layout', function ($view) {
            try {
                if (Schema::hasTable('site_settings')) {
                    $view->with('siteSettings', SiteSetting::getAllCached());
                } else {
                    $view->with('siteSettings', []);
                }
            } catch (\Exception $e) {
                $view->with('siteSettings', []);
            }
        });
    }
}

