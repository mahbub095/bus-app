<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * Store a new booking.
     */
    public function store(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'passenger_name' => 'required|string|max:100',
            'passenger_phone' => 'required|string|max:20',
            'passenger_email' => 'required|email|max:100',
            'seat_numbers' => 'required|string', // Comma separated list like A1,A2
            'payment_method' => 'required|string',
            'promo_code' => 'nullable|string'
        ]);

        $scheduleId = $request->input('schedule_id');
        $seatNumbersInput = $request->input('seat_numbers');
        $promoCode = $request->input('promo_code');

        // Parse seats
        $requestedSeats = array_map('trim', explode(',', $seatNumbersInput));
        $requestedSeats = array_filter($requestedSeats); // remove empty

        if (empty($requestedSeats)) {
            return response()->json([
                'message' => 'Please select at least one seat.'
            ], 422);
        }

        // Fetch the schedule
        $schedule = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])->find($scheduleId);

        // TRANSACTION to ensure thread safety
        return DB::transaction(function() use ($request, $schedule, $requestedSeats, $seatNumbersInput, $promoCode) {
            
            // 1. Get already booked seats for this schedule
            $activeBookings = Booking::where('schedule_id', $schedule->id)
                ->where('status', 'PAID')
                ->get();

            $bookedSeats = [];
            foreach ($activeBookings as $booking) {
                $seats = explode(',', $booking->seat_numbers);
                foreach ($seats as $seat) {
                    $bookedSeats[trim($seat)] = true;
                }
            }

            // 2. Check if any requested seat is already booked
            foreach ($requestedSeats as $reqSeat) {
                if (isset($bookedSeats[$reqSeat])) {
                    return response()->json([
                        'message' => "Seat {$reqSeat} is already booked. Please select another seat."
                    ], 422);
                }
            }

            // 3. Calculate Fare
            $seatCount = count($requestedSeats);
            $subtotal = floatval($schedule->fare) * $seatCount;
            $discount = 0.00;

            // 4. Apply Coupon if provided
            if ($promoCode) {
                $promotion = Promotion::where('code', strtoupper($promoCode))->first();
                if ($promotion) {
                    $discount = floatval($promotion->discount_amount);
                }
            }

            $totalFare = max(0.00, $subtotal - $discount);

            // 5. Create Booking
            $booking = Booking::create([
                'schedule_id' => $schedule->id,
                'passenger_name' => $request->input('passenger_name'),
                'passenger_phone' => $request->input('passenger_phone'),
                'passenger_email' => $request->input('passenger_email'),
                'seat_numbers' => implode(',', $requestedSeats),
                'total_fare' => $totalFare,
                'payment_method' => $request->input('payment_method'),
                'status' => 'PAID'
            ]);

            // 6. Return response styled like a PNR invoice ticket
            return response()->json([
                'message' => 'Booking successfully created!',
                'booking' => [
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
                            'duration' => $schedule->route->duration
                        ]
                    ]
                ]
            ], 201);
        });
    }

    /**
     * Search for bookings by ticket number (PNR) or phone number.
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:4'
        ]);

        $searchQuery = $request->query('query');

        $query = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation'
        ]);

        // If it starts with 'SE' and is followed by numbers, it's a PNR
        if (str_starts_with(strtoupper($searchQuery), 'SE')) {
            $idStr = substr($searchQuery, 2);
            if (is_numeric($idStr)) {
                $query->where('id', intval($idStr));
            } else {
                $query->where('passenger_phone', 'like', "%{$searchQuery}%")
                      ->orWhere('passenger_email', 'like', "%{$searchQuery}%");
            }
        } else {
            $query->where('passenger_phone', 'like', "%{$searchQuery}%")
                  ->orWhere('passenger_email', 'like', "%{$searchQuery}%");
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        $formatted = $bookings->map(function($b) {
            return [
                'id' => $b->id,
                'pnr' => 'SE' . str_pad($b->id, 5, '0', STR_PAD_LEFT),
                'passenger_name' => $b->passenger_name,
                'passenger_phone' => $b->passenger_phone,
                'passenger_email' => $b->passenger_email,
                'seat_numbers' => $b->seat_numbers,
                'total_fare' => floatval($b->total_fare),
                'payment_method' => $b->payment_method,
                'status' => $b->status,
                'created_at' => $b->created_at->toIso8601String(),
                'schedule' => [
                    'departure_time' => $b->schedule->departure_time->toIso8601String(),
                    'arrival_time' => $b->schedule->arrival_time->toIso8601String(),
                    'bus' => [
                        'operator_name' => $b->schedule->bus->operator_name,
                        'coach_number' => $b->schedule->bus->coach_number,
                        'coach_type' => $b->schedule->bus->coach_type,
                    ],
                    'route' => [
                        'from' => $b->schedule->route->departureStation->name,
                        'to' => $b->schedule->route->arrivalStation->name,
                        'duration' => $b->schedule->route->duration
                    ]
                ]
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Cancel a booking.
     */
    public function cancel($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found.'
            ], 404);
        }

        if ($booking->status === 'CANCELLED') {
            return response()->json([
                'message' => 'Ticket is already cancelled.'
            ], 400);
        }

        // Mark as cancelled
        $booking->update([
            'status' => 'CANCELLED'
        ]);

        return response()->json([
            'message' => 'Booking successfully cancelled! Refund will be processed shortly.',
            'booking_id' => $booking->id,
            'status' => 'CANCELLED'
        ]);
    }
}
