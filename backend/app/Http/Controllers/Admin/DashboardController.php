<?php

namespace App\Http\Controllers\Admin;

use App\Services\AdminDashboardService;
use App\Services\AdminSessionService;
use Illuminate\Http\Request;

class DashboardController extends BaseAdminController
{
    public function __construct(
        protected AdminDashboardService $adminDashboardService,
        protected AdminSessionService $adminSessionService,
    ) {
    }

    public function dashboardView(Request $request)
    {
        // Per-tab search terms for the Coaches and Schedules lists.
        $request->validate([
            'buses_search'     => 'nullable|string|max:100',
            'schedules_search' => 'nullable|string|max:100',
        ]);

        return view('admin.dashboard', array_merge(
            $this->adminDashboardService->getDashboardData(),
            // Session & Security panel needs the active value, not a guess.
            ['adminSessionMinutes' => $this->adminSessionService->minutes()]
        ));
    }
}
