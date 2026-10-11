<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminBookingService;
use App\Services\AdminDashboardAnalyticsService;
use App\Services\CoachServicesService;
use Illuminate\Http\Request;

/**
 * Session-authenticated JSON endpoints for the admin Blade dashboard (fetch/XHR).
 */
class AjaxController extends Controller
{
    public function __construct(
        protected CoachServicesService $coachServicesService,
        protected AdminBookingService $adminBookingService,
        protected AdminDashboardAnalyticsService $dashboardAnalyticsService,
    ) {
    }

    public function dashboardAnalytics(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:today,last_7_days,this_month,this_year',
        ]);

        return response()->json(
            $this->dashboardAnalyticsService->getAnalytics($validated['period'] ?? 'this_month')
        );
    }

    public function bookingLogsApi()
    {
        return response()->json($this->adminBookingService->getBookingLogs());
    }

    public function cancelRequestsLogsApi()
    {
        return response()->json($this->adminBookingService->getCancelRequestsLogs());
    }

    public function searchCoachServices(Request $request)
    {
        $request->validate([
            'from' => 'nullable',
            'to' => 'nullable',
            'date' => 'nullable|date_format:Y-m-d',
            'coach_type' => 'nullable|string',
        ]);

        $from = $request->filled('from') ? (int) $request->query('from') : null;
        $to = $request->filled('to') ? (int) $request->query('to') : null;
        $date = $request->query('date') ?: null;
        $coachType = $request->query('coach_type');

        $results = $this->coachServicesService->search(
            $from,
            $to,
            $date,
            $coachType,
            includeSeatBookings: true
        );

        return response()->json($results);
    }

    public function toggleBlockedSeat(Request $request, int $id)
    {
        $request->validate([
            'seat' => ['required', 'string', 'regex:/^(L-|U-)?[A-Z][1-4]$/i'],
        ]);

        $result = $this->coachServicesService->toggleBlockedSeat($id, $request->input('seat'));

        return response()->json($result['body'], $result['status']);
    }

    public function cancelBookingApi($id)
    {
        $result = $this->adminBookingService->cancelBooking((int) $id);

        return response()->json($result['body'], $result['status']);
    }

    public function requestCancelApi($id)
    {
        $result = $this->adminBookingService->requestCancellation((int) $id);

        return response()->json($result['body'], $result['status']);
    }
}
