<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Station;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Promotion;
use App\Models\SmsConfig;

class AdminController extends Controller
{
    /**
     * Render the Blade Admin Dashboard view.
     */
    public function dashboardView()
    {
        $metrics = [
            'total_sales' => Booking::where('status', 'PAID')->sum('total_fare'),
            'active_bookings' => Booking::where('status', 'PAID')->count(),
            'cancelled_bookings' => Booking::where('status', 'CANCELLED')->count(),
            'total_schedules' => Schedule::count(),
        ];

        $recentBookings = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation'
        ])
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get();

        $cancelRequests = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->where('status', 'CANCEL_REQUESTED')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        $stations = Station::orderBy('name', 'asc')->get();
        $buses = Bus::orderBy('operator_name', 'asc')->get();
        
        $routes = Route::with(['departureStation', 'arrivalStation'])
            ->limit(100)
            ->get()
            ->map(function($r) {
                $r->from = $r->departureStation->name;
                $r->to = $r->arrivalStation->name;
                return $r;
            });

        $schedules = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])
            ->limit(100)
            ->get();
        $promotions = Promotion::orderBy('code', 'asc')->get();
        $smsConfig = SmsConfig::query()->latest('id')->first();

        return view('admin.dashboard', compact(
            'metrics',
            'recentBookings',
            'cancelRequests',
            'stations',
            'buses',
            'routes',
            'schedules',
            'promotions',
            'smsConfig'
        ));
    }

}
