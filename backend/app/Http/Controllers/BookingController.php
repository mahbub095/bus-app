<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendBookingSmsNotification;
use App\Models\Booking;
use App\Models\Schedule;
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(protected SmsGatewayService $smsGatewayService)
    {
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
            'total_fare' => 'required|numeric|min:0',
            'status' => 'sometimes|in:PAID,CANCEL_REQUESTED,CANCELLED'
        ]);

        $schedule = Schedule::findOrFail($request->input('schedule_id'));

        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => $request->input('passenger_name'),
            'passenger_phone' => $request->input('passenger_phone'),
            'passenger_email' => $request->input('passenger_email'),
            'seat_numbers' => $request->input('seat_numbers'),
            'total_fare' => $request->input('total_fare'),
            'payment_method' => $request->input('payment_method'),
            'status' => $request->input('status', 'PAID'),
        ]);

        if ($booking->status === 'PAID') {
            SendBookingSmsNotification::dispatchSync($booking);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Booking created successfully!',
                'booking' => $booking,
            ], 201);
        }

        return redirect()->back()->with('success', 'Booking created successfully!');
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
            'status' => 'required|in:PAID,CANCEL_REQUESTED,CANCELLED'
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

        return redirect()->back()->with('success', 'Booking updated successfully!');
    }

    public function destroy($id)
    {
        Booking::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Booking deleted successfully!');
    }

    public function cancel($id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return redirect()->back()->withErrors(['message' => 'Booking not found.']);
        }

        if ($booking->status === 'CANCELLED') {
            return redirect()->back()->withErrors(['message' => 'Ticket is already cancelled.']);
        }

        if ($booking->status === 'CANCEL_REQUESTED') {
            return redirect()->back()->withErrors(['message' => 'Cancellation request already submitted. Please wait for admin approval.']);
        }

        $booking->update(['status' => 'CANCEL_REQUESTED']);

        return redirect()->back()->with('success', 'Cancellation request submitted. It will be cancelled after admin approval.');
    }

    public function approveCancelRequest($id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return redirect()->back()->withErrors(['message' => 'Booking not found.']);
        }

        if ($booking->status !== 'CANCEL_REQUESTED') {
            return redirect()->back()->withErrors(['message' => 'This booking has no pending cancellation request.']);
        }

        $booking->update(['status' => 'CANCELLED']);

        return redirect()->back()->with('success', 'Cancellation request approved successfully. Booking is now cancelled.');
    }
}
