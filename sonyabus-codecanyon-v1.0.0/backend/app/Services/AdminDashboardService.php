<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Promotion;
use App\Models\Route;
use App\Models\Schedule;
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
        $busSearch      = trim((string) request()->query('buses_search', ''));
        $scheduleSearch = trim((string) request()->query('schedules_search', ''));

        $analytics = $this->analyticsService->getAnalytics('this_month');

        $metrics = [
            'total_sales' => $analytics['metrics']['sales_revenue'],
            'active_bookings' => $analytics['metrics']['confirmed_bookings'],
            'cancelled_bookings' => $analytics['metrics']['cancelled_bookings'],
            'total_schedules' => Schedule::count(),
        ];

        $routes = Route::with(['departureStation', 'arrivalStation'])
            ->orderBy('id', 'desc')
            ->paginate(15, ['*'], 'routes_page')
            ->through(function ($route) {
                $route->from = $route->departureStation->name ?? '';
                $route->to = $route->arrivalStation->name ?? '';
                return $route;
            });

        $schedules = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])
            ->when($scheduleSearch !== '', function ($query) use ($scheduleSearch) {
                $like = $this->likeTerm($scheduleSearch);
                // "Operator & Coach" column — either the operator company name or
                // the coach registration number can be typed into the box.
                $query->whereHas('bus', function ($bus) use ($like) {
                    $bus->where('operator_name', 'like', $like)
                        ->orWhere('coach_number', 'like', $like);
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(15, ['*'], 'schedules_page');

        $promotions = Promotion::orderBy('code', 'asc')
            ->paginate(15, ['*'], 'promotions_page');

        $buses = Bus::when($busSearch !== '', function ($query) use ($busSearch) {
            $query->where('coach_number', 'like', $this->likeTerm($busSearch));
        })
            ->orderBy('operator_name', 'asc')
            ->paginate(15, ['*'], 'buses_page');

        $stations = Station::orderBy('name', 'asc')
            ->paginate(15, ['*'], 'stations_page');

        $allStations = Station::orderBy('name', 'asc')->get();

        $users = User::orderBy('created_at', 'desc')->paginate(15, ['*'], 'users_page');

        return [
            'metrics'     => $metrics,
            'analytics'   => $analytics,
            'stations'    => $stations,
            'allStations' => $allStations,
            'buses'       => $buses,
            'routes'      => $routes,
            'schedules'   => $schedules,
            'promotions'  => $promotions,
            'users'       => $users,
        ];
    }

    /**
     * Build a LIKE pattern, escaping wildcards so a literal "%" or "_" typed
     * into a search box is matched as text instead of as a wildcard.
     */
    private function likeTerm(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
