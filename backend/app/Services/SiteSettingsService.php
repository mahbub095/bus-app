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
            'logo_url' => $settings['logo_url'] ?? null,
            'logo_text' => $settings['logo_text'] ?? null,
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
            'logo_text' => 'nullable|string|max:50',
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
            'logo_text' => $request->input('logo_text', ''),
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

        $file    = $request->file('favicon');
        // Derive extension from the validated MIME type, not the client filename,
        // to prevent extension spoofing (e.g. evil.php renamed to image.png).
        $ext      = $this->safeExtensionFromMime($file->getMimeType(), ['ico', 'png', 'svg', 'jpg', 'jpeg', 'gif', 'webp']);
        $filename = 'favicon.' . $ext;

        $this->ensureUploadDirectory();
        $file->move(public_path('uploads'), $filename);

        $faviconUrl = '/uploads/' . $filename;
        SiteSetting::setValue('favicon_url', $faviconUrl);
        SiteSetting::clearCache();

        return $faviconUrl;
    }

    public function uploadLogo(Request $request): string
    {
        $request->validate([
            'logo' => 'required|file|mimes:png,svg,jpg,jpeg,gif,webp|max:1024',
        ]);

        $file     = $request->file('logo');
        $ext      = $this->safeExtensionFromMime($file->getMimeType(), ['png', 'svg', 'jpg', 'jpeg', 'gif', 'webp']);
        $filename = 'logo.' . $ext;

        $this->ensureUploadDirectory();
        $file->move(public_path('uploads'), $filename);

        $logoUrl = '/uploads/' . $filename;
        SiteSetting::setValue('logo_url', $logoUrl);
        SiteSetting::clearCache();

        return $logoUrl;
    }

    public function deleteLogo(): void
    {
        $existing = SiteSetting::getValue('logo_url');
        if ($existing && str_starts_with($existing, '/uploads/logo.')) {
            $fullPath = public_path(ltrim($existing, '/'));
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        SiteSetting::setValue('logo_url', '');
        SiteSetting::clearCache();
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Map a MIME type to a safe, whitelisted file extension.
     * Falls back to the first allowed extension if the MIME is not recognised.
     */
    private function safeExtensionFromMime(string $mimeType, array $allowed): string
    {
        $map = [
            'image/png'               => 'png',
            'image/jpeg'              => 'jpg',
            'image/gif'               => 'gif',
            'image/webp'              => 'webp',
            'image/svg+xml'           => 'svg',
            'image/x-icon'            => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
        ];

        $ext = $map[$mimeType] ?? null;

        // Ensure the resolved extension is in the allowed list
        if ($ext && in_array($ext, $allowed, true)) {
            return $ext;
        }

        return $allowed[0];
    }

    /**
     * Create the uploads directory if it does not already exist.
     */
    private function ensureUploadDirectory(): void
    {
        $dir = public_path('uploads');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
