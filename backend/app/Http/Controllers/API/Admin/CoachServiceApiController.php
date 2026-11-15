<?php

namespace App\Http\Controllers\API\Admin;

use App\Services\CoachServicesService;
use App\Services\SeatMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Coach service search + seat operations (dashboard "Coach Services" tab).
 */
class CoachServiceApiController extends BaseAdminApiController
{
    public function __construct(
        protected CoachServicesService $coachServicesService,
        protected SeatMapService $seatMapService,
    ) {
    }

    /**
     * Search departures together with their seat map.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|integer|exists:stations,id',
            'to' => 'nullable|integer|exists:stations,id',
            'date' => 'nullable|date_format:Y-m-d',
            'coach_type' => 'nullable|string|max:20',
            'include_seat_bookings' => 'nullable|boolean',
        ]);

        $results = $this->coachServicesService->search(
            $request->filled('from') ? (int) $request->query('from') : null,
            $request->filled('to') ? (int) $request->query('to') : null,
            $request->query('date') ?: null,
            $request->query('coach_type'),
            includeSeatBookings: $request->boolean('include_seat_bookings', true)
        );

        return response()->json([
            'data' => $results->values(),
            'count' => $results->count(),
        ]);
    }

    /**
     * Block / unblock a seat on a single departure.
     */
    public function toggleBlockedSeat(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'seat' => ['required', 'string', 'regex:/^(L-|U-)?[A-Z][1-4]$/i'],
        ]);

        $result = $this->coachServicesService->toggleBlockedSeat($id, (string) $request->input('seat'));

        return $this->serviceResponse($result);
    }

    /**
     * Hold a seat while the admin fills in the booking form.
     */
    public function holdSeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'seat_number' => 'required|string',
        ]);

        $result = $this->seatMapService->holdSeat(
            (int) $validated['schedule_id'],
            $validated['seat_number'],
            (int) $request->user()->id
        );

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json(['message' => $result['message']], 422);
    }

    /**
     * Release a seat held by this admin.
     */
    public function releaseSeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'seat_number' => 'required|string',
        ]);

        $result = $this->seatMapService->releaseSeat(
            (int) $validated['schedule_id'],
            $validated['seat_number'],
            (int) $request->user()->id
        );

        return response()->json($result);
    }
}
