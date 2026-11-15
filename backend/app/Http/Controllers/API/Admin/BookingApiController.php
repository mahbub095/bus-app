<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Booking;
use App\Services\AdminBookingService;
use App\Services\BookingService;
use App\Services\ZinipayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bookings management (dashboard "Bookings" tab).
 *
 * Formatting is shared with the customer API through BookingService::formatForApi
 * so a ticket looks identical on both sides.
 */
class BookingApiController extends BaseAdminApiController
{
    public function __construct(
        protected BookingService $bookingService,
        protected AdminBookingService $adminBookingService,
        protected ZinipayService $zinipayService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->validateFilters($request);

        $bookings = $this->adminBookingService->bookingQuery($request)
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', (string) $request->query('status'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage($request))
            ->through(fn (Booking $booking) => $this->bookingService->formatForApi($booking));

        return response()->json($bookings);
    }

    public function show(int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        return $this->itemResponse($this->bookingService->formatForApi($booking));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'passenger_name' => 'required|string|max:100',
            'passenger_phone' => 'required|string|max:20',
            'passenger_email' => 'required|email|max:100',
            'seat_numbers' => 'required|string|max:255',
            'payment_method' => 'required|string|max:50',
            'total_fare' => 'nullable|numeric|min:0',
            'passenger_gender' => 'nullable|in:M,F',
            'boarding_point' => 'nullable|string|max:150',
            'dropping_point' => 'nullable|string|max:150',
            'status' => 'sometimes|in:PENDING,PAID,SOLD,BOOKED,CANCEL_REQUESTED,CANCELLED',
        ]);

        $result = $this->bookingService->createForAdmin($validated);

        return response()->json([
            'message' => 'Booking created successfully.',
            'data' => $this->bookingService->formatForApi($result['booking']),
            'payment_url' => $result['payment_url'],
            'sms' => $result['sms'],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'passenger_name' => 'required|string|max:100',
            'passenger_phone' => 'required|string|max:20',
            'passenger_email' => 'required|email|max:100',
            'seat_numbers' => 'required|string|max:255',
            'total_fare' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'status' => 'required|in:PENDING,PAID,SOLD,BOOKED,CANCEL_REQUESTED,CANCELLED',
        ]);

        $booking->update($validated);

        return $this->itemResponse(
            $this->bookingService->formatForApi($booking->fresh()),
            'Booking updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        Booking::findOrFail($id)->delete();

        return response()->json(['message' => 'Booking deleted successfully.']);
    }

    /**
     * Cancel immediately (admin override of the request/approve flow).
     */
    public function cancel(int $id): JsonResponse
    {
        return $this->serviceResponse($this->adminBookingService->cancelBooking($id));
    }

    /**
     * Ask for a cancellation instead of cancelling right away.
     */
    public function requestCancel(int $id): JsonResponse
    {
        return $this->serviceResponse($this->adminBookingService->requestCancellation($id));
    }

    /**
     * Start a ZiniPay invoice for a pending online booking.
     */
    public function pay(int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        if ($booking->status !== 'PENDING' || strtolower((string) $booking->payment_method) !== 'zinipay') {
            return response()->json([
                'message' => 'Invalid booking status or payment method.',
            ], 422);
        }

        $invoice = $this->zinipayService->createInvoice($booking, 'admin');

        if (! $invoice || ! isset($invoice['payment_url'])) {
            return response()->json(['message' => 'Failed to initiate ZiniPay payment.'], 502);
        }

        $booking->update(['payment_invoice_id' => $invoice['invoice_id']]);

        return response()->json([
            'message' => 'Payment invoice created.',
            'payment_url' => $invoice['payment_url'],
            'invoice_id' => $invoice['invoice_id'],
        ]);
    }

    /**
     * Same filters the dashboard log accepts.
     */
    protected function validateFilters(Request $request): void
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:PENDING,PAID,SOLD,BOOKED,CANCEL_REQUESTED,CANCELLED',
            'period' => 'nullable|in:all,today,yesterday,this_week,this_month,this_year,custom',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
    }
}
