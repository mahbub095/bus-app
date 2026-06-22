<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\Promotion;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\SiteSetting;
use App\Models\Station;
use App\Models\User;

class AdminDashboardService
{
    /**
     * @return array{
     *     metrics: array<string, mixed>,
     *     stations: \Illuminate\Support\Collection,
     *     buses: \Illuminate\Support\Collection,
     *     routes: \Illuminate\Support\Collection,
     *     schedules: \Illuminate\Support\Collection,
     *     promotions: \Illuminate\Support\Collection,
     *     siteSettings: array<string, mixed>,
     *     users: \Illuminate\Support\Collection
     * }
     */
    public function getDashboardData(): array
    {
        $metrics = [
            'total_sales' => Booking::whereIn('status', ['PAID', 'SOLD', 'BOOKED'])->sum('total_fare'),
            'active_bookings' => Booking::whereIn('status', ['PAID', 'SOLD', 'BOOKED'])->count(),
            'cancelled_bookings' => Booking::where('status', 'CANCELLED')->count(),
            'total_schedules' => Schedule::count(),
        ];

        $routes = Route::with(['departureStation', 'arrivalStation'])
            ->limit(100)
            ->get()
            ->map(function ($route) {
                $route->from = $route->departureStation->name ?? '';
                $route->to = $route->arrivalStation->name ?? '';

                return $route;
            });

        return [
            'metrics' => $metrics,
            'stations' => Station::orderBy('name', 'asc')->get(),
            'buses' => Bus::orderBy('operator_name', 'asc')->get(),
            'routes' => $routes,
            'schedules' => Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])
                ->limit(100)
                ->get(),
            'promotions' => Promotion::orderBy('code', 'asc')->get(),
            'siteSettings' => SiteSetting::getAll(),
            'users' => User::orderBy('created_at', 'desc')->get(),
        ];
    }
}
