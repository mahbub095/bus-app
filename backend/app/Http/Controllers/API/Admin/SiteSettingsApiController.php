<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\SiteSetting;
use App\Services\AdminSessionService;
use App\Services\SiteSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Site settings (dashboard "Site Settings" tab), including the Session
 * Security fields that drive the admin idle timeout and the customer token
 * lifetime.
 */
class SiteSettingsApiController extends BaseAdminApiController
{
    public function __construct(
        protected SiteSettingsService $siteSettingsService,
        protected AdminSessionService $adminSessionService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->settings()]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->mergeCurrentValues($request);

        $this->siteSettingsService->updateFromRequest($request);

        return response()->json([
            'message' => 'Site settings updated successfully.',
            'data' => $this->settings(),
        ]);
    }

    public function uploadFavicon(Request $request): JsonResponse
    {
        $url = $this->siteSettingsService->uploadFavicon($request);

        return response()->json([
            'message' => 'Favicon uploaded successfully.',
            'data' => ['favicon_url' => $url],
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $url = $this->siteSettingsService->uploadLogo($request);

        return response()->json([
            'message' => 'Logo uploaded successfully.',
            'data' => ['logo_url' => $url],
        ]);
    }

    public function deleteLogo(): JsonResponse
    {
        $this->siteSettingsService->deleteLogo();

        return response()->json([
            'message' => 'Logo removed. The default text logo is now active.',
            'data' => ['logo_url' => null],
        ]);
    }

    /**
     * Every editable setting, plus the resolved session policy.
     *
     * @return array<string, mixed>
     */
    protected function settings(): array
    {
        $settings = SiteSetting::getAllCached();

        return [
            'site_title' => $settings['site_title'] ?? 'SonyaBus',
            'logo_text' => $settings['logo_text'] ?? '',
            'logo_url' => $settings['logo_url'] ?? null,
            'favicon_url' => $settings['favicon_url'] ?? '/favicon.svg',
            'footer_company_name' => $settings['footer_company_name'] ?? '',
            'footer_copyright' => $settings['footer_copyright'] ?? '',
            'footer_links' => $this->decodeFooterLinks($settings['footer_links'] ?? null),
            'maintenance_mode' => ($settings['maintenance_mode'] ?? 'false') === 'true',
            'maintenance_message' => $settings['maintenance_message'] ?? '',
            'seo_meta_description' => $settings['seo_meta_description'] ?? '',
            'seo_meta_keywords' => $settings['seo_meta_keywords'] ?? '',
            'seo_og_title' => $settings['seo_og_title'] ?? '',
            'seo_og_description' => $settings['seo_og_description'] ?? '',
            'seo_og_image' => $settings['seo_og_image'] ?? '',
            'seo_google_analytics_id' => $settings['seo_google_analytics_id'] ?? '',
            'session' => [
                'admin_session_lifetime' => $this->adminSessionService->minutes(),
                'customer_session_lifetime' => $this->adminSessionService->customerSettingMinutes(),
                'admin_allowed_range' => [
                    AdminSessionService::MIN_MINUTES,
                    AdminSessionService::MAX_MINUTES,
                ],
                'customer_allowed_range' => [
                    AdminSessionService::MIN_CUSTOMER_MINUTES,
                    AdminSessionService::MAX_CUSTOMER_MINUTES,
                ],
            ],
        ];
    }

    /**
     * The shared service validates three required text fields and reads the
     * maintenance checkbox from the request, so a JSON client sending a partial
     * body (or a real boolean) is normalised here first.
     */
    protected function mergeCurrentValues(Request $request): void
    {
        $current = SiteSetting::getAllCached();

        // The shared service marks these three as required. A JSON client
        // updating one field (e.g. the session timeout) should not have to
        // resend them, so fall back to the stored value — or the shipped
        // default on a fresh install.
        $defaults = [
            'site_title' => 'SonyaBus',
            'footer_company_name' => 'SonyaBus Enterprise',
            'footer_copyright' => '© SonyaBus Enterprise Ltd.',
        ];

        foreach ($defaults as $key => $default) {
            if (! $request->filled($key)) {
                $request->merge([$key => $current[$key] ?? $default]);
            }
        }

        if ($request->has('maintenance_mode')) {
            if ($request->boolean('maintenance_mode')) {
                $request->merge(['maintenance_mode' => 'true']);
            } else {
                // Absent = unchecked, exactly like the HTML checkbox.
                $request->request->remove('maintenance_mode');
            }
        }

        if (is_array($request->input('footer_links'))) {
            $request->merge(['footer_links' => json_encode($request->input('footer_links'))]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function decodeFooterLinks(?string $json): array
    {
        if (! $json) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
