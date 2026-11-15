<?php

namespace App\Http\Controllers\Admin;

use App\Services\SiteSettingsService;
use Illuminate\Http\Request;

class SiteSettingsController extends BaseAdminController
{
    public function __construct(protected SiteSettingsService $siteSettingsService)
    {
    }

    public function update(Request $request)
    {
        $this->siteSettingsService->updateFromRequest($request);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Site settings updated successfully.')
            ->withInput(['admin_tab' => 'site-settings']);
    }

    public function uploadFavicon(Request $request)
    {
        $this->siteSettingsService->uploadFavicon($request);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Favicon uploaded successfully.')
            ->withInput(['admin_tab' => 'site-settings']);
    }

    public function uploadLogo(Request $request)
    {
        $this->siteSettingsService->uploadLogo($request);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Logo uploaded successfully.')
            ->withInput(['admin_tab' => 'site-settings']);
    }

    public function deleteLogo(Request $request)
    {
        $this->siteSettingsService->deleteLogo();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Logo removed. The default text logo is now active.')
            ->withInput(['admin_tab' => 'site-settings']);
    }
}
