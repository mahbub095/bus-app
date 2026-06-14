<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportDataService
{
    public function __construct(
        protected ReportFilterService $filters
    ) {}

    public function sellingQuery(Request $request): Builder
    {
        [$start, $end] = $this->filters->resolveDateRange($request);

        $query = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->whereIn('status', ['PAID', 'SOLD', 'BOOKED'])
            ->whereBetween('created_at', [$start, $end]);

        return $this->applyCommonFilters($query, $request);
    }

    public function cancelQuery(Request $request): Builder
    {
        [$start, $end] = $this->filters->resolveDateRange($request);

        $query = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->where('status', 'CANCELLED')
            ->whereBetween('updated_at', [$start, $end]);

        return $this->applyCommonFilters($query, $request);
    }

    protected function applyCommonFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('coach_type') && $request->coach_type !== 'All') {
            $query->whereHas('schedule.bus', fn ($q) => $q->where('coach_type', $request->coach_type));
        }

        if ($request->filled('payment_method') && $request->payment_method !== 'All') {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('route_id') && $request->route_id !== 'All' && is_numeric($request->route_id)) {
            $query->whereHas('schedule', fn ($q) => $q->where('route_id', $request->route_id));
        }

        if ($request->filled('operator') && $request->operator !== 'All') {
            $query->whereHas('schedule.bus', fn ($q) => $q->where('operator_name', $request->operator));
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function formatSellingRow(Booking $booking): array
    {
        $schedule = $booking->schedule;
        $route = $schedule?->route;
        $bus = $schedule?->bus;
        $seats = array_filter(array_map('trim', explode(',', $booking->seat_numbers)));

        return [
            'pnr' => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'sold_date' => $booking->created_at->format('Y-m-d H:i'),
            'passenger_name' => $booking->passenger_name,
            'passenger_phone' => $booking->passenger_phone,
            'passenger_email' => $booking->passenger_email,
            'route' => $route
                ? $route->departureStation->name . ' → ' . $route->arrivalStation->name
                : 'N/A',
            'departure' => $schedule?->departure_time?->format('M d, Y h:i A') ?? 'N/A',
            'operator' => $bus?->operator_name ?? 'N/A',
            'coach_number' => $bus?->coach_number ?? 'N/A',
            'coach_type' => $bus?->coach_type ?? 'N/A',
            'seats' => $booking->seat_numbers,
            'seat_count' => count($seats),
            'fare' => floatval($booking->total_fare),
            'payment_method' => $booking->payment_method,
        ];
    }

    public function formatCancelRow(Booking $booking): array
    {
        $schedule = $booking->schedule;
        $route = $schedule?->route;
        $bus = $schedule?->bus;
        $seats = array_filter(array_map('trim', explode(',', $booking->seat_numbers)));

        return [
            'pnr' => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'cancel_date' => $booking->updated_at->format('Y-m-d H:i'),
            'booked_date' => $booking->created_at->format('Y-m-d H:i'),
            'passenger_name' => $booking->passenger_name,
            'passenger_phone' => $booking->passenger_phone,
            'passenger_email' => $booking->passenger_email,
            'route' => $route
                ? $route->departureStation->name . ' → ' . $route->arrivalStation->name
                : 'N/A',
            'departure' => $schedule?->departure_time?->format('M d, Y h:i A') ?? 'N/A',
            'operator' => $bus?->operator_name ?? 'N/A',
            'coach_type' => $bus?->coach_type ?? 'N/A',
            'seats' => $booking->seat_numbers,
            'seat_count' => count($seats),
            'fare' => floatval($booking->total_fare),
            'payment_method' => $booking->payment_method,
        ];
    }

    public function buildSummary(Collection $bookings, string $type): array
    {
        $totalTickets = $bookings->count();
        $totalSeats = 0;
        $totalFare = 0.00;
        $acCount = 0;
        $nonAcCount = 0;
        $acFare = 0.00;
        $nonAcFare = 0.00;

        foreach ($bookings as $booking) {
            // Parse seats efficiently
            $seats = array_filter(array_map('trim', explode(',', $booking->seat_numbers)));
            $seatCount = count($seats);
            $bookingFare = floatval($booking->total_fare);
            
            $totalSeats += $seatCount;
            $totalFare += $bookingFare;

            // Check coach type without accessing relationship if already loaded
            $coachType = $booking->schedule?->bus?->coach_type ?? '';
            if ($coachType === 'AC') {
                $acCount++;
                $acFare += $bookingFare;
            } else {
                $nonAcCount++;
                $nonAcFare += $bookingFare;
            }
        }

        return [
            'type' => $type,
            'total_tickets' => $totalTickets,
            'total_seats' => $totalSeats,
            'total_fare' => round($totalFare, 2),
            'ac_tickets' => $acCount,
            'non_ac_tickets' => $nonAcCount,
            'ac_fare' => round($acFare, 2),
            'non_ac_fare' => round($nonAcFare, 2),
        ];
    }

    public function sellingHeaders(): array
    {
        return [
            'PNR', 'Sold Date', 'Passenger', 'Phone', 'Email', 'Route',
            'Departure', 'Operator', 'Coach #', 'Coach Type', 'Seats',
            'Seat Count', 'Fare (BDT)', 'Payment',
        ];
    }

    public function cancelHeaders(): array
    {
        return [
            'PNR', 'Cancel Date', 'Booked Date', 'Passenger', 'Phone', 'Email',
            'Route', 'Departure', 'Operator', 'Coach Type', 'Seats',
            'Seat Count', 'Fare (BDT)', 'Payment',
        ];
    }

    public function sellingRowToArray(array $row): array
    {
        return [
            $row['pnr'], $row['sold_date'], $row['passenger_name'], $row['passenger_phone'],
            $row['passenger_email'], $row['route'], $row['departure'], $row['operator'],
            $row['coach_number'], $row['coach_type'], $row['seats'], $row['seat_count'],
            $row['fare'], $row['payment_method'],
        ];
    }

    public function cancelRowToArray(array $row): array
    {
        return [
            $row['pnr'], $row['cancel_date'], $row['booked_date'], $row['passenger_name'],
            $row['passenger_phone'], $row['passenger_email'], $row['route'], $row['departure'],
            $row['operator'], $row['coach_type'], $row['seats'], $row['seat_count'],
            $row['fare'], $row['payment_method'],
        ];
    }
}
