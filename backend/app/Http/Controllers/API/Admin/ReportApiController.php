<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Booking;
use App\Models\Bus;
use App\Models\Route as RouteModel;
use App\Services\ReportDataService;
use App\Services\ReportFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Report previews (dashboard "Reports" pages).
 *
 * The Blade report pages call the same ReportDataService / ReportFilterService
 * pair; the file exports (Excel / PDF) stay on the web routes because they
 * return downloads rather than API data.
 */
class ReportApiController extends BaseAdminApiController
{
    /** Report keys accepted by show(). */
    public const TYPES = [
        'selling',
        'cancel',
        'booking',
        'revenue',
        'passenger',
        'seat-occupancy',
        'cancellation',
        'refund',
        'payment',
        'route-sales',
        'agent-sales',
    ];

    /** Rows returned for the preview grids. */
    protected const ROW_LIMIT = 500;

    protected const SCHEDULE_ROW_LIMIT = 200;

    public function __construct(
        protected ReportFilterService $filters,
        protected ReportDataService $data,
    ) {
    }

    /**
     * Report catalogue — what the dashboard report switcher lists.
     */
    public function index(): JsonResponse
    {
        $reports = array_map(fn (string $type) => [
            'type' => $type,
            'label' => ucwords(str_replace('-', ' ', $type)),
        ], self::TYPES);

        return response()->json(['data' => $reports]);
    }

    /**
     * Dropdown data for the report filter bar.
     */
    public function filters(): JsonResponse
    {
        $routes = RouteModel::with(['departureStation', 'arrivalStation'])
            ->orderBy('id')
            ->get()
            ->map(fn (RouteModel $route) => [
                'id' => $route->id,
                'from' => $route->departureStation?->name,
                'to' => $route->arrivalStation?->name,
                'label' => ($route->departureStation?->name ?? 'N/A').' → '.($route->arrivalStation?->name ?? 'N/A'),
            ]);

        $operators = Bus::query()
            ->select('operator_name')
            ->distinct()
            ->orderBy('operator_name')
            ->pluck('operator_name')
            ->filter()
            ->values();

        $paymentMethods = Booking::query()
            ->select('payment_method')
            ->distinct()
            ->pluck('payment_method')
            ->filter()
            ->values();

        return response()->json([
            'periods' => ['weekly', 'monthly', 'quarterly', 'yearly', 'custom'],
            'group_by' => ['date', 'operator', 'route', 'coach_type', 'payment_method', 'status'],
            'coach_types' => ['AC', 'Non AC'],
            'routes' => $routes,
            'operators' => $operators,
            'payment_methods' => $paymentMethods,
        ]);
    }

