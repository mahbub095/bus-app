<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;

class SiteSettingsApiController extends Controller
{
    /**
     * Return all public site settings as JSON for the React frontend.
     */
    public function index()
    {
        $settings = SiteSetting::getAllCached();

        // Parse footer_links from JSON string to array
        $footerLinks = [];
        if (!empty($settings['footer_links'])) {
            $decoded = json_decode($settings['footer_links'], true);
            if (is_array($decoded)) {
                $footerLinks = $decoded;
            }
        }

        return response()->json([
            'site_title' => $settings['site_title'] ?? 'SonyaBus',
            'favicon_url' => $settings['favicon_url'] ?? '/favicon.svg',
            'footer' => [
                'company_name' => $settings['footer_company_name'] ?? 'SonyaBus Enterprise',
                'copyright' => $settings['footer_copyright'] ?? '© 2026 SonyaBus Enterprise Ltd.',
                'links' => $footerLinks,
            ],
            'maintenance' => [
                'enabled' => ($settings['maintenance_mode'] ?? 'false') === 'true',
                'message' => $settings['maintenance_message'] ?? '',
            ],
            'seo' => [
                'meta_description' => $settings['seo_meta_description'] ?? '',
                'meta_keywords' => $settings['seo_meta_keywords'] ?? '',
                'og_title' => $settings['seo_og_title'] ?? '',
                'og_description' => $settings['seo_og_description'] ?? '',
                'og_image' => $settings['seo_og_image'] ?? '',
                'google_analytics_id' => $settings['seo_google_analytics_id'] ?? '',
            ],
        ]);
    }
}
