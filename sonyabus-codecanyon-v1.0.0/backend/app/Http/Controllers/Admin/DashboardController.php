<?php

namespace App\Http\Controllers\Admin;

use App\Services\AdminDashboardService;
use Illuminate\Http\Request;

class DashboardController extends BaseAdminController
{
    public function __construct(protected AdminDashboardService $adminDashboardService)
    {
    }

    public function dashboardView(Request $request)
    {
        // Per-tab search terms for the Coaches and Schedules lists.
        $request->validate([
            'buses_search'     => 'nullable|string|max:100',
            'schedules_search' => 'nullable|string|max:100',
        ]);

        return view('admin.dashboard', $this->adminDashboardService->getDashboardData());
    }
}
