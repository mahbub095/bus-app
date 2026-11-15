<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Booking;
use App\Services\AdminBookingService;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cancellation request queue (dashboard "Cancel Requests" tab).
 */
class CancelRequestApiController extends BaseAdminApiController
{
    public function __construct(
        protected AdminBookingService $adminBookingService,
        protected BookingService $bookingService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'period' => 'nullable|in:all,today,yesterday,this_week,this_month,this_year,custom',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $requests = $this->adminBookingService->cancelRequestsQuery($request)
            ->orderBy('updated_at', 'desc')
            ->paginate($this->perPage($request))
            ->through(fn (Booking $booking) => $this->bookingService->formatForApi($booking) + [
                'cancel_requested_at' => optional($booking->updated_at)->toIso8601String(),
            ]);

        return response()->json($requests);
    }

    /**
     * Approve a pending cancellation — the booking becomes CANCELLED.
     */
    public function approve(int $id): JsonResponse
    {
        return $this->serviceResponse($this->adminBookingService->approveCancelRequest($id));
    }
}
