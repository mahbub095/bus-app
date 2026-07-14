<?php

namespace App\Http\Controllers\Admin;

use App\Services\ExcelExportService;
use App\Services\ReportDataService;
use App\Services\ReportFilterService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends BaseAdminController
{
    public function __construct(
        protected ReportFilterService $filters,
        protected ReportDataService $data,
        protected ExcelExportService $excel
    ) {
    }

    /**
     * Render a full-page detail view for any report type.
     * URL: /admin/reports/{type}/view
     */
    public function detailPage(string $type)
    {
        $allowedTypes = [
            'selling', 'booking', 'revenue', 'passenger', 'seat-occupancy',
            'cancellation', 'cancel', 'refund', 'payment', 'route-sales', 'agent-sales',
        ];

        if (! in_array($type, $allowedTypes)) {
            abort(404);
        }

        // Load only what the filter dropdowns need — lightweight queries
        $routes = \App\Models\Route::with(['departureStation', 'arrivalStation'])
            ->orderBy('id')
            ->get()
            ->each(function ($route) {
                $route->from = $route->departureStation->name ?? '';
                $route->to   = $route->arrivalStation->name ?? '';
            });

        $buses = \App\Models\Bus::orderBy('operator_name')->get();

        return view("admin.reports.pages.{$type}", compact('routes', 'buses', 'type'));
    }

    public function sellingPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->sellingQuery($request)->limit(500)->get();
        $rows = $bookings->map(fn ($b) => $this->data->formatSellingRow($b))->values();

        return response()->json([
            'summary' => $this->data->buildSummary($bookings, 'selling'),
            'filter_label' => $this->filters->filterSummary($request),
            'rows' => $rows,
            'total_count' => $this->data->sellingQuery($request)->count(),
        ]);
    }

    public function cancelPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->cancelQuery($request)->limit(500)->get();
        $rows = $bookings->map(fn ($b) => $this->data->formatCancelRow($b))->values();

        return response()->json([
            'summary' => $this->data->buildSummary($bookings, 'cancel'),
            'filter_label' => $this->filters->filterSummary($request),
            'rows' => $rows,
            'total_count' => $this->data->cancelQuery($request)->count(),
        ]);
    }

    public function sellingExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->sellingQuery($request)->get();
        $rows = $bookings->map(function ($b) {
            return $this->data->sellingRowToArray($this->data->formatSellingRow($b));
        })->all();

        $summary = $this->data->buildSummary($bookings, 'selling');
        $rows[] = [];
        $rows[] = ['Summary', 'Total Tickets', $summary['total_tickets'], 'Total Seats', $summary['total_seats'], 'Total Revenue (BDT)', $summary['total_fare']];
        $rows[] = ['', 'AC Tickets', $summary['ac_tickets'], 'AC Revenue', $summary['ac_fare'], 'Non AC Tickets', $summary['non_ac_tickets'], 'Non AC Revenue', $summary['non_ac_fare']];

        return $this->excel->download(
            $this->data->sellingHeaders(),
            $rows,
            'ticket-selling-report-'.now()->format('Y-m-d'),
            'Ticket Selling Report'
        );
    }

    public function cancelExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->cancelQuery($request)->get();
        $rows = $bookings->map(function ($b) {
            return $this->data->cancelRowToArray($this->data->formatCancelRow($b));
        })->all();

        $summary = $this->data->buildSummary($bookings, 'cancel');
        $rows[] = [];
        $rows[] = ['Summary', 'Total Cancelled', $summary['total_tickets'], 'Seats Released', $summary['total_seats'], 'Fare Amount (BDT)', $summary['total_fare']];
        $rows[] = ['', 'AC Cancelled', $summary['ac_tickets'], 'AC Fare', $summary['ac_fare'], 'Non AC Cancelled', $summary['non_ac_tickets'], 'Non AC Fare', $summary['non_ac_fare']];

        return $this->excel->download(
            $this->data->cancelHeaders(),
            $rows,
            'ticket-cancel-report-'.now()->format('Y-m-d'),
            'Ticket Cancel Report'
        );
    }

    public function sellingExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->sellingQuery($request)->get();
        $rows = $bookings->map(fn ($b) => $this->data->formatSellingRow($b));

        $pdf = Pdf::loadView('admin.reports.pdf.selling', [
            'rows' => $rows,
            'summary' => $this->data->buildSummary($bookings, 'selling'),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('ticket-selling-report-'.now()->format('Y-m-d').'.pdf');
    }

    public function cancelExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->cancelQuery($request)->get();
        $rows = $bookings->map(fn ($b) => $this->data->formatCancelRow($b));

        $pdf = Pdf::loadView('admin.reports.pdf.cancel', [
            'rows' => $rows,
            'summary' => $this->data->buildSummary($bookings, 'cancel'),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('ticket-cancel-report-'.now()->format('Y-m-d').'.pdf');
    }

    // =========================================================================
    // BOOKING REPORT
    // =========================================================================

    public function bookingPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->bookingQuery($request)->limit(500)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatBookingRow($b))->values();

        return response()->json([
            'summary'      => $this->data->buildSummary($bookings, 'booking'),
            'filter_label' => $this->filters->filterSummary($request),
            'rows'         => $rows,
            'total_count'  => $this->data->bookingQuery($request)->count(),
        ]);
    }

    public function bookingExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->bookingQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->bookingRowToArray($this->data->formatBookingRow($b)))->all();
        $summary  = $this->data->buildSummary($bookings, 'booking');
        $rows[]   = [];
        $rows[]   = ['Summary', 'Total Bookings', $summary['total_tickets'], 'Total Seats', $summary['total_seats'], 'Total Fare (BDT)', $summary['total_fare']];

        return $this->excel->download(
            $this->data->bookingHeaders(),
            $rows,
            'booking-report-'.now()->format('Y-m-d'),
            'Booking Report'
        );
    }

    public function bookingExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->bookingQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatBookingRow($b));
        $pdf      = Pdf::loadView('admin.reports.pdf.booking', [
            'rows'        => $rows,
            'summary'     => $this->data->buildSummary($bookings, 'booking'),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('booking-report-'.now()->format('Y-m-d').'.pdf');
    }

    // =========================================================================
    // REVENUE REPORT
    // =========================================================================

    public function revenuePreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->revenueQuery($request)->limit(500)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatRevenueRow($b))->values();

        return response()->json([
            'summary'      => $this->data->buildRevenueSummary($bookings),
            'filter_label' => $this->filters->filterSummary($request),
            'rows'         => $rows,
            'total_count'  => $this->data->revenueQuery($request)->count(),
        ]);
    }

    public function revenueExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->revenueQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->revenueRowToArray($this->data->formatRevenueRow($b)))->all();
        $summary  = $this->data->buildRevenueSummary($bookings);
        $rows[]   = [];
        $rows[]   = ['Summary', 'Total Revenue (BDT)', $summary['total_revenue'], 'AC Revenue', $summary['ac_revenue'], 'Non AC Revenue', $summary['non_ac_revenue']];
        $rows[]   = ['', 'Total Tickets', $summary['total_tickets'], 'Total Seats', $summary['total_seats']];

        return $this->excel->download(
            $this->data->revenueHeaders(),
            $rows,
            'revenue-report-'.now()->format('Y-m-d'),
            'Revenue Report'
        );
    }

    public function revenueExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->revenueQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatRevenueRow($b));
        $pdf      = Pdf::loadView('admin.reports.pdf.revenue', [
            'rows'        => $rows,
            'summary'     => $this->data->buildRevenueSummary($bookings),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('revenue-report-'.now()->format('Y-m-d').'.pdf');
    }

    // =========================================================================
    // PASSENGER REPORT
    // =========================================================================

    public function passengerPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->passengerQuery($request)->limit(500)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatPassengerRow($b))->values();

        return response()->json([
            'summary'      => $this->data->buildSummary($bookings, 'passenger'),
            'filter_label' => $this->filters->filterSummary($request),
            'rows'         => $rows,
            'total_count'  => $this->data->passengerQuery($request)->count(),
        ]);
    }

    public function passengerExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->passengerQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->passengerRowToArray($this->data->formatPassengerRow($b)))->all();
        $summary  = $this->data->buildSummary($bookings, 'passenger');
        $rows[]   = [];
        $rows[]   = ['Summary', 'Total Passengers', $summary['total_tickets'], 'Total Seats', $summary['total_seats']];

        return $this->excel->download(
            $this->data->passengerHeaders(),
            $rows,
            'passenger-report-'.now()->format('Y-m-d'),
            'Passenger Report'
        );
    }

    public function passengerExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->passengerQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatPassengerRow($b));
        $pdf      = Pdf::loadView('admin.reports.pdf.passenger', [
            'rows'        => $rows,
            'summary'     => $this->data->buildSummary($bookings, 'passenger'),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('passenger-report-'.now()->format('Y-m-d').'.pdf');
    }

    // =========================================================================
    // SEAT OCCUPANCY REPORT
    // =========================================================================

    public function seatOccupancyPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $schedules = $this->data->seatOccupancyQuery($request)->limit(200)->get();
        $rows      = $schedules->map(fn ($s) => $this->data->formatSeatOccupancyRow($s))->values();
        $summary   = $this->data->buildSeatOccupancySummary($rows);

        return response()->json([
            'summary'      => $summary,
            'filter_label' => $this->filters->filterSummary($request),
            'rows'         => $rows,
            'total_count'  => $this->data->seatOccupancyQuery($request)->count(),
        ]);
    }

    public function seatOccupancyExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $schedules = $this->data->seatOccupancyQuery($request)->get();
        $formatted = $schedules->map(fn ($s) => $this->data->formatSeatOccupancyRow($s));
        $rows      = $formatted->map(fn ($r) => $this->data->seatOccupancyRowToArray($r))->all();
        $summary   = $this->data->buildSeatOccupancySummary($formatted);
        $rows[]    = [];
        $rows[]    = ['Summary', 'Total Schedules', $summary['total_schedules'], 'Total Seats', $summary['total_seats'],
                      'Booked Seats', $summary['booked_seats'], 'Available', $summary['available_seats'],
                      'Avg Occupancy %', $summary['avg_occupancy']];

        return $this->excel->download(
            $this->data->seatOccupancyHeaders(),
            $rows,
            'seat-occupancy-report-'.now()->format('Y-m-d'),
            'Seat Occupancy Report'
        );
    }

    public function seatOccupancyExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $schedules = $this->data->seatOccupancyQuery($request)->get();
        $rows      = $schedules->map(fn ($s) => $this->data->formatSeatOccupancyRow($s));
        $pdf       = Pdf::loadView('admin.reports.pdf.seat-occupancy', [
            'rows'        => $rows,
            'summary'     => $this->data->buildSeatOccupancySummary($rows),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('seat-occupancy-report-'.now()->format('Y-m-d').'.pdf');
    }

    // =========================================================================
    // CANCELLATION REPORT
    // =========================================================================

    public function cancellationPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->cancellationQuery($request)->limit(500)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatCancelRow($b))->values();

        return response()->json([
            'summary'      => $this->data->buildCancellationSummary($bookings),
            'filter_label' => $this->filters->filterSummary($request),
            'rows'         => $rows,
            'total_count'  => $this->data->cancellationQuery($request)->count(),
        ]);
    }

    public function cancellationExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->cancellationQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->cancelRowToArray($this->data->formatCancelRow($b)))->all();
        $summary  = $this->data->buildCancellationSummary($bookings);
        $rows[]   = [];
        $rows[]   = ['Summary', 'Total Cancelled', $summary['total_tickets'], 'Seats Released', $summary['total_seats'], 'Fare Amount (BDT)', $summary['total_fare']];

        return $this->excel->download(
            $this->data->cancelHeaders(),
            $rows,
            'cancellation-report-'.now()->format('Y-m-d'),
            'Cancellation Report'
        );
    }

    public function cancellationExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->cancellationQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatCancelRow($b));
        $pdf      = Pdf::loadView('admin.reports.pdf.cancellation', [
            'rows'        => $rows,
            'summary'     => $this->data->buildCancellationSummary($bookings),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('cancellation-report-'.now()->format('Y-m-d').'.pdf');
    }

    // =========================================================================
    // REFUND REPORT
    // =========================================================================

    public function refundPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->refundQuery($request)->limit(500)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatRefundRow($b))->values();

        return response()->json([
            'summary'      => $this->data->buildRefundSummary($bookings),
            'filter_label' => $this->filters->filterSummary($request),
            'rows'         => $rows,
            'total_count'  => $this->data->refundQuery($request)->count(),
        ]);
    }

    public function refundExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->refundQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->refundRowToArray($this->data->formatRefundRow($b)))->all();
        $summary  = $this->data->buildRefundSummary($bookings);
        $rows[]   = [];
        $rows[]   = ['Summary', 'Total Refunds', $summary['total_refunds'], 'Total Refund Amount (BDT)', $summary['total_refund_amount'], 'Total Seats', $summary['total_seats']];

        return $this->excel->download(
            $this->data->refundHeaders(),
            $rows,
            'refund-report-'.now()->format('Y-m-d'),
            'Refund Report'
        );
    }

    public function refundExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->refundQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatRefundRow($b));
        $pdf      = Pdf::loadView('admin.reports.pdf.refund', [
            'rows'        => $rows,
            'summary'     => $this->data->buildRefundSummary($bookings),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('refund-report-'.now()->format('Y-m-d').'.pdf');
    }

    // =========================================================================
    // PAYMENT REPORT
    // =========================================================================

    public function paymentPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->paymentQuery($request)->limit(500)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatPaymentRow($b))->values();

        return response()->json([
            'summary'      => $this->data->buildPaymentSummary($bookings),
            'filter_label' => $this->filters->filterSummary($request),
            'rows'         => $rows,
            'total_count'  => $this->data->paymentQuery($request)->count(),
        ]);
    }

    public function paymentExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->paymentQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->paymentRowToArray($this->data->formatPaymentRow($b)))->all();
        $summary  = $this->data->buildPaymentSummary($bookings);
        $rows[]   = [];
        $rows[]   = ['Summary', 'Total Transactions', $summary['total_transactions'], 'Total Amount (BDT)', $summary['total_amount']];

        return $this->excel->download(
            $this->data->paymentHeaders(),
            $rows,
            'payment-report-'.now()->format('Y-m-d'),
            'Payment Report'
        );
    }

    public function paymentExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings = $this->data->paymentQuery($request)->get();
        $rows     = $bookings->map(fn ($b) => $this->data->formatPaymentRow($b));
        $pdf      = Pdf::loadView('admin.reports.pdf.payment', [
            'rows'        => $rows,
            'summary'     => $this->data->buildPaymentSummary($bookings),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('payment-report-'.now()->format('Y-m-d').'.pdf');
    }

    // =========================================================================
    // ROUTE-WISE SALES REPORT
    // =========================================================================

    public function routeSalesPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings    = $this->data->routeSalesQuery($request)->limit(2000)->get();
        $aggregated  = $this->data->aggregateRouteSales($bookings);

        return response()->json([
            'summary'      => $this->data->buildRouteSalesSummary($aggregated),
            'filter_label' => $this->filters->filterSummary($request),
            'rows'         => $aggregated->values(),
            'total_count'  => $aggregated->count(),
        ]);
    }

    public function routeSalesExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings   = $this->data->routeSalesQuery($request)->get();
        $aggregated = $this->data->aggregateRouteSales($bookings);
        $rows       = $aggregated->map(fn ($r) => $this->data->routeSalesRowToArray($r))->all();
        $summary    = $this->data->buildRouteSalesSummary($aggregated);
        $rows[]     = [];
        $rows[]     = ['Summary', 'Total Routes', $summary['total_routes'], 'Total Bookings', $summary['total_bookings'],
                       'Total Seats', $summary['total_seats'], 'Total Revenue (BDT)', $summary['total_revenue']];

        return $this->excel->download(
            $this->data->routeSalesHeaders(),
            $rows,
            'route-sales-report-'.now()->format('Y-m-d'),
            'Route-wise Sales Report'
        );
    }

    public function routeSalesExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings   = $this->data->routeSalesQuery($request)->get();
        $aggregated = $this->data->aggregateRouteSales($bookings);
        $pdf        = Pdf::loadView('admin.reports.pdf.route-sales', [
            'rows'        => $aggregated,
            'summary'     => $this->data->buildRouteSalesSummary($aggregated),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('route-sales-report-'.now()->format('Y-m-d').'.pdf');
    }

    // =========================================================================
    // AGENT / COUNTER SALES REPORT
    // =========================================================================

    public function agentSalesPreview(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings   = $this->data->agentSalesQuery($request)->limit(2000)->get();
        $aggregated = $this->data->aggregateAgentSales($bookings);

        return response()->json([
            'summary'      => $this->data->buildAgentSalesSummary($aggregated),
            'filter_label' => $this->filters->filterSummary($request),
            'rows'         => $aggregated->values(),
            'total_count'  => $aggregated->count(),
        ]);
    }

    public function agentSalesExportExcel(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings   = $this->data->agentSalesQuery($request)->get();
        $aggregated = $this->data->aggregateAgentSales($bookings);
        $rows       = $aggregated->map(fn ($r) => $this->data->agentSalesRowToArray($r))->all();
        $summary    = $this->data->buildAgentSalesSummary($aggregated);
        $rows[]     = [];
        $rows[]     = ['Summary', 'Total Agents', $summary['total_agents'], 'Total Bookings', $summary['total_bookings'],
                       'Total Seats', $summary['total_seats'], 'Total Revenue (BDT)', $summary['total_revenue']];

        return $this->excel->download(
            $this->data->agentSalesHeaders(),
            $rows,
            'agent-sales-report-'.now()->format('Y-m-d'),
            'Agent/Counter Sales Report'
        );
    }

    public function agentSalesExportPdf(Request $request)
    {
        $this->filters->validateFilters($request);

        $bookings   = $this->data->agentSalesQuery($request)->get();
        $aggregated = $this->data->aggregateAgentSales($bookings);
        $pdf        = Pdf::loadView('admin.reports.pdf.agent-sales', [
            'rows'        => $aggregated,
            'summary'     => $this->data->buildAgentSalesSummary($aggregated),
            'filterLabel' => $this->filters->filterSummary($request),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('agent-sales-report-'.now()->format('Y-m-d').'.pdf');
    }
}
