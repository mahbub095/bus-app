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
use App\Services\SeatMapService;
use App\Services\ZinipayService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminController extends Controller
{
    public function __construct(
        protected SeatMapService $seatMapService,
        protected ZinipayService $zinipayService
    ) {
    }

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

    /**
     * Get booking logs (from AjaxController).
     */
    public function bookingLogs()
    {
        $recentBookings = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        $formattedBookings = $recentBookings->map(function ($b) {
            return [
                'id' => $b->id,
                'pnr' => 'SE' . str_pad($b->id, 5, '0', STR_PAD_LEFT),
                'passenger_name' => $b->passenger_name,
                'passenger_phone' => $b->passenger_phone,
                'passenger_email' => $b->passenger_email,
                'seat_numbers' => $b->seat_numbers,
                'total_fare' => (float) $b->total_fare,
                'status' => $b->status,
                'payment_method' => $b->payment_method,
                'created_at' => optional($b->created_at)->toIso8601String(),
                'schedule' => [
                    'departure_time' => optional($b->schedule?->departure_time)->toIso8601String(),
                    'bus' => [
                        'operator_name' => $b->schedule?->bus?->operator_name,
                    ],
                    'route' => [
                        'from' => $b->schedule?->route?->departureStation?->name,
                        'to' => $b->schedule?->route?->arrivalStation?->name,
                    ],
                ],
            ];
        })->values();

        return response()->json([
            'bookings' => $formattedBookings,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get cancel requests logs (from AjaxController).
     */
    public function cancelRequestsLogs()
    {
        $cancelRequests = Booking::where('status', 'CANCEL_REQUESTED')
            ->with([
                'schedule.bus',
                'schedule.route.departureStation',
                'schedule.route.arrivalStation',
            ])
            ->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get();

        $formattedCancelRequests = $cancelRequests->map(function ($b) {
            return [
                'id' => $b->id,
                'pnr' => 'SE' . str_pad($b->id, 5, '0', STR_PAD_LEFT),
                'passenger_name' => $b->passenger_name,
                'passenger_phone' => $b->passenger_phone,
                'passenger_email' => $b->passenger_email,
                'seat_numbers' => $b->seat_numbers,
                'total_fare' => (float) $b->total_fare,
                'status' => $b->status,
                'created_at' => optional($b->created_at)->toIso8601String(),
                'updated_at' => optional($b->updated_at)->toIso8601String(),
                'schedule' => [
                    'departure_time' => optional($b->schedule?->departure_time)->toIso8601String(),
                    'bus' => [
                        'operator_name' => $b->schedule?->bus?->operator_name,
                    ],
                    'route' => [
                        'from' => $b->schedule?->route?->departureStation?->name,
                        'to' => $b->schedule?->route?->arrivalStation?->name,
                    ],
                ],
            ];
        })->values();

        return response()->json([
            'cancel_requests' => $formattedCancelRequests,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Search coach services (from AjaxController).
     */
    public function searchCoachServices(Request $request)
    {
        $request->validate([
            'from' => 'required|exists:stations,id',
            'to' => 'required|exists:stations,id',
            'date' => 'required|date_format:Y-m-d',
            'coach_type' => 'nullable|string',
        ]);

        $fromId = $request->query('from');
        $toId = $request->query('to');
        $date = $request->query('date');
        $coachType = $request->query('coach_type');

        $route = Route::where('departure_station_id', $fromId)
            ->where('arrival_station_id', $toId)
            ->first();

        if (! $route) {
            return response()->json([]);
        }

        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        $schedules = Schedule::where('route_id', $route->id)
            ->whereBetween('departure_time', [$startDate, $endDate])
            ->with([
                'bus',
                'route.departureStation',
                'route.arrivalStation',
                'bookings' => fn ($q) => SeatMapService::scopePaidBookingsForSeatMap($q),
            ])
            ->get();

        if ($coachType && $coachType !== 'All') {
            $schedules = $schedules->filter(function ($sched) use ($coachType) {
                return strtolower($sched->bus->coach_type) === strtolower($coachType);
            })->values();
        }

        $formattedSchedules = $schedules->map(function ($sched) {
            $seatPayload = $this->seatMapService->formatSchedulePayload($sched, $sched->bookings);
            $seatBookings = $this->seatMapService->seatBookingDetails($sched, $sched->bookings);
            $availableCount = count(array_filter(
                $seatPayload['seat_map'],
                fn ($status) => $status === 'available'
            ));

            return [
                'id' => $sched->id,
                'departure_time' => $sched->departure_time->toIso8601String(),
                'arrival_time' => $sched->arrival_time->toIso8601String(),
                'fare' => floatval($sched->fare),
                'bus' => [
                    'id' => $sched->bus->id,
                    'operator_name' => $sched->bus->operator_name,
                    'coach_number' => $sched->bus->coach_number,
                    'coach_type' => $sched->bus->coach_type,
                    'total_seats' => $sched->bus->total_seats,
                    'seat_layout' => $sched->bus->seat_layout,
                    'seat_layout_grid' => $sched->bus->seat_layout_grid,
                ],
                'route' => [
                    'id' => $sched->route->id,
                    'distance' => $sched->route->distance,
                    'duration' => $sched->route->duration,
                    'from' => $sched->route->departureStation->name ?? '',
                    'to' => $sched->route->arrivalStation->name ?? '',
                ],
                'booked_seats' => $seatPayload['booked_seats'],
                'seat_map' => $seatPayload['seat_map'],
                'seat_bookings' => $seatBookings,
                'boarding_points' => $seatPayload['boarding_points'],
                'dropping_points' => $seatPayload['dropping_points'],
                'seat_class' => $seatPayload['seat_class'],
                'pricing' => $seatPayload['pricing'],
                'available_seats_count' => $availableCount,
            ];
        });

        return response()->json($formattedSchedules);
    }

    /**
     * Toggle blocked seat (from AjaxController).
     */
    public function toggleBlockedSeat(Request $request, int $id)
    {
        $request->validate([
            'seat' => ['required', 'string', 'regex:/^(L-|U-)?[A-Z][1-4]$/i'],
        ]);

        $schedule = Schedule::find($id);

        if (! $schedule) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $seat = strtoupper($request->input('seat'));

        $bookings = $schedule->bookings()
            ->where(function ($q) {
                $q->whereIn('status', ['PAID', 'SOLD', 'BOOKED'])
                  ->orWhere(function ($qp) {
                      $qp->where('status', 'PENDING')
                         ->where('created_at', '>=', now()->subMinutes(10));
                  });
            })
            ->select(SeatMapService::paidBookingColumns())
            ->get();

        try {
            $result = $this->seatMapService->toggleBlockedSeat($schedule, $seat, $bookings);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $schedule->refresh();
        $bookings = $schedule->bookings()
            ->where(function ($q) {
                $q->whereIn('status', ['PAID', 'SOLD', 'BOOKED'])
                  ->orWhere(function ($qp) {
                      $qp->where('status', 'PENDING')
                         ->where('created_at', '>=', now()->subMinutes(10));
                  });
            })
            ->select(SeatMapService::paidBookingColumns())
            ->get();

        $seatPayload = $this->seatMapService->formatSchedulePayload($schedule, $bookings);

        return response()->json([
            'message' => $result['blocked'] ? "Seat {$seat} blocked." : "Seat {$seat} unblocked.",
            'seat' => $result['seat'],
            'blocked' => $result['blocked'],
            'blocked_seats' => $result['blocked_seats'],
            'seat_map' => $seatPayload['seat_map'],
            'booked_seats' => $seatPayload['booked_seats'],
            'available_seats_count' => count(array_filter(
                $seatPayload['seat_map'],
                fn ($status) => $status === 'available'
            )),
        ]);
    }

    /**
     * Cancel booking (from AjaxController).
     */
    public function cancelBooking($id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        if ($booking->status === 'CANCELLED') {
            return response()->json(['message' => 'Ticket is already cancelled.'], 400);
        }

        $booking->update(['status' => 'CANCELLED']);

        return response()->json([
            'message' => 'Booking successfully cancelled and seat released!',
            'booking_id' => $booking->id,
            'status' => 'CANCELLED',
        ]);
    }

    /**
     * Approve cancel request (from BookingController).
     */
    public function approveCancelRequest($id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        if ($booking->status !== 'CANCEL_REQUESTED') {
            return response()->json(['message' => 'This booking has no pending cancellation request.'], 400);
        }

        $booking->update(['status' => 'CANCELLED']);

        return response()->json([
            'message' => 'Cancellation request approved successfully. Booking is now cancelled.',
            'booking_id' => $booking->id,
            'status' => 'CANCELLED',
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
