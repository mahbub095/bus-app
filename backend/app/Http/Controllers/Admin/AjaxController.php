<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminBookingService;
use App\Services\AdminDashboardAnalyticsService;
use App\Services\CoachServicesService;
use App\Services\SeatMapService;
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
        protected SeatMapService $seatMapService,
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

    /**
     * Hold a seat on behalf of the logged-in admin user so frontend users
     * cannot book the same seat while the admin is filling in the form.
     */
    public function holdSeat(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'seat_number' => 'required|string',
        ]);

        // Admin users are authenticated via the web (session) guard.
        // We prefix their ID with a large constant so it never collides with
        // customer Sanctum user IDs in the cache hold records.
        $userId = (int) $request->user()->id;

        $result = $this->seatMapService->holdSeat(
            (int) $validated['schedule_id'],
            $validated['seat_number'],
            $userId
        );

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json(['message' => $result['message']], 422);
    }

    /**
     * Release a held seat for the logged-in admin user.
     */
    public function releaseSeat(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'seat_number' => 'required|string',
        ]);

        $userId = (int) $request->user()->id;

        $result = $this->seatMapService->releaseSeat(
            (int) $validated['schedule_id'],
            $validated['seat_number'],
            $userId
        );

        return response()->json($result, 200);
    }
}
