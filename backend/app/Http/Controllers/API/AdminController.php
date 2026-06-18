<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Station;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Promotion;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Get the Admin Dashboard data.
     */
    public function dashboard()
    {
        $metrics = [
            'total_sales' => Booking::whereIn('status', ['PAID', 'SOLD', 'BOOKED'])->sum('total_fare'),
            'active_bookings' => Booking::whereIn('status', ['PAID', 'SOLD', 'BOOKED'])->count(),
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
        ->get()
        ->map(function($b) {
            return $this->formatBooking($b);
        });

        $cancelRequests = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->where('status', 'CANCEL_REQUESTED')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function($b) {
                return $this->formatBooking($b);
            });

        $stations = Station::orderBy('name', 'asc')->get();
        $buses = Bus::orderBy('operator_name', 'asc')->get();
        
        $routes = Route::with(['departureStation', 'arrivalStation'])
            ->limit(100)
            ->get()
            ->map(function($r) {
                $r->from = $r->departureStation->name ?? '';
                $r->to = $r->arrivalStation->name ?? '';
                return $r;
            });

        $schedules = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])
            ->limit(100)
            ->get();
            
        $promotions = Promotion::orderBy('code', 'asc')->get();
        $siteSettings = SiteSetting::getAll();
        $users = User::orderBy('created_at', 'desc')->get();

        return response()->json([
            'metrics' => $metrics,
            'recentBookings' => $recentBookings,
            'cancelRequests' => $cancelRequests,
            'stations' => $stations,
            'buses' => $buses,
            'routes' => $routes,
            'schedules' => $schedules,
            'promotions' => $promotions,
            'siteSettings' => $siteSettings,
            'users' => $users
        ]);
    }

    protected function formatBooking(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'pnr' => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'passenger_name' => $booking->passenger_name,
            'passenger_phone' => $booking->passenger_phone,
            'passenger_email' => $booking->passenger_email,
            'seat_numbers' => $booking->seat_numbers,
            'total_fare' => floatval($booking->total_fare),
            'payment_method' => $booking->payment_method,
            'payment_invoice_id' => $booking->payment_invoice_id,
            'status' => $booking->status,
            'created_at' => $booking->created_at ? $booking->created_at->toIso8601String() : null,
            'updated_at' => $booking->updated_at ? $booking->updated_at->toIso8601String() : null,
            'schedule' => $booking->schedule ? [
                'departure_time' => $booking->schedule->departure_time ? $booking->schedule->departure_time->toIso8601String() : null,
                'arrival_time' => $booking->schedule->arrival_time ? $booking->schedule->arrival_time->toIso8601String() : null,
                'bus' => $booking->schedule->bus ? [
                    'operator_name' => $booking->schedule->bus->operator_name,
                    'coach_number' => $booking->schedule->bus->coach_number,
                    'coach_type' => $booking->schedule->bus->coach_type,
                ] : null,
                'route' => $booking->schedule->route ? [
                    'from' => $booking->schedule->route->departureStation ? $booking->schedule->route->departureStation->name : '',
                    'to' => $booking->schedule->route->arrivalStation ? $booking->schedule->route->arrivalStation->name : '',
                    'duration' => $booking->schedule->route->duration,
                ] : null,
            ] : null,
        ];
    }
}
