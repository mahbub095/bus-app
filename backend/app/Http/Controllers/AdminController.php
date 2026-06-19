<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardService;

class AdminController extends Controller
{
    public function __construct(protected AdminDashboardService $adminDashboardService)
    {
    }

    /**
     * Render the Blade Admin Dashboard view.
     */
    public function dashboardView()
    {
        return view('admin.dashboard', $this->adminDashboardService->getDashboardData());
    }
}
