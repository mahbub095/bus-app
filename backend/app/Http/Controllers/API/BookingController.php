<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Promotion;
use App\Jobs\SendBookingSmsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Store a new booking (authenticated customers only).
     */
    public function store(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'passenger_name' => 'required|string|max:100',
            'passenger_phone' => 'required|string|max:20',
            'passenger_email' => 'required|email|max:100',
            'seat_numbers' => 'required|string',
            'payment_method' => 'required|string',
            'promo_code' => 'nullable|string',
        ]);

        $user = $request->user();
        $scheduleId = $request->input('schedule_id');
        $promoCode = $request->input('promo_code');

        $requestedSeats = array_filter(array_map('trim', explode(',', $request->input('seat_numbers'))));

        if (empty($requestedSeats)) {
            return response()->json([
                'message' => 'Please select at least one seat.',
            ], 422);
        }

        $schedule = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])->find($scheduleId);

        return DB::transaction(function () use ($request, $schedule, $requestedSeats, $promoCode, $user) {
            $activeBookings = Booking::where('schedule_id', $schedule->id)
                ->where('status', 'PAID')
                ->get(['seat_numbers']); // Only fetch seats column

            // Extract booked seats more efficiently
            $bookedSeats = $this->extractBookedSeats($activeBookings);

            foreach ($requestedSeats as $reqSeat) {
                if (isset($bookedSeats[$reqSeat])) {
                    return response()->json([
                        'message' => "Seat {$reqSeat} is already booked. Please select another seat.",
                    ], 422);
                }
            }

            $seatCount = count($requestedSeats);
            $subtotal = floatval($schedule->fare) * $seatCount;
            $discount = 0.00;

            if ($promoCode) {
                $promotion = Promotion::where('code', strtoupper($promoCode))->first();
                if ($promotion) {
                    $discount = floatval($promotion->discount_amount);
                }
            }

            $totalFare = max(0.00, $subtotal - $discount);

            $booking = Booking::create([
                'user_id' => $user->id,
                'schedule_id' => $schedule->id,
                'passenger_name' => $request->input('passenger_name'),
                'passenger_phone' => $request->input('passenger_phone'),
                'passenger_email' => $request->input('passenger_email'),
                'seat_numbers' => implode(',', $requestedSeats),
                'total_fare' => $totalFare,
                'payment_method' => $request->input('payment_method'),
                'status' => 'PAID',
            ]);

            // Dispatch SMS notification to queue (non-blocking)
            SendBookingSmsNotification::dispatch($booking);

            return response()->json([
                'message' => 'Booking successfully created!',
                'booking' => $this->formatBooking($booking, $schedule),
            ], 201);
        });
    }

    /**
     * List authenticated user's own bookings.
     */
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
            $bookings->map(fn ($b) => $this->formatBooking($b))->values()
        );
    }

    /**
     * Cancel a booking (owner only).
     */
    public function cancel(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return response()->json([
                'message' => 'Booking not found.',
            ], 404);
        }

        if ((int) $booking->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to cancel this ticket.',
            ], 403);
        }

        if ($booking->status === 'CANCELLED') {
            return response()->json([
                'message' => 'Ticket is already cancelled.',
            ], 400);
        }

        if ($booking->status === 'CANCEL_REQUESTED') {
            return response()->json([
                'message' => 'Cancellation request already submitted. Please wait for admin approval.',
            ], 400);
        }

        $booking->update(['status' => 'CANCEL_REQUESTED']);

        return response()->json([
            'message' => 'Cancellation request submitted. It will be cancelled after admin approval.',
            'booking_id' => $booking->id,
            'status' => 'CANCEL_REQUESTED',
        ]);
    }

    protected function formatBooking(Booking $booking, ?Schedule $schedule = null): array
    {
        $booking->loadMissing([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ]);

        $schedule = $schedule ?? $booking->schedule;

        return [
            'id' => $booking->id,
            'pnr' => 'SE' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
            'passenger_name' => $booking->passenger_name,
            'passenger_phone' => $booking->passenger_phone,
            'passenger_email' => $booking->passenger_email,
            'seat_numbers' => $booking->seat_numbers,
            'total_fare' => floatval($booking->total_fare),
            'payment_method' => $booking->payment_method,
            'status' => $booking->status,
            'created_at' => $booking->created_at->toIso8601String(),
            'schedule' => [
                'departure_time' => $schedule->departure_time->toIso8601String(),
                'arrival_time' => $schedule->arrival_time->toIso8601String(),
                'bus' => [
                    'operator_name' => $schedule->bus->operator_name,
                    'coach_number' => $schedule->bus->coach_number,
                    'coach_type' => $schedule->bus->coach_type,
                ],
                'route' => [
                    'from' => $schedule->route->departureStation->name,
                    'to' => $schedule->route->arrivalStation->name,
                    'duration' => $schedule->route->duration,
                ],
            ],
        ];
    }

    /**
     * Extract booked seats from bookings collection.
     * Optimized to reduce memory and processing time.
     */
    protected function extractBookedSeats($bookings): array
    {
        $bookedSeats = [];
        foreach ($bookings as $booking) {
            $seats = array_filter(array_map('trim', explode(',', $booking->seat_numbers)));
            foreach ($seats as $seat) {
                $bookedSeats[$seat] = true;
            }
        }
        return $bookedSeats;
    }
}
