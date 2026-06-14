<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Schedule;
use App\Services\SeatMapService;
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use App\Services\ZinipayService;


class BookingController extends Controller
{
    public function __construct(
        protected SmsGatewayService $smsGatewayService,
        protected SeatMapService $seatMapService,
        protected ZinipayService $zinipayService
    ) {
    }

    public function store(Request $request)
    {
        $request->validate([
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

        $schedule = Schedule::with('bus')->findOrFail($request->input('schedule_id'));
        $seats = array_filter(array_map('trim', explode(',', $request->input('seat_numbers'))));
        $seatCount = max(1, count($seats));
        $applyGateway = strtolower($request->input('payment_method')) !== 'cash';
        $pricing = $this->seatMapService->pricingBreakdown($seatCount, (float) $schedule->fare, $applyGateway);
        $totalFare = $request->input('total_fare', $pricing['total']);

        $boardingPoints = $this->seatMapService->boardingPoints($schedule);
        $droppingPoints = $this->seatMapService->droppingPoints($schedule);

        $paymentMethod = strtolower($request->input('payment_method'));
        $isZinipay = $paymentMethod === 'zinipay';
        $isGateway = in_array($paymentMethod, ['zinipay', 'bkash', 'nagad', 'card']);

        $defaultStatus = 'BOOKED';
        if ($isZinipay) {
            $defaultStatus = 'PENDING';
        } elseif ($isGateway) {
            $defaultStatus = 'SOLD';
        }

        $status = $request->input('status', $defaultStatus);

        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => $request->input('passenger_name'),
            'passenger_phone' => $request->input('passenger_phone'),
            'passenger_email' => $request->input('passenger_email'),
            'passenger_gender' => $request->input('passenger_gender', 'M'),
            'boarding_point' => $request->input('boarding_point', $boardingPoints[0]['value'] ?? null),
            'dropping_point' => $request->input('dropping_point', $droppingPoints[0]['value'] ?? null),
            'seat_class' => $this->seatMapService->seatClassForCoach($schedule->bus->coach_type ?? null),
            'seat_numbers' => $request->input('seat_numbers'),
            'total_fare' => $totalFare,
            'payment_method' => $request->input('payment_method'),
            'status' => $status,
        ]);

        $paymentUrl = null;
        if ($isZinipay && $booking->status === 'PENDING') {
            $invoice = $this->zinipayService->createInvoice($booking, 'admin');
            if ($invoice && isset($invoice['payment_url'])) {
                $booking->update([
                    'payment_invoice_id' => $invoice['invoice_id']
                ]);
                $paymentUrl = $invoice['payment_url'];
            }
        }

        $smsResult = ['success' => false, 'message' => 'SMS not sent (booking is not PAID).'];
        if ($booking->status === 'PAID') {
            $smsResult = $this->smsGatewayService->sendBookingVerification($booking);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Booking created successfully!',
                'booking' => $booking,
                'payment_url' => $paymentUrl,
                'sms' => $smsResult,
            ], 201);
        }

        if ($paymentUrl) {
            return redirect($paymentUrl);
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
            'status' => 'required|in:PENDING,PAID,SOLD,BOOKED,CANCEL_REQUESTED,CANCELLED'
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
        $booking = Booking::find($id);

        if (! $booking) {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'Booking not found.']);
        }

        if ($booking->status !== 'CANCEL_REQUESTED') {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'This booking has no pending cancellation request.']);
        }

        $booking->update(['status' => 'CANCELLED']);

        return $this->adminTabRedirect($request)->with('success', 'Cancellation request approved successfully. Booking is now cancelled.');
    }

    public function payAdmin($id)
    {
        $booking = Booking::findOrFail($id);
        if ($booking->status !== 'PENDING' || strtolower($booking->payment_method) !== 'zinipay') {
            return redirect()->route('admin.dashboard')->withErrors(['message' => 'Invalid booking status or payment method.']);
        }

        $invoice = $this->zinipayService->createInvoice($booking, 'admin');
        if ($invoice && isset($invoice['payment_url'])) {
            $booking->update([
                'payment_invoice_id' => $invoice['invoice_id']
            ]);
            return redirect($invoice['payment_url']);
        }

        return redirect()->route('admin.dashboard')->withErrors(['message' => 'Failed to initiate ZiniPay payment.']);
    }
}
