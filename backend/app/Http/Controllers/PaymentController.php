<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\ZinipayService;
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected ZinipayService $zinipayService,
        protected SmsGatewayService $smsGatewayService
    ) {}

    /**
     * Handle redirection from ZiniPay after payment attempt.
     */
    public function callback(Request $request)
    {
        $bookingId = $request->query('booking_id');
        $source = $request->query('source', 'frontend');
        $invoiceId = $request->query('invoice_id'); // ZiniPay may append this

        $booking = Booking::find($bookingId);

        if (!$booking) {
            return $this->handleRedirectWithError($source, 'Booking not found.');
        }

        // If it's already paid, just redirect to success
        if (in_array($booking->status, ['PAID', 'SOLD'])) {
            return $this->handleRedirectWithSuccess($source, $booking);
        }

        $targetInvoiceId = $booking->payment_invoice_id ?? $invoiceId;

        if (!$targetInvoiceId) {
            return $this->handleRedirectWithError($source, 'Payment reference missing.');
        }

        // Verify with ZiniPay
        $status = $this->zinipayService->verifyPayment($targetInvoiceId);

        if ($status === 'COMPLETED') {
            $booking->update(['status' => 'SOLD']);
            
            // Send booking verification SMS
            $this->smsGatewayService->sendBookingVerification($booking);

            return $this->handleRedirectWithSuccess($source, $booking);
        }

        if ($status === 'FAILED') {
            $booking->update(['status' => 'CANCELLED']);
        }

        Log::warning('ZiniPay payment callback verification was not completed.', [
            'booking_id' => $booking->id,
            'status' => $status
        ]);

        return $this->handleRedirectWithError($source, 'Payment verification failed or pending.');
    }

    public function cancel(Request $request)
    {
        $bookingId = $request->query('booking_id');
        $source = $request->query('source', 'frontend');

        $booking = Booking::find($bookingId);
        if ($booking && $booking->status === 'PENDING') {
            $booking->update(['status' => 'CANCELLED']);
        }

        if ($source === 'admin') {
            return redirect()->route('admin.dashboard')
                ->withFragment('bookings')
                ->withErrors(['Payment was cancelled.']);
        }

        return redirect('http://localhost:5173/?payment=cancelled&booking_id=' . ($booking ? $booking->id : ''));
    }

    /**
     * Handle asynchronous webhook notifications from ZiniPay.
     */
    public function webhook(Request $request)
    {
        // Log webhook payload
        Log::info('ZiniPay Webhook received', $request->all());

        $invoiceId = $request->input('invoice_id');
        $status = $request->input('status');

        if (!$invoiceId) {
            return response()->json(['message' => 'Missing invoice_id'], 400);
        }

        $booking = Booking::where('payment_invoice_id', $invoiceId)->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if (in_array($booking->status, ['PAID', 'SOLD'])) {
            return response()->json(['message' => 'Already processed'], 200);
        }

        // Verify status from Zinipay API directly for security
        $verifiedStatus = $this->zinipayService->verifyPayment($invoiceId);

        if ($verifiedStatus === 'COMPLETED') {
            $booking->update(['status' => 'SOLD']);
            
            // Send SMS
            $this->smsGatewayService->sendBookingVerification($booking);

            return response()->json([
                'message' => 'Payment verified and booking completed',
                'booking' => [
                    'id' => $booking->id,
                    'pnr' => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
                    'status' => 'SOLD'
                ]
            ], 200);
        }

        if ($verifiedStatus === 'FAILED') {
            $booking->update(['status' => 'CANCELLED']);
        }

        return response()->json(['message' => 'Payment status is: ' . $verifiedStatus], 200);
    }

    protected function handleRedirectWithSuccess(string $source, Booking $booking)
    {
        if ($source === 'admin') {
            return redirect()->route('admin.dashboard')
                ->withFragment('bookings')
                ->with('success', 'Ticket successfully booked and paid via ZiniPay!');
        }

        // For frontend, redirect back to React application with success flags
        $pnr = 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT);
        return redirect('http://localhost:5173/?payment=success&pnr=' . $pnr . '&booking_id=' . $booking->id);
    }

    protected function handleRedirectWithError(string $source, string $error)
    {
        if ($source === 'admin') {
            return redirect()->route('admin.dashboard')
                ->withFragment('bookings')
                ->withErrors([$error]);
        }

        return redirect('http://localhost:5173/?payment=failed&error=' . urlencode($error));
    }
}