    /**
     * Preview rows + summary for one report type.
     */
    public function show(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::TYPES, true)) {
            return response()->json([
                'message' => 'Unknown report type.',
                'allowed' => self::TYPES,
            ], 404);
        }

        $this->filters->validateFilters($request);

        $preview = $this->preview($type, $request);

        return response()->json([
            'type' => $type,
            'period' => $request->input('period', 'monthly'),
            'filter_label' => $this->filters->filterSummary($request),
            'summary' => $preview['summary'],
            'rows' => $preview['rows'],
            'row_count' => count($preview['rows']),
            'total_count' => $preview['total_count'],
        ]);
    }

    /**
     * @return array{summary: array<string, mixed>, rows: array<int, mixed>, total_count: int}
     */
    protected function preview(string $type, Request $request): array
    {
        $data = $this->data;

        return match ($type) {
            'selling' => $this->bookingsPreview(
                $data->sellingQuery($request),
                fn (Booking $booking) => $data->formatSellingRow($booking),
                fn (Collection $bookings) => $data->buildSummary($bookings, 'selling')
            ),
            'cancel' => $this->bookingsPreview(
                $data->cancelQuery($request),
                fn (Booking $booking) => $data->formatCancelRow($booking),
                fn (Collection $bookings) => $data->buildSummary($bookings, 'cancel')
            ),
            'booking' => $this->bookingsPreview(
                $data->bookingQuery($request),
                fn (Booking $booking) => $data->formatBookingRow($booking),
                fn (Collection $bookings) => $data->buildSummary($bookings, 'booking')
            ),
            'revenue' => $this->bookingsPreview(
                $data->revenueQuery($request),
                fn (Booking $booking) => $data->formatRevenueRow($booking),
                fn (Collection $bookings) => $data->buildRevenueSummary($bookings)
            ),
            'passenger' => $this->bookingsPreview(
                $data->passengerQuery($request),
                fn (Booking $booking) => $data->formatPassengerRow($booking),
                fn (Collection $bookings) => $data->buildSummary($bookings, 'passenger')
            ),
            'seat-occupancy' => $this->seatOccupancyPreview($request),
            'cancellation' => $this->bookingsPreview(
                $data->cancellationQuery($request),
                fn (Booking $booking) => $data->formatCancelRow($booking),
                fn (Collection $bookings) => $data->buildCancellationSummary($bookings)
            ),
            'refund' => $this->bookingsPreview(
                $data->refundQuery($request),
                fn (Booking $booking) => $data->formatRefundRow($booking),
                fn (Collection $bookings) => $data->buildRefundSummary($bookings)
            ),
            'payment' => $this->bookingsPreview(
                $data->paymentQuery($request),
                fn (Booking $booking) => $data->formatPaymentRow($booking),
                fn (Collection $bookings) => $data->buildPaymentSummary($bookings)
            ),
            'route-sales' => $this->aggregatedPreview(
                $data->routeSalesQuery($request),
                fn (Collection $bookings) => $data->aggregateRouteSales($bookings),
                fn (Collection $rows) => $data->buildRouteSalesSummary($rows)
            ),
            'agent-sales' => $this->aggregatedPreview(
                $data->agentSalesQuery($request),
                fn (Collection $bookings) => $data->aggregateAgentSales($bookings),
                fn (Collection $rows) => $data->buildAgentSalesSummary($rows)
            ),
        };
    }

    /**
     * @param  callable(Booking): array<string, mixed>  $format
     * @param  callable(Collection): array<string, mixed>  $summary
     * @return array{summary: array<string, mixed>, rows: array<int, mixed>, total_count: int}
     */
    protected function bookingsPreview(Builder $query, callable $format, callable $summary): array
    {
        $bookings = (clone $query)->limit(self::ROW_LIMIT)->get();
        $rows = $bookings->map($format)->values();

        return [
            'summary' => $summary($bookings),
            'rows' => $rows->all(),
            'total_count' => $query->count(),
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, rows: array<int, mixed>, total_count: int}
     */
    protected function seatOccupancyPreview(Request $request): array
    {
        $query = $this->data->seatOccupancyQuery($request);

        $schedules = (clone $query)->limit(self::SCHEDULE_ROW_LIMIT)->get();
        $rows = $schedules->map(fn ($schedule) => $this->data->formatSeatOccupancyRow($schedule))->values();

        return [
            'summary' => $this->data->buildSeatOccupancySummary($rows),
            'rows' => $rows->all(),
            'total_count' => $query->count(),
        ];
    }

    /**
     * Route-wise and agent-wise reports aggregate the bookings first, so the
     * "total" is the number of aggregated rows, not bookings.
     *
     * @param  callable(Collection): Collection  $aggregate
     * @param  callable(Collection): array<string, mixed>  $summary
     * @return array{summary: array<string, mixed>, rows: array<int, mixed>, total_count: int}
     */
    protected function aggregatedPreview(Builder $query, callable $aggregate, callable $summary): array
    {
        $bookings = (clone $query)->limit(self::ROW_LIMIT * 4)->get();
        $rows = $aggregate($bookings)->values();

        return [
            'summary' => $summary($rows),
            'rows' => $rows->all(),
            'total_count' => $rows->count(),
        ];
    }
}
