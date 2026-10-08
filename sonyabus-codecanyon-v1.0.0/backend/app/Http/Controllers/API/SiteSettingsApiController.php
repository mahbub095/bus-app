<?php

namespace App\Http\Controllers\API;

use App\Services\SiteSettingsService;

class SiteSettingsApiController extends BaseController
{
    public function __construct(protected SiteSettingsService $siteSettingsService)
    {
    }

    public function index()
    {
        return response()->json($this->siteSettingsService->toPublicApiArray());
    }
}
