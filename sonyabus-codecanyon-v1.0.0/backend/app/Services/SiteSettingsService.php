<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

/**
 * Site-wide settings for admin forms and public API.
 */
class SiteSettingsService
{
    /** @return array<string, mixed> */
    public function toPublicApiArray(): array
    {
        $settings = SiteSetting::getAllCached();

        $footerLinks = [];
        if (! empty($settings['footer_links'])) {
            $decoded = json_decode($settings['footer_links'], true);
            if (is_array($decoded)) {
                $footerLinks = $decoded;
            }
        }

        return [
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
        ];
    }

    public function updateFromRequest(Request $request): void
    {
        $request->validate([
            'site_title' => 'required|string|max:255',
            'footer_company_name' => 'required|string|max:255',
            'footer_copyright' => 'required|string|max:500',
            'footer_links' => 'nullable|string',
            'maintenance_mode' => 'nullable|string',
            'maintenance_message' => 'nullable|string|max:1000',
            'seo_meta_description' => 'nullable|string|max:500',
            'seo_meta_keywords' => 'nullable|string|max:500',
            'seo_og_title' => 'nullable|string|max:255',
            'seo_og_description' => 'nullable|string|max:500',
            'seo_og_image' => 'nullable|string|max:500',
            'seo_google_analytics_id' => 'nullable|string|max:100',
        ]);

        SiteSetting::setMany([
            'site_title' => $request->input('site_title'),
            'footer_company_name' => $request->input('footer_company_name'),
            'footer_copyright' => $request->input('footer_copyright'),
            'footer_links' => $request->input('footer_links', '[]'),
            'maintenance_mode' => $request->has('maintenance_mode') ? 'true' : 'false',
            'maintenance_message' => $request->input('maintenance_message', ''),
            'seo_meta_description' => $request->input('seo_meta_description', ''),
            'seo_meta_keywords' => $request->input('seo_meta_keywords', ''),
            'seo_og_title' => $request->input('seo_og_title', ''),
            'seo_og_description' => $request->input('seo_og_description', ''),
            'seo_og_image' => $request->input('seo_og_image', ''),
            'seo_google_analytics_id' => $request->input('seo_google_analytics_id', ''),
        ]);
    }

    public function uploadFavicon(Request $request): string
    {
        $request->validate([
            'favicon' => 'required|file|mimes:ico,png,svg,jpg,jpeg,gif,webp|max:512',
        ]);

        $file = $request->file('favicon');
        $filename = 'favicon.'.$file->getClientOriginalExtension();
        $file->move(public_path('uploads'), $filename);

        $faviconUrl = '/uploads/'.$filename;
        SiteSetting::setValue('favicon_url', $faviconUrl);

        return $faviconUrl;
    }
}
