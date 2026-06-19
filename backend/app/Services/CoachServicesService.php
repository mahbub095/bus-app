<?php

namespace App\Services;

use App\Models\Route;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CoachServicesService
{
    public function __construct(protected SeatMapService $seatMapService)
    {
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(int $fromId, int $toId, string $date, ?string $coachType = null, bool $includeSeatBookings = false): Collection
    {
        $route = Route::where('departure_station_id', $fromId)
            ->where('arrival_station_id', $toId)
            ->first();

        if (! $route) {
            return collect();
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
            $schedules = $schedules->filter(
                fn ($schedule) => strtolower($schedule->bus->coach_type) === strtolower($coachType)
            )->values();
        }

        return $schedules->map(function ($schedule) use ($includeSeatBookings) {
            $seatPayload = $this->seatMapService->formatSchedulePayload($schedule, $schedule->bookings);
            $availableCount = count(array_filter(
                $seatPayload['seat_map'],
                fn ($status) => $status === 'available'
            ));

            $payload = [
                'id' => $schedule->id,
                'departure_time' => $schedule->departure_time->toIso8601String(),
                'arrival_time' => $schedule->arrival_time->toIso8601String(),
                'fare' => floatval($schedule->fare),
                'bus' => [
                    'id' => $schedule->bus->id,
                    'operator_name' => $schedule->bus->operator_name,
                    'coach_number' => $schedule->bus->coach_number,
                    'coach_type' => $schedule->bus->coach_type,
                    'total_seats' => $schedule->bus->total_seats,
                    'seat_layout' => $schedule->bus->seat_layout,
                    'seat_layout_grid' => $schedule->bus->seat_layout_grid,
                ],
                'route' => [
                    'id' => $schedule->route->id,
                    'distance' => $schedule->route->distance,
                    'duration' => $schedule->route->duration,
                    'from' => $schedule->route->departureStation->name ?? '',
                    'to' => $schedule->route->arrivalStation->name ?? '',
                ],
                'booked_seats' => $seatPayload['booked_seats'],
                'seat_map' => $seatPayload['seat_map'],
                'boarding_points' => $seatPayload['boarding_points'],
                'dropping_points' => $seatPayload['dropping_points'],
                'seat_class' => $seatPayload['seat_class'],
                'pricing' => $seatPayload['pricing'],
                'available_seats_count' => $availableCount,
            ];

            if ($includeSeatBookings) {
                $payload['seat_bookings'] = $this->seatMapService->seatBookingDetails($schedule, $schedule->bookings);
            }

            return $payload;
        });
    }

    /**
     * @return array{success: bool, status: int, body: array<string, mixed>}
     */
    public function toggleBlockedSeat(int $scheduleId, string $seat): array
    {
        $schedule = Schedule::find($scheduleId);

        if (! $schedule) {
            return [
                'success' => false,
                'status' => 404,
                'body' => ['message' => 'Schedule not found.'],
            ];
        }

        $seat = strtoupper($seat);
        $bookings = $this->paidBookingsForSchedule($schedule);

        try {
            $result = $this->seatMapService->toggleBlockedSeat($schedule, $seat, $bookings);
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'status' => 422,
                'body' => ['message' => $e->getMessage()],
            ];
        }

        $schedule->refresh();
        $bookings = $this->paidBookingsForSchedule($schedule);
        $seatPayload = $this->seatMapService->formatSchedulePayload($schedule, $bookings);

        return [
            'success' => true,
            'status' => 200,
            'body' => [
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
            ],
        ];
    }

    protected function paidBookingsForSchedule(Schedule $schedule): Collection
    {
        return $schedule->bookings()
            ->where(function ($q) {
                $q->whereIn('status', ['PAID', 'SOLD', 'BOOKED'])
                    ->orWhere(function ($qp) {
                        $qp->where('status', 'PENDING')
                            ->where('created_at', '>=', now()->subMinutes(10));
                    });
            })
            ->select(SeatMapService::paidBookingColumns())
            ->get();
    }
}
