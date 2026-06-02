<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Station;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Promotion;
use App\Models\SmsConfig;
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function __construct(protected SmsGatewayService $smsGatewayService)
    {
    }

    /**
     * Get dashboard metrics and JSON data for API.
     */
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
        ->get();

        $formattedBookings = $recentBookings->map(function($b) {
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
        $routes = Route::with(['departureStation', 'arrivalStation'])->get()->map(function($r) {
            return [
                'id' => $r->id,
                'departure_station_id' => $r->departure_station_id,
                'arrival_station_id' => $r->arrival_station_id,
                'from' => $r->departureStation->name,
                'to' => $r->arrivalStation->name,
                'distance' => $r->distance,
                'duration' => $r->duration
            ];
        });

        $schedules = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])->get()->map(function($s) {
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
                'fare' => floatval($s->fare)
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
            'promotions' => $promotions
        ]);
    }

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
        ->get();

        $cancelRequests = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->where('status', 'CANCEL_REQUESTED')
            ->orderBy('updated_at', 'desc')
            ->get();

        $stations = Station::orderBy('name', 'asc')->get();
        $buses = Bus::orderBy('operator_name', 'asc')->get();
        
        $routes = Route::with(['departureStation', 'arrivalStation'])->get()->map(function($r) {
            $r->from = $r->departureStation->name;
            $r->to = $r->arrivalStation->name;
            return $r;
        });

        $schedules = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])->get();
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

    /**
     * Update SMS gateway configuration for customer notifications.
     */
    public function updateSmsConfigWeb(Request $request)
    {
        $validated = $request->validate([
            'gateway_name' => 'required|string|max:100',
            'api_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:255',
            'sender_id' => 'nullable|string|max:50',
            'is_active' => 'nullable|in:0,1',
            'message_template' => 'nullable|string|max:500',
        ]);

        $config = SmsConfig::query()->latest('id')->first() ?? new SmsConfig();
        $config->fill([
            'gateway_name' => trim($validated['gateway_name']),
            'api_url' => $validated['api_url'] ?? null,
            'api_key' => $validated['api_key'] ?? null,
            'sender_id' => $validated['sender_id'] ?? null,
            'is_active' => ($validated['is_active'] ?? '0') === '1',
            'message_template' => $validated['message_template'] ?? null,
        ]);
        $config->save();

        return redirect()->back()->with('success', 'SMS gateway configuration saved successfully!');
    }

    /**
     * Live booking logs for admin dashboard (poll every 5s).
     */
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

    // Manual creation store methods for Blade Web Interface

    public function storeStationWeb(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:stations,name',
            'district' => 'nullable|string|max:100'
        ]);

        Station::create([
            'name' => strtoupper(trim($request->input('name'))),
            'district' => trim($request->input('district'))
        ]);

        return redirect()->back()->with('success', 'Station terminal created successfully!');
    }

    public function updateStationWeb(Request $request, $id)
    {
        $station = Station::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100|unique:stations,name,' . $id,
            'district' => 'nullable|string|max:100'
        ]);

        $station->update([
            'name' => strtoupper(trim($request->input('name'))),
            'district' => trim($request->input('district'))
        ]);

        return redirect()->back()->with('success', 'Station terminal updated successfully!');
    }

    public function destroyStationWeb($id)
    {
        $station = Station::findOrFail($id);

        if ($station->departureRoutes()->exists() || $station->arrivalRoutes()->exists()) {
            return redirect()->back()->withErrors(['message' => 'Cannot delete station — it is linked to existing routes.']);
        }

        $station->delete();

        return redirect()->back()->with('success', 'Station terminal deleted successfully!');
    }

    public function storeBusWeb(Request $request)
    {
        $request->validate([
            'operator_name' => 'required|string|max:100',
            'coach_number' => 'required|string|max:50|unique:buses,coach_number',
            'coach_type' => 'required|in:AC,Non AC',
            'total_seats' => 'required|integer|min:10|max:100'
        ]);

        Bus::create($request->only('operator_name', 'coach_number', 'coach_type', 'total_seats'));

        return redirect()->back()->with('success', 'Bus fleet registered successfully!');
    }

    public function updateBusWeb(Request $request, $id)
    {
        $bus = Bus::findOrFail($id);

        $request->validate([
            'operator_name' => 'required|string|max:100',
            'coach_number' => 'required|string|max:50|unique:buses,coach_number,' . $id,
            'coach_type' => 'required|in:AC,Non AC',
            'total_seats' => 'required|integer|min:10|max:100'
        ]);

        $bus->update($request->only('operator_name', 'coach_number', 'coach_type', 'total_seats'));

        return redirect()->back()->with('success', 'Coach updated successfully!');
    }

    public function destroyBusWeb($id)
    {
        Bus::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Coach deleted successfully!');
    }

    public function storeRouteWeb(Request $request)
    {
        $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'distance' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50'
        ]);

        $exists = Route::where('departure_station_id', $request->input('departure_station_id'))
            ->where('arrival_station_id', $request->input('arrival_station_id'))
            ->first();

        if ($exists) {
            return redirect()->back()->withInput()->withErrors(['departure_station_id' => 'A route between these stations already exists.']);
        }

        Route::create($request->only('departure_station_id', 'arrival_station_id', 'distance', 'duration'));

        return redirect()->back()->with('success', 'Transport line route connection configured successfully!');
    }

    public function updateRouteWeb(Request $request, $id)
    {
        $route = Route::findOrFail($id);

        $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'distance' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50'
        ]);

        $exists = Route::where('departure_station_id', $request->input('departure_station_id'))
            ->where('arrival_station_id', $request->input('arrival_station_id'))
            ->where('id', '!=', $id)
            ->first();

        if ($exists) {
            return redirect()->back()->withInput()->withErrors(['departure_station_id' => 'A route between these stations already exists.']);
        }

        $route->update($request->only('departure_station_id', 'arrival_station_id', 'distance', 'duration'));

        return redirect()->back()->with('success', 'Route updated successfully!');
    }

    public function destroyRouteWeb($id)
    {
        Route::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Route deleted successfully!');
    }

    public function storeScheduleWeb(Request $request)
    {
        $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date|after_or_equal:today',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0'
        ]);

        Schedule::create($request->only('bus_id', 'route_id', 'departure_time', 'arrival_time', 'fare'));

        return redirect()->back()->with('success', 'Schedule run registered successfully!');
    }

    public function updateScheduleWeb(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0'
        ]);

        $schedule->update($request->only('bus_id', 'route_id', 'departure_time', 'arrival_time', 'fare'));

        return redirect()->back()->with('success', 'Schedule updated successfully!');
    }

    public function destroyScheduleWeb($id)
    {
        Schedule::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Schedule deleted successfully!');
    }

    public function storePromotionWeb(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code',
            'discount_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255'
        ]);

        Promotion::create([
            'code' => strtoupper(trim($request->input('code'))),
            'discount_amount' => $request->input('discount_amount'),
            'description' => trim($request->input('description'))
        ]);

        return redirect()->back()->with('success', 'Promotion code coupon generated successfully!');
    }

    public function updatePromotionWeb(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);

        $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code,' . $id,
            'discount_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255'
        ]);

        $promotion->update([
            'code' => strtoupper(trim($request->input('code'))),
            'discount_amount' => $request->input('discount_amount'),
            'description' => trim($request->input('description'))
        ]);

        return redirect()->back()->with('success', 'Coupon updated successfully!');
    }

    public function destroyPromotionWeb($id)
    {
        Promotion::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Coupon deleted successfully!');
    }

    public function storeBookingWeb(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'passenger_name' => 'required|string|max:100',
            'passenger_phone' => 'required|string|max:20',
            'passenger_email' => 'required|email|max:100',
            'seat_numbers' => 'required|string|max:255',
            'payment_method' => 'required|string|max:50',
            'total_fare' => 'required|numeric|min:0',
            'status' => 'required|in:PAID,CANCEL_REQUESTED,CANCELLED'
        ]);

        $booking = Booking::create([
            'schedule_id' => $request->input('schedule_id'),
            'passenger_name' => $request->input('passenger_name'),
            'passenger_phone' => $request->input('passenger_phone'),
            'passenger_email' => $request->input('passenger_email'),
            'seat_numbers' => $request->input('seat_numbers'),
            'total_fare' => $request->input('total_fare'),
            'payment_method' => $request->input('payment_method'),
            'status' => $request->input('status'),
        ]);

        if ($request->input('status') === 'PAID') {
            $this->smsGatewayService->sendBookingVerification($booking);
        }

        return redirect()->back()->with('success', 'Booking created successfully!');
    }

    public function updateBookingWeb(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $request->validate([
            'passenger_name' => 'required|string|max:100',
            'passenger_phone' => 'required|string|max:20',
            'passenger_email' => 'required|email|max:100',
            'seat_numbers' => 'required|string|max:255',
            'total_fare' => 'required|numeric|min:0',
            'status' => 'required|in:PAID,CANCEL_REQUESTED,CANCELLED'
        ]);

        $booking->update($request->only(
            'passenger_name',
            'passenger_phone',
            'passenger_email',
            'seat_numbers',
            'total_fare',
            'status'
        ));

        return redirect()->back()->with('success', 'Booking updated successfully!');
    }

    public function destroyBookingWeb($id)
    {
        Booking::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Booking deleted successfully!');
    }

    /**
     * Cancel booking and release seats from web view.
     */
    public function cancelBookingWeb($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return redirect()->back()->withErrors(['message' => 'Booking not found.']);
        }

        if ($booking->status === 'CANCELLED') {
            return redirect()->back()->withErrors(['message' => 'Ticket is already cancelled.']);
        }

        $booking->update(['status' => 'CANCELLED']);

        return redirect()->back()->with('success', 'Reservation successfully cancelled and seat released!');
    }

    /**
     * Approve a customer cancel request from admin dashboard.
     */
    public function approveCancelRequestWeb($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return redirect()->back()->withErrors(['message' => 'Booking not found.']);
        }

        if ($booking->status !== 'CANCEL_REQUESTED') {
            return redirect()->back()->withErrors(['message' => 'This booking has no pending cancellation request.']);
        }

        $booking->update(['status' => 'CANCELLED']);

        return redirect()->back()->with('success', 'Cancellation request approved successfully. Booking is now cancelled.');
    }

    /**
     * Search coach services with seat occupancy for admin dashboard (JSON).
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

        if (!$route) {
            return response()->json([]);
        }

        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        $schedules = Schedule::where('route_id', $route->id)
            ->whereBetween('departure_time', [$startDate, $endDate])
            ->with(['bus', 'route', 'bookings' => function ($q) {
                $q->where('status', 'PAID');
            }])
            ->get();

        if ($coachType && $coachType !== 'All') {
            $schedules = $schedules->filter(function ($sched) use ($coachType) {
                return strtolower($sched->bus->coach_type) === strtolower($coachType);
            })->values();
        }

        $formattedSchedules = $schedules->map(function ($sched) {
            $bookedSeats = [];
            $seatBookings = [];

            foreach ($sched->bookings as $booking) {
                $seats = explode(',', $booking->seat_numbers);
                foreach ($seats as $seat) {
                    $seatTrimmed = trim($seat);
                    if ($seatTrimmed === '') {
                        continue;
                    }

                    $bookedSeats[] = $seatTrimmed;
                    $seatBookings[$seatTrimmed] = [
                        'booking_id' => $booking->id,
                        'pnr' => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
                        'passenger_name' => $booking->passenger_name,
                        'passenger_phone' => $booking->passenger_phone,
                        'passenger_email' => $booking->passenger_email,
                        'seat_numbers' => $booking->seat_numbers,
                        'total_fare' => floatval($booking->total_fare),
                        'payment_method' => $booking->payment_method,
                        'status' => $booking->status,
                    ];
                }
            }

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
                ],
                'booked_seats' => $bookedSeats,
                'seat_bookings' => $seatBookings,
                'available_seats_count' => $sched->bus->total_seats - count($bookedSeats),
            ];
        });

        return response()->json($formattedSchedules);
    }

    /**
     * Cancel a booking via AJAX for realtime admin seat map updates.
     */
    public function cancelBookingApi($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
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

    // Programmatical database operations via Artisan triggers

    public function systemMigrate()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            return redirect()->back()->with('console_output', $output)->with('success', 'Database migrations executed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['system' => 'Failed to migrate: ' . $e->getMessage()]);
        }
    }

    public function systemSeed()
    {
        try {
            Artisan::call('db:seed', ['--force' => true]);
            $output = Artisan::output();
            return redirect()->back()->with('console_output', $output)->with('success', 'Seeder execution completed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['system' => 'Failed to seed: ' . $e->getMessage()]);
        }
    }

    public function systemMigrateFreshSeed()
    {
        try {
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
            $output = Artisan::output();
            return redirect()->back()->with('console_output', $output)->with('success', 'Fresh migration and seeding completed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['system' => 'Failed to fresh migrate & seed: ' . $e->getMessage()]);
        }
    }

    // JSON API equivalents for reference/safety

    public function storeStation(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:stations,name',
            'district' => 'nullable|string|max:100'
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
            'total_seats' => 'required|integer|min:10|max:100'
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
            'duration' => 'nullable|string|max:50'
        ]);
        $exists = Route::where('departure_station_id', $validated['departure_station_id'])->where('arrival_station_id', $validated['arrival_station_id'])->first();
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
                'duration' => $routeLoaded->duration
            ]
        ], 201);
    }

    public function storeSchedule(Request $request)
    {
        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date|after_or_equal:today',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0'
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
                'fare' => floatval($scheduleLoaded->fare)
            ]
        ], 201);
    }

    public function storePromotion(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code',
            'discount_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255'
        ]);
        $validated['code'] = strtoupper($validated['code']);
        $promotion = Promotion::create($validated);
        return response()->json(['message' => 'Promotion code successfully created!', 'promotion' => $promotion], 201);
    }
}
