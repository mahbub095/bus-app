<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Schedule;
use App\Services\SeatMapService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SearchController extends Controller
{
    public function __construct(protected SeatMapService $seatMapService)
    {
    }

    public function search(Request $request)
    {
        $request->validate([
            'from' => 'required|exists:stations,id',
            'to' => 'required|exists:stations,id',
            'date' => 'required|date_format:Y-m-d',
            'coach_type' => 'nullable|string'
        ]);

        $fromId = $request->query('from');
        $toId = $request->query('to');
        $date = $request->query('date');
        $coachType = $request->query('coach_type'); // AC, Non AC, All

        // 1. Find the Route
        $route = Route::where('departure_station_id', $fromId)
            ->where('arrival_station_id', $toId)
            ->first();

        if (!$route) {
            return response()->json([], 200); // Return empty array if no route exists
        }

        // 2. Find Schedules for this Route on the specific Date
        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        $query = Schedule::where('route_id', $route->id)
            ->whereBetween('departure_time', [$startDate, $endDate])
            ->with([
                'bus',
                'route.departureStation',
                'route.arrivalStation',
                'bookings' => fn ($q) => SeatMapService::scopePaidBookingsForSeatMap($q),
            ]);

        $schedules = $query->get();

        // 3. Filter by Coach Type if specified
        if ($coachType && $coachType !== 'All') {
            $schedules = $schedules->filter(function($sched) use ($coachType) {
                return strtolower($sched->bus->coach_type) === strtolower($coachType);
            })->values();
        }

        // 4. Format the output with seat booking summaries
        $formattedSchedules = $schedules->map(function ($sched) {
            $seatPayload = $this->seatMapService->formatSchedulePayload($sched, $sched->bookings);
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
                'boarding_points' => $seatPayload['boarding_points'],
                'dropping_points' => $seatPayload['dropping_points'],
                'seat_class' => $seatPayload['seat_class'],
                'pricing' => $seatPayload['pricing'],
                'available_seats_count' => $availableCount,
            ];
        });

        return response()->json($formattedSchedules);
    }

}
