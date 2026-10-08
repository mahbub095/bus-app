<?php

namespace App\Http\Controllers\Admin;

use App\Services\AdminDashboardService;

class DashboardController extends BaseAdminController
{
    public function __construct(protected AdminDashboardService $adminDashboardService)
    {
    }

    public function dashboardView()
    {
        return view('admin.dashboard', $this->adminDashboardService->getDashboardData());
    }
}
