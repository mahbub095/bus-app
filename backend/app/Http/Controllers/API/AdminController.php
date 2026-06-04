<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Bus;
use App\Models\Promotion;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Station;
use App\Services\SeatMapService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminController extends Controller
{
    public function __construct(protected SeatMapService $seatMapService)
    {
    }

    public function dashboard()
    {
        $totalSales = Booking::where('status', 'PAID')->sum('total_fare');
        $activeBookingsCount = Booking::where('status', 'PAID')->count();
        $cancelledBookingsCount = Booking::where('status', 'CANCELLED')->count();
        $totalSchedules = Schedule::count();

        $recentBookings = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation'
        ])
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get();

        $formattedBookings = $recentBookings->map(function ($b) {
            return [
                'id' => $b->id,
                'pnr' => 'SE' . str_pad($b->id, 5, '0', STR_PAD_LEFT),
                'passenger_name' => $b->passenger_name,
                'passenger_phone' => $b->passenger_phone,
                'passenger_email' => $b->passenger_email,
                'seat_numbers' => $b->seat_numbers,
                'total_fare' => floatval($b->total_fare),
                'status' => $b->status,
                'created_at' => $b->created_at->toIso8601String(),
                'schedule' => [
                    'departure_time' => $b->schedule->departure_time->toIso8601String(),
                    'bus' => [
                        'operator_name' => $b->schedule->bus->operator_name,
                        'coach_type' => $b->schedule->bus->coach_type,
                    ],
                    'route' => [
                        'from' => $b->schedule->route->departureStation->name,
                        'to' => $b->schedule->route->arrivalStation->name,
                    ]
                ]
            ];
        });

        $stations = Station::orderBy('name', 'asc')->get();
        $buses = Bus::orderBy('operator_name', 'asc')->get();
        $routes = Route::with(['departureStation', 'arrivalStation'])
            ->limit(100)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'departure_station_id' => $r->departure_station_id,
                    'arrival_station_id' => $r->arrival_station_id,
                    'from' => $r->departureStation->name,
                    'to' => $r->arrivalStation->name,
                    'distance' => $r->distance,
                    'duration' => $r->duration,
                ];
            });

        $schedules = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])
            ->limit(100)
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'bus_id' => $s->bus_id,
                    'route_id' => $s->route_id,
                    'bus_operator' => $s->bus->operator_name,
                    'coach_number' => $s->bus->coach_number,
                    'coach_type' => $s->bus->coach_type,
                    'route_from' => $s->route->departureStation->name,
                    'route_to' => $s->route->arrivalStation->name,
                    'departure_time' => $s->departure_time->toIso8601String(),
                    'arrival_time' => $s->arrival_time->toIso8601String(),
                    'fare' => floatval($s->fare),
                ];
            });

        $promotions = Promotion::orderBy('code', 'asc')->get();

        return response()->json([
            'metrics' => [
                'total_sales' => floatval($totalSales),
                'active_bookings' => $activeBookingsCount,
                'cancelled_bookings' => $cancelledBookingsCount,
                'total_schedules' => $totalSchedules,
            ],
            'recent_bookings' => $formattedBookings,
            'stations' => $stations,
            'buses' => $buses,
            'routes' => $routes,
            'schedules' => $schedules,
            'promotions' => $promotions,
        ]);
    }

    public function bookingLogsApi()
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

    public function cancelRequestsLogsApi()
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

    public function toggleBlockedSeat(Request $request, int $id)
    {
        $request->validate([
            'seat' => ['required', 'string', 'regex:/^[A-I][1-4]$/i'],
        ]);

        $schedule = Schedule::find($id);

        if (! $schedule) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $seat = strtoupper($request->input('seat'));

        $bookings = $schedule->bookings()
            ->where('status', 'PAID')
            ->select(SeatMapService::paidBookingColumns())
            ->get();

        try {
            $result = $this->seatMapService->toggleBlockedSeat($schedule, $seat, $bookings);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $schedule->refresh();
        $bookings = $schedule->bookings()
            ->where('status', 'PAID')
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

    public function cancelBookingApi($id)
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

    public function storeStation(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:stations,name',
            'district' => 'nullable|string|max:100',
        ]);

        $validated['name'] = strtoupper($validated['name']);
        $station = Station::create($validated);

        return response()->json(['message' => 'Station successfully created!', 'station' => $station], 201);
    }

    public function storeBus(Request $request)
    {
        $validated = $request->validate([
            'operator_name' => 'required|string|max:100',
            'coach_number' => 'required|string|max:50|unique:buses,coach_number',
            'coach_type' => 'required|in:AC,Non AC',
            'total_seats' => 'required|integer|min:10|max:100',
        ]);

        $bus = Bus::create($validated);

        return response()->json(['message' => 'Bus successfully created!', 'bus' => $bus], 201);
    }

    public function storeRoute(Request $request)
    {
        $validated = $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'distance' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50',
        ]);

        $exists = Route::where('departure_station_id', $validated['departure_station_id'])
            ->where('arrival_station_id', $validated['arrival_station_id'])
            ->first();

        if ($exists) {
            return response()->json(['message' => 'A route between these stations already exists.'], 422);
        }

        $route = Route::create($validated);
        $routeLoaded = Route::with(['departureStation', 'arrivalStation'])->find($route->id);

        return response()->json([
            'message' => 'Route successfully created!',
            'route' => [
                'id' => $routeLoaded->id,
                'departure_station_id' => $routeLoaded->departure_station_id,
                'arrival_station_id' => $routeLoaded->arrival_station_id,
                'from' => $routeLoaded->departureStation->name,
                'to' => $routeLoaded->arrivalStation->name,
                'distance' => $routeLoaded->distance,
                'duration' => $routeLoaded->duration,
            ],
        ], 201);
    }

    public function storeSchedule(Request $request)
    {
        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date|after_or_equal:today',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0',
        ]);

        $schedule = Schedule::create($validated);
        $scheduleLoaded = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])->find($schedule->id);

        return response()->json([
            'message' => 'Schedule successfully created!',
            'schedule' => [
                'id' => $scheduleLoaded->id,
                'bus_id' => $scheduleLoaded->bus_id,
                'route_id' => $scheduleLoaded->route_id,
                'bus_operator' => $scheduleLoaded->bus->operator_name,
                'coach_number' => $scheduleLoaded->bus->coach_number,
                'coach_type' => $scheduleLoaded->bus->coach_type,
                'route_from' => $scheduleLoaded->route->departureStation->name,
                'route_to' => $scheduleLoaded->route->arrivalStation->name,
                'departure_time' => $scheduleLoaded->departure_time->toIso8601String(),
                'arrival_time' => $scheduleLoaded->arrival_time->toIso8601String(),
                'fare' => floatval($scheduleLoaded->fare),
            ],
        ], 201);
    }

    public function storePromotion(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code',
            'discount_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $promotion = Promotion::create($validated);

        return response()->json(['message' => 'Promotion code successfully created!', 'promotion' => $promotion], 201);
    }
}
