<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        
        return $query;
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

    // =========================================================================
    // BOOKING REPORT  — all active bookings (PAID, SOLD, BOOKED)
    // =========================================================================

    public function bookingQuery(Request $request): Builder
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

    public function formatBookingRow(Booking $booking): array
    {
        $schedule = $booking->schedule;
        $route    = $schedule?->route;
        $bus      = $schedule?->bus;
        $seats    = array_filter(array_map('trim', explode(',', $booking->seat_numbers)));

        return [
            'pnr'              => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'booking_date'     => $booking->created_at->format('Y-m-d H:i'),
            'passenger_name'   => $booking->passenger_name,
            'passenger_phone'  => $booking->passenger_phone,
            'passenger_email'  => $booking->passenger_email,
            'route'            => $route
                ? $route->departureStation->name . ' → ' . $route->arrivalStation->name
                : 'N/A',
            'departure'        => $schedule?->departure_time?->format('M d, Y h:i A') ?? 'N/A',
            'operator'         => $bus?->operator_name ?? 'N/A',
            'coach_number'     => $bus?->coach_number ?? 'N/A',
            'coach_type'       => $bus?->coach_type ?? 'N/A',
            'seats'            => $booking->seat_numbers,
            'seat_count'       => count($seats),
            'fare'             => floatval($booking->total_fare),
            'payment_method'   => $booking->payment_method,
            'status'           => $booking->status,
        ];
    }

    public function bookingHeaders(): array
    {
        return ['PNR', 'Booking Date', 'Passenger', 'Phone', 'Email', 'Route',
                'Departure', 'Operator', 'Coach #', 'Coach Type', 'Seats',
                'Seat Count', 'Fare (BDT)', 'Payment', 'Status'];
    }

    public function bookingRowToArray(array $row): array
    {
        return [$row['pnr'], $row['booking_date'], $row['passenger_name'], $row['passenger_phone'],
                $row['passenger_email'], $row['route'], $row['departure'], $row['operator'],
                $row['coach_number'], $row['coach_type'], $row['seats'], $row['seat_count'],
                $row['fare'], $row['payment_method'], $row['status']];
    }

    // =========================================================================
    // REVENUE REPORT  — total fare collected on paid bookings
    // =========================================================================

    public function revenueQuery(Request $request): Builder
    {
        [$start, $end] = $this->filters->resolveDateRange($request);

        $query = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->whereIn('status', ['PAID', 'SOLD'])
            ->whereBetween('created_at', [$start, $end]);

        return $this->applyCommonFilters($query, $request);
    }

    public function formatRevenueRow(Booking $booking): array
    {
        $schedule = $booking->schedule;
        $route    = $schedule?->route;
        $bus      = $schedule?->bus;
        $seats    = array_filter(array_map('trim', explode(',', $booking->seat_numbers)));

        return [
            'pnr'            => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'sold_date'      => $booking->created_at->format('Y-m-d H:i'),
            'route'          => $route
                ? $route->departureStation->name . ' → ' . $route->arrivalStation->name
                : 'N/A',
            'operator'       => $bus?->operator_name ?? 'N/A',
            'coach_type'     => $bus?->coach_type ?? 'N/A',
            'seat_count'     => count($seats),
            'fare'           => floatval($booking->total_fare),
            'payment_method' => $booking->payment_method,
        ];
    }

    public function buildRevenueSummary(Collection $bookings): array
    {
        $totalRevenue  = $bookings->sum(fn ($b) => floatval($b->total_fare));
        $acRevenue     = $bookings->filter(fn ($b) => $b->schedule?->bus?->coach_type === 'AC')
                                  ->sum(fn ($b) => floatval($b->total_fare));
        $nonAcRevenue  = $totalRevenue - $acRevenue;
        $totalTickets  = $bookings->count();
        $totalSeats    = $bookings->sum(fn ($b) => count(array_filter(array_map('trim', explode(',', $b->seat_numbers)))));

        // Revenue by payment method
        $byPayment = $bookings->groupBy('payment_method')
                              ->map(fn ($g) => round($g->sum(fn ($b) => floatval($b->total_fare)), 2));

        return [
            'total_revenue'    => round($totalRevenue, 2),
            'ac_revenue'       => round($acRevenue, 2),
            'non_ac_revenue'   => round($nonAcRevenue, 2),
            'total_tickets'    => $totalTickets,
            'total_seats'      => $totalSeats,
            'by_payment'       => $byPayment,
        ];
    }

    public function revenueHeaders(): array
    {
        return ['PNR', 'Sold Date', 'Route', 'Operator', 'Coach Type',
                'Seat Count', 'Fare (BDT)', 'Payment Method'];
    }

    public function revenueRowToArray(array $row): array
    {
        return [$row['pnr'], $row['sold_date'], $row['route'], $row['operator'],
                $row['coach_type'], $row['seat_count'], $row['fare'], $row['payment_method']];
    }

    // =========================================================================
    // PASSENGER REPORT  — unique passenger travel history
    // =========================================================================

    public function passengerQuery(Request $request): Builder
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

    public function formatPassengerRow(Booking $booking): array
    {
        $schedule = $booking->schedule;
        $route    = $schedule?->route;
        $bus      = $schedule?->bus;
        $seats    = array_filter(array_map('trim', explode(',', $booking->seat_numbers)));

        return [
            'pnr'             => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'travel_date'     => $schedule?->departure_time?->format('Y-m-d') ?? 'N/A',
            'passenger_name'  => $booking->passenger_name,
            'passenger_phone' => $booking->passenger_phone,
            'passenger_email' => $booking->passenger_email ?? '-',
            'gender'          => $booking->passenger_gender ?? '-',
            'route'           => $route
                ? $route->departureStation->name . ' → ' . $route->arrivalStation->name
                : 'N/A',
            'boarding_point'  => $booking->boarding_point ?? '-',
            'dropping_point'  => $booking->dropping_point ?? '-',
            'seats'           => $booking->seat_numbers,
            'seat_count'      => count($seats),
            'fare'            => floatval($booking->total_fare),
        ];
    }

    public function passengerHeaders(): array
    {
        return ['PNR', 'Travel Date', 'Passenger', 'Phone', 'Email', 'Gender',
                'Route', 'Boarding Point', 'Dropping Point', 'Seats', 'Seat Count', 'Fare (BDT)'];
    }

    public function passengerRowToArray(array $row): array
    {
        return [$row['pnr'], $row['travel_date'], $row['passenger_name'], $row['passenger_phone'],
                $row['passenger_email'], $row['gender'], $row['route'], $row['boarding_point'],
                $row['dropping_point'], $row['seats'], $row['seat_count'], $row['fare']];
    }

    // =========================================================================
    // SEAT OCCUPANCY REPORT  — per-schedule occupancy rates
    // =========================================================================

    public function seatOccupancyQuery(Request $request): Builder
    {
        [$start, $end] = $this->filters->resolveDateRange($request);

        // Query on schedules — join bookings count
        $query = \App\Models\Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])
            ->whereBetween('departure_time', [$start, $end]);

        if ($request->filled('coach_type') && $request->coach_type !== 'All') {
            $query->whereHas('bus', fn ($q) => $q->where('coach_type', $request->coach_type));
        }

        if ($request->filled('route_id') && $request->route_id !== 'All' && is_numeric($request->route_id)) {
            $query->where('route_id', $request->route_id);
        }

        if ($request->filled('operator') && $request->operator !== 'All') {
            $query->whereHas('bus', fn ($q) => $q->where('operator_name', $request->operator));
        }

        return $query;
    }

    public function formatSeatOccupancyRow(\App\Models\Schedule $schedule): array
    {
        $route      = $schedule->route;
        $bus        = $schedule->bus;
        $totalSeats = intval($bus?->total_seats ?? 0);

        $bookedSeats = $schedule->bookings()
            ->whereIn('status', ['PAID', 'SOLD', 'BOOKED'])
            ->get()
            ->sum(fn ($b) => count(array_filter(array_map('trim', explode(',', $b->seat_numbers)))));

        $occupancyPct = $totalSeats > 0 ? round(($bookedSeats / $totalSeats) * 100, 1) : 0;

        return [
            'schedule_id'    => $schedule->id,
            'departure'      => $schedule->departure_time?->format('Y-m-d H:i') ?? 'N/A',
            'route'          => $route
                ? $route->departureStation->name . ' → ' . $route->arrivalStation->name
                : 'N/A',
            'operator'       => $bus?->operator_name ?? 'N/A',
            'coach_number'   => $bus?->coach_number ?? 'N/A',
            'coach_type'     => $bus?->coach_type ?? 'N/A',
            'total_seats'    => $totalSeats,
            'booked_seats'   => $bookedSeats,
            'available_seats'=> max(0, $totalSeats - $bookedSeats),
            'occupancy_pct'  => $occupancyPct,
        ];
    }

    public function buildSeatOccupancySummary(Collection $rows): array
    {
        $totalSeats  = $rows->sum('total_seats');
        $bookedSeats = $rows->sum('booked_seats');
        $avgOccupancy = $rows->count() > 0 ? round($rows->avg('occupancy_pct'), 1) : 0;

        return [
            'total_schedules' => $rows->count(),
            'total_seats'     => $totalSeats,
            'booked_seats'    => $bookedSeats,
            'available_seats' => max(0, $totalSeats - $bookedSeats),
            'avg_occupancy'   => $avgOccupancy,
        ];
    }

    public function seatOccupancyHeaders(): array
    {
        return ['Schedule ID', 'Departure', 'Route', 'Operator', 'Coach #',
                'Coach Type', 'Total Seats', 'Booked Seats', 'Available Seats', 'Occupancy %'];
    }

    public function seatOccupancyRowToArray(array $row): array
    {
        return [$row['schedule_id'], $row['departure'], $row['route'], $row['operator'],
                $row['coach_number'], $row['coach_type'], $row['total_seats'],
                $row['booked_seats'], $row['available_seats'], $row['occupancy_pct']];
    }

    // =========================================================================
    // CANCELLATION REPORT  — already handled by cancelQuery; aliased here for
    //                        clarity; returns the same data with richer summary
    // =========================================================================

    public function cancellationQuery(Request $request): Builder
    {
        return $this->cancelQuery($request);
    }

    public function buildCancellationSummary(Collection $bookings): array
    {
        $base = $this->buildSummary($bookings, 'cancel');

        $byMethod = $bookings->groupBy('payment_method')
                             ->map(fn ($g) => $g->count());

        return array_merge($base, ['by_payment_count' => $byMethod]);
    }

    // =========================================================================
    // REFUND REPORT  — cancelled bookings that had been paid (eligible refunds)
    // =========================================================================

    public function refundQuery(Request $request): Builder
    {
        [$start, $end] = $this->filters->resolveDateRange($request);

        $query = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->where('status', 'CANCELLED')
            ->whereIn('payment_method', ['bKash', 'Nagad', 'Card'])   // online payments only
            ->whereBetween('updated_at', [$start, $end]);

        return $this->applyCommonFilters($query, $request);
    }

    public function formatRefundRow(Booking $booking): array
    {
        $schedule = $booking->schedule;
        $route    = $schedule?->route;
        $bus      = $schedule?->bus;
        $seats    = array_filter(array_map('trim', explode(',', $booking->seat_numbers)));

        return [
            'pnr'             => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'cancel_date'     => $booking->updated_at->format('Y-m-d H:i'),
            'booking_date'    => $booking->created_at->format('Y-m-d H:i'),
            'passenger_name'  => $booking->passenger_name,
            'passenger_phone' => $booking->passenger_phone,
            'route'           => $route
                ? $route->departureStation->name . ' → ' . $route->arrivalStation->name
                : 'N/A',
            'operator'        => $bus?->operator_name ?? 'N/A',
            'coach_type'      => $bus?->coach_type ?? 'N/A',
            'seats'           => $booking->seat_numbers,
            'seat_count'      => count($seats),
            'refund_amount'   => floatval($booking->total_fare),
            'payment_method'  => $booking->payment_method,
            'invoice_id'      => $booking->payment_invoice_id ?? '-',
        ];
    }

    public function buildRefundSummary(Collection $bookings): array
    {
        return [
            'total_refunds'      => $bookings->count(),
            'total_refund_amount'=> round($bookings->sum(fn ($b) => floatval($b->total_fare)), 2),
            'total_seats'        => $bookings->sum(fn ($b) => count(array_filter(array_map('trim', explode(',', $b->seat_numbers))))),
            'by_method'          => $bookings->groupBy('payment_method')
                                             ->map(fn ($g) => round($g->sum(fn ($b) => floatval($b->total_fare)), 2)),
        ];
    }

    public function refundHeaders(): array
    {
        return ['PNR', 'Cancel Date', 'Booking Date', 'Passenger', 'Phone', 'Route',
                'Operator', 'Coach Type', 'Seats', 'Seat Count', 'Refund Amount (BDT)',
                'Payment Method', 'Invoice ID'];
    }

    public function refundRowToArray(array $row): array
    {
        return [$row['pnr'], $row['cancel_date'], $row['booking_date'], $row['passenger_name'],
                $row['passenger_phone'], $row['route'], $row['operator'], $row['coach_type'],
                $row['seats'], $row['seat_count'], $row['refund_amount'],
                $row['payment_method'], $row['invoice_id']];
    }

    // =========================================================================
    // PAYMENT REPORT  — payment method breakdown
    // =========================================================================

    public function paymentQuery(Request $request): Builder
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

    public function formatPaymentRow(Booking $booking): array
    {
        $schedule = $booking->schedule;
        $route    = $schedule?->route;
        $bus      = $schedule?->bus;

        return [
            'pnr'            => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'payment_date'   => $booking->created_at->format('Y-m-d H:i'),
            'passenger_name' => $booking->passenger_name,
            'passenger_phone'=> $booking->passenger_phone,
            'route'          => $route
                ? $route->departureStation->name . ' → ' . $route->arrivalStation->name
                : 'N/A',
            'operator'       => $bus?->operator_name ?? 'N/A',
            'coach_type'     => $bus?->coach_type ?? 'N/A',
            'amount'         => floatval($booking->total_fare),
            'payment_method' => $booking->payment_method,
            'invoice_id'     => $booking->payment_invoice_id ?? '-',
            'status'         => $booking->status,
        ];
    }

    public function buildPaymentSummary(Collection $bookings): array
    {
        $total = $bookings->sum(fn ($b) => floatval($b->total_fare));

        $byMethod = $bookings->groupBy('payment_method')->map(fn ($g) => [
            'count'  => $g->count(),
            'amount' => round($g->sum(fn ($b) => floatval($b->total_fare)), 2),
        ]);

        return [
            'total_transactions' => $bookings->count(),
            'total_amount'       => round($total, 2),
            'by_method'          => $byMethod,
        ];
    }

    public function paymentHeaders(): array
    {
        return ['PNR', 'Payment Date', 'Passenger', 'Phone', 'Route', 'Operator',
                'Coach Type', 'Amount (BDT)', 'Payment Method', 'Invoice ID', 'Status'];
    }

    public function paymentRowToArray(array $row): array
    {
        return [$row['pnr'], $row['payment_date'], $row['passenger_name'], $row['passenger_phone'],
                $row['route'], $row['operator'], $row['coach_type'], $row['amount'],
                $row['payment_method'], $row['invoice_id'], $row['status']];
    }

    // =========================================================================
    // ROUTE-WISE SALES REPORT  — aggregated sales per route
    // =========================================================================

    public function routeSalesQuery(Request $request): Builder
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

    public function aggregateRouteSales(Collection $bookings): Collection
    {
        return $bookings->groupBy(function ($b) {
            $route = $b->schedule?->route;
            return $route
                ? $route->departureStation->name . ' → ' . $route->arrivalStation->name
                : 'N/A';
        })->map(function ($group, $routeName) {
            $totalSeats = $group->sum(fn ($b) => count(array_filter(array_map('trim', explode(',', $b->seat_numbers)))));
            $totalFare  = round($group->sum(fn ($b) => floatval($b->total_fare)), 2);
            $acCount    = $group->filter(fn ($b) => $b->schedule?->bus?->coach_type === 'AC')->count();

            return [
                'route'         => $routeName,
                'total_bookings'=> $group->count(),
                'total_seats'   => $totalSeats,
                'total_revenue' => $totalFare,
                'ac_bookings'   => $acCount,
                'non_ac_bookings' => $group->count() - $acCount,
            ];
        })->values()->sortByDesc('total_revenue')->values();
    }

    public function buildRouteSalesSummary(Collection $aggregated): array
    {
        return [
            'total_routes'   => $aggregated->count(),
            'total_bookings' => $aggregated->sum('total_bookings'),
            'total_seats'    => $aggregated->sum('total_seats'),
            'total_revenue'  => round($aggregated->sum('total_revenue'), 2),
        ];
    }

    public function routeSalesHeaders(): array
    {
        return ['Route', 'Total Bookings', 'Total Seats', 'Total Revenue (BDT)',
                'AC Bookings', 'Non AC Bookings'];
    }

    public function routeSalesRowToArray(array $row): array
    {
        return [$row['route'], $row['total_bookings'], $row['total_seats'],
                $row['total_revenue'], $row['ac_bookings'], $row['non_ac_bookings']];
    }

    // =========================================================================
    // AGENT / COUNTER SALES REPORT  — aggregated sales per operator
    // =========================================================================

    public function agentSalesQuery(Request $request): Builder
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

    public function aggregateAgentSales(Collection $bookings): Collection
    {
        return $bookings->groupBy(fn ($b) => $b->schedule?->bus?->operator_name ?? 'Unknown')
            ->map(function ($group, $operatorName) {
                $totalSeats  = $group->sum(fn ($b) => count(array_filter(array_map('trim', explode(',', $b->seat_numbers)))));
                $totalFare   = round($group->sum(fn ($b) => floatval($b->total_fare)), 2);
                $acCount     = $group->filter(fn ($b) => $b->schedule?->bus?->coach_type === 'AC')->count();

                $routes = $group->map(fn ($b) => $b->schedule?->route)
                    ->filter()
                    ->map(fn ($r) => $r->departureStation->name . ' → ' . $r->arrivalStation->name)
                    ->unique()->implode(', ');

                return [
                    'operator'       => $operatorName,
                    'routes_covered' => $routes ?: 'N/A',
                    'total_bookings' => $group->count(),
                    'total_seats'    => $totalSeats,
                    'total_revenue'  => $totalFare,
                    'ac_bookings'    => $acCount,
                    'non_ac_bookings'=> $group->count() - $acCount,
                ];
            })->values()->sortByDesc('total_revenue')->values();
    }

    public function buildAgentSalesSummary(Collection $aggregated): array
    {
        return [
            'total_agents'   => $aggregated->count(),
            'total_bookings' => $aggregated->sum('total_bookings'),
            'total_seats'    => $aggregated->sum('total_seats'),
            'total_revenue'  => round($aggregated->sum('total_revenue'), 2),
        ];
    }

    public function agentSalesHeaders(): array
    {
        return ['Operator / Agent', 'Routes Covered', 'Total Bookings',
                'Total Seats', 'Total Revenue (BDT)', 'AC Bookings', 'Non AC Bookings'];
    }

    public function agentSalesRowToArray(array $row): array
    {
        return [$row['operator'], $row['routes_covered'], $row['total_bookings'],
                $row['total_seats'], $row['total_revenue'], $row['ac_bookings'], $row['non_ac_bookings']];
    }
}
