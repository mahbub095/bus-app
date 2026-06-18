<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Schedule;
use App\Services\SeatMapService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleController extends Controller
{
    public function __construct(protected SeatMapService $seatMapService)
    {
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
}
