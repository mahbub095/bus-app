<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Promotion;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\SiteSetting;
use App\Models\SmsConfig;
use App\Models\Station;
use App\Models\User;

class AdminDashboardService
{
    public function __construct(
        protected AdminDashboardAnalyticsService $analyticsService,
    ) {}

    /**
     * @return array{
     *     metrics: array<string, mixed>,
     *     analytics: array<string, mixed>,
     *     stations: \Illuminate\Support\Collection,
     *     buses: \Illuminate\Support\Collection,
     *     routes: \Illuminate\Support\Collection,
     *     schedules: \Illuminate\Support\Collection,
     *     promotions: \Illuminate\Support\Collection,
     *     siteSettings: array<string, mixed>,
     *     smsConfig: \App\Models\SmsConfig|null,
     *     users: \Illuminate\Support\Collection
     * }
     */
    public function getDashboardData(): array
    {
        $analytics = $this->analyticsService->getAnalytics('this_month');

        $metrics = [
            'total_sales' => $analytics['metrics']['sales_revenue'],
            'active_bookings' => $analytics['metrics']['confirmed_bookings'],
            'cancelled_bookings' => $analytics['metrics']['cancelled_bookings'],
            'total_schedules' => Schedule::count(),
        ];

        $routes = Route::with(['departureStation', 'arrivalStation'])
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->through(function ($route) {
                $route->from = $route->departureStation->name ?? '';
                $route->to = $route->arrivalStation->name ?? '';
                return $route;
            });

        $schedules = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        $promotions = Promotion::orderBy('code', 'asc')
            ->paginate(15);

        $buses = Bus::orderBy('operator_name', 'asc')
            ->paginate(15);

        $stations = Station::orderBy('name', 'asc')
            ->paginate(15);

        $allStations = Station::orderBy('name', 'asc')->get();

        $users = User::orderBy('created_at', 'desc')->get();

        return [
            'metrics' => $metrics,
            'analytics' => $analytics,
            'stations' => $stations,
            'allStations' => $allStations,
            'buses' => $buses,
            'routes' => $routes,
            'schedules' => $schedules,
            'promotions' => $promotions,
            'siteSettings' => SiteSetting::getAll(),
            'smsConfig' => SmsConfig::query()->latest('id')->first(),
            'users' => $users,
        ];
    }
}
