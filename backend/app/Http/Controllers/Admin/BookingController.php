<?php

namespace App\Http\Controllers\Admin;

use App\Models\Booking;
use App\Services\AdminBookingService;
use App\Services\BookingService;
use App\Services\ZinipayService;
use Illuminate\Http\Request;

class BookingController extends BaseAdminController
{
    public function __construct(
        protected BookingService $bookingService,
        protected ZinipayService $zinipayService,
        protected AdminBookingService $adminBookingService
    ) {
    }

    public function store(Request $request)
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

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Booking created successfully!',
                'booking' => $result['booking'],
                'payment_url' => $result['payment_url'],
                'sms' => $result['sms'],
            ], 201);
        }

        if ($result['payment_url']) {
            return redirect($result['payment_url']);
        }

        return $this->adminTabRedirect($request)->with('success', 'Booking created successfully!');
    }

    public function update(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $request->validate([
            'passenger_name' => 'required|string|max:100',
            'passenger_phone' => 'required|string|max:20',
            'passenger_email' => 'required|email|max:100',
            'seat_numbers' => 'required|string|max:255',
            'total_fare' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'status' => 'required|in:PENDING,PAID,SOLD,BOOKED,CANCEL_REQUESTED,CANCELLED',
        ]);

        $booking->update($request->only([
            'passenger_name',
            'passenger_phone',
            'passenger_email',
            'seat_numbers',
            'total_fare',
            'payment_method',
            'status',
        ]));

        return $this->adminTabRedirect($request)->with('success', 'Booking updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        Booking::findOrFail($id)->delete();

        return $this->adminTabRedirect($request)->with('success', 'Booking deleted successfully!');
    }

    public function cancel(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'Booking not found.']);
        }

        if ($booking->status === 'CANCELLED') {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'Ticket is already cancelled.']);
        }

        if ($booking->status === 'CANCEL_REQUESTED') {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'Cancellation request already submitted. Please wait for admin approval.']);
        }

        $booking->update(['status' => 'CANCEL_REQUESTED']);

        return $this->adminTabRedirect($request)->with('success', 'Cancellation request submitted. It will be cancelled after admin approval.');
    }

    public function approveCancelRequest(Request $request, $id)
    {
        $result = $this->adminBookingService->approveCancelRequest((int) $id);

        if (! $result['success']) {
            return $this->adminTabRedirect($request)->withErrors(['message' => $result['body']['message']]);
        }

        return $this->adminTabRedirect($request)->with('success', $result['body']['message']);
    }

    public function payAdmin($id)
    {
        $booking = Booking::findOrFail($id);
        if ($booking->status !== 'PENDING' || strtolower($booking->payment_method) !== 'zinipay') {
            return redirect()->route('admin.dashboard')->withErrors(['message' => 'Invalid booking status or payment method.']);
        }

        $invoice = $this->zinipayService->createInvoice($booking, 'admin');
        if ($invoice && isset($invoice['payment_url'])) {
            $booking->update(['payment_invoice_id' => $invoice['invoice_id']]);

            return redirect($invoice['payment_url']);
        }

        return redirect()->route('admin.dashboard')->withErrors(['message' => 'Failed to initiate ZiniPay payment.']);
    }
}
