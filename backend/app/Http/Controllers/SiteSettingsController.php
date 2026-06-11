<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

class SiteSettingsController extends Controller
{
    /**
     * Bulk update all site settings from the admin dashboard form.
     */
    public function update(Request $request)
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

        $settings = [
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
        ];

        SiteSetting::setMany($settings);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Site settings updated successfully.')
            ->withInput(['admin_tab' => 'site-settings']);
    }

    /**
     * Handle favicon file upload.
     */
    public function uploadFavicon(Request $request)
    {
        $request->validate([
            'favicon' => 'required|file|mimes:ico,png,svg,jpg,jpeg,gif,webp|max:512',
        ]);

        $file = $request->file('favicon');
        $filename = 'favicon.' . $file->getClientOriginalExtension();

        // Store in public directory for direct access
        $file->move(public_path('uploads'), $filename);

        $faviconUrl = '/uploads/' . $filename;
        SiteSetting::setValue('favicon_url', $faviconUrl);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Favicon uploaded successfully.')
            ->withInput(['admin_tab' => 'site-settings']);
    }
}
