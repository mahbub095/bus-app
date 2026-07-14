<?php

namespace App\Http\Controllers\API;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends BaseController
{
    public function __construct(protected BookingService $bookingService)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'schedule_id'      => 'required|exists:schedules,id',
            'passenger_name'   => 'required|string|max:100',
            'passenger_phone'  => 'required|string|max:20',
            'passenger_email'  => 'required|email|max:100',
            'seat_numbers'     => 'required|string|max:255',
            'payment_method'   => 'required|string|max:50',
            'total_fare'       => 'nullable|numeric|min:0',
            'passenger_gender' => 'nullable|in:M,F',
            'boarding_point'   => 'nullable|string|max:150',
            'dropping_point'   => 'nullable|string|max:150',
            'promo_code'       => 'nullable|string',
            // Admin users may set an explicit status via the mobile/API client
            'status'           => 'sometimes|in:PENDING,PAID,SOLD,BOOKED,CANCEL_REQUESTED,CANCELLED',
        ]);

        $result = $this->bookingService->createForCustomer($validated, $request->user());

        return response()->json($result['body'], $result['status']);
    }

    public function mine(Request $request)
    {
        $bookings = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(
            $bookings->map(fn ($b) => $this->bookingService->formatForApi($b))->values()
        );
    }

    public function cancel(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        $result = $this->bookingService->cancelForCustomer($booking, $request->user());

        return response()->json($result['body'], $result['status']);
    }

    public function showPublic($id)
    {
        $booking = Booking::findOrFail($id);

        return response()->json($this->bookingService->formatForApi($booking));
    }
}
