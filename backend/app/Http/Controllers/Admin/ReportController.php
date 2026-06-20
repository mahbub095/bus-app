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
}
