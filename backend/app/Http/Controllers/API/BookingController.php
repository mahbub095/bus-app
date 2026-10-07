<?php

namespace App\Http\Controllers\API;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\ZinipayService;
use Illuminate\Http\Request;

class BookingController extends BaseController
{
    public function __construct(
        protected BookingService $bookingService,
        protected \App\Services\SeatMapService $seatMapService,
        protected ZinipayService $zinipayService
    ) {
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

    public function holdSeat(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'seat_number' => 'required|string',
        ]);

        $result = $this->seatMapService->holdSeat(
            (int) $validated['schedule_id'],
            $validated['seat_number'],
            $request->user()->id
        );

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json(['message' => $result['message']], 422);
    }

    public function releaseSeat(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'seat_number' => 'required|string',
        ]);

        $result = $this->seatMapService->releaseSeat(
            (int) $validated['schedule_id'],
            $validated['seat_number'],
            $request->user()->id
        );

        return response()->json($result, 200);
    }

    public function pay(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        if ((int) $booking->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'You are not authorized to pay for this ticket.'], 403);
        }

        if ($booking->status !== 'PENDING' || strtolower($booking->payment_method) !== 'zinipay') {
            return response()->json(['message' => 'Invalid booking status or payment method.'], 422);
        }

        $invoice = $this->zinipayService->createInvoice($booking, 'frontend');

        if ($invoice && isset($invoice['payment_url'])) {
            $booking->update(['payment_invoice_id' => $invoice['invoice_id']]);

            return response()->json([
                'message' => 'Payment initiated. Redirecting to payment...',
                'payment_url' => $invoice['payment_url'],
                'invoice_id' => $invoice['invoice_id'],
                'booking' => $this->bookingService->formatForApi($booking),
            ], 200);
        }

        return response()->json(['message' => 'Failed to initiate ZiniPay payment.'], 500);
    }
}
