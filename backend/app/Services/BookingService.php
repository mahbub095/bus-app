<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared booking logic for customer API and admin panel.
 */
class BookingService
{
    public function __construct(
        protected SeatMapService $seatMapService,
        protected SmsGatewayService $smsGatewayService,
        protected ZinipayService $zinipayService,
    ) {
    }

    /**
     * Create a booking for an authenticated customer (with seat locking).
     *
     * @return array{status: int, body: array}
     */
    public function createForCustomer(array $input, User $user): array
    {
        $paymentMethod = strtolower($input['payment_method']);

        if (($paymentMethod === 'cash' || ($input['status'] ?? null) === 'BOOKED') && ! $user->isAdmin()) {
            return [
                'status' => 403,
                'body' => ['message' => 'Only admins or super admins can book a seat manually.'],
            ];
        }

        $requestedSeats = $this->parseSeatNumbers($input['seat_numbers']);

        if (empty($requestedSeats)) {
            return ['status' => 422, 'body' => ['message' => 'Please select at least one seat.']];
        }

        if (count($requestedSeats) > 4) {
            return ['status' => 422, 'body' => ['message' => 'You can select a maximum of 4 seats per booking.']];
        }

        $schedule = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])
            ->findOrFail($input['schedule_id']);

        try {
            return DB::transaction(function () use ($input, $schedule, $requestedSeats, $user, $paymentMethod) {
                $activeBookings = Booking::where('schedule_id', $schedule->id)
                    ->where(function ($q) {
                        $q->whereIn('status', ['PAID', 'SOLD', 'BOOKED'])
                            ->orWhere(function ($qp) {
                                $qp->where('status', 'PENDING')
                                    ->where('created_at', '>=', now()->subMinutes(10));
                            });
                    })
                    ->select(SeatMapService::paidBookingColumns())
                    ->lockForUpdate()
                    ->get();

                $seatMap = $this->seatMapService->buildSeatMap($schedule, $activeBookings);

                foreach ($requestedSeats as $reqSeat) {
                    $status = $seatMap[$reqSeat] ?? 'available';
                    if (! $this->seatMapService->isSeatSelectable($status)) {
                        return [
                            'status' => 422,
                            'body' => ['message' => "Seat {$reqSeat} is not available. Please select another seat."],
                        ];
                    }
                }

                $totalFare = $this->calculateTotalFare(
                    count($requestedSeats),
                    (float) $schedule->fare,
                    $paymentMethod,
                    $input['promo_code'] ?? null
                );

                $boardingPoints = $this->seatMapService->boardingPoints($schedule);
                $droppingPoints = $this->seatMapService->droppingPoints($schedule);

                $booking = Booking::create([
                    'user_id' => $user->id,
                    'schedule_id' => $schedule->id,
                    'passenger_name' => $input['passenger_name'],
                    'passenger_phone' => $input['passenger_phone'],
                    'passenger_email' => $input['passenger_email'],
                    'passenger_gender' => $input['passenger_gender'] ?? 'M',
                    'boarding_point' => $input['boarding_point'] ?? ($boardingPoints[0]['value'] ?? null),
                    'dropping_point' => $input['dropping_point'] ?? ($droppingPoints[0]['value'] ?? null),
                    'seat_class' => $this->seatMapService->seatClassForCoach($schedule->bus->coach_type ?? null),
                    'seat_numbers' => implode(',', $requestedSeats),
                    'total_fare' => $totalFare,
                    'payment_method' => $input['payment_method'],
                    'status' => $this->resolveStatus($paymentMethod),
                ]);

                if ($paymentMethod === 'zinipay') {
                    return $this->attachZinipayInvoice($booking, $schedule, 'frontend');
                }

                $smsResult = $this->smsGatewayService->sendBookingVerification($booking);

                return [
                    'status' => 201,
                    'body' => [
                        'message' => 'Booking successfully created!',
                        'booking' => $this->formatForApi($booking, $schedule),
                        'sms' => $smsResult,
                    ],
                ];
            });
        } catch (\Exception $e) {
            return ['status' => 500, 'body' => ['message' => $e->getMessage()]];
        }
    }

    /**
     * Create a booking from the admin panel (no seat locking).
     *
     * @return array{booking: Booking, payment_url: ?string, sms: array}
     */
    public function createForAdmin(array $input): array
    {
        $schedule = Schedule::with('bus')->findOrFail($input['schedule_id']);
        $seats = $this->parseSeatNumbers($input['seat_numbers']);
        $seatCount = max(1, count($seats));
        $paymentMethod = strtolower($input['payment_method']);
        $applyGateway = $paymentMethod !== 'cash';
        $pricing = $this->seatMapService->pricingBreakdown($seatCount, (float) $schedule->fare, $applyGateway);
        $totalFare = $input['total_fare'] ?? $pricing['total'];

        $boardingPoints = $this->seatMapService->boardingPoints($schedule);
        $droppingPoints = $this->seatMapService->droppingPoints($schedule);

        $status = $input['status'] ?? $this->resolveStatus($paymentMethod);

        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => $input['passenger_name'],
            'passenger_phone' => $input['passenger_phone'],
            'passenger_email' => $input['passenger_email'],
            'passenger_gender' => $input['passenger_gender'] ?? 'M',
            'boarding_point' => $input['boarding_point'] ?? ($boardingPoints[0]['value'] ?? null),
            'dropping_point' => $input['dropping_point'] ?? ($droppingPoints[0]['value'] ?? null),
            'seat_class' => $this->seatMapService->seatClassForCoach($schedule->bus->coach_type ?? null),
            'seat_numbers' => $input['seat_numbers'],
            'total_fare' => $totalFare,
            'payment_method' => $input['payment_method'],
            'status' => $status,
        ]);

        $paymentUrl = null;
        if ($paymentMethod === 'zinipay' && $booking->status === 'PENDING') {
            $invoice = $this->zinipayService->createInvoice($booking, 'admin');
            if ($invoice && isset($invoice['payment_url'])) {
                $booking->update(['payment_invoice_id' => $invoice['invoice_id']]);
                $paymentUrl = $invoice['payment_url'];
            }
        }

        $smsResult = ['success' => false, 'message' => 'SMS not sent (booking is not PAID).'];
        if ($booking->status === 'PAID') {
            $smsResult = $this->smsGatewayService->sendBookingVerification($booking);
        }

        return [
            'booking' => $booking,
            'payment_url' => $paymentUrl,
            'sms' => $smsResult,
        ];
    }

    /**
     * Customer-initiated cancel or cancel-request.
     *
     * @return array{status: int, body: array}
     */
    public function cancelForCustomer(Booking $booking, User $user): array
    {
        if ((int) $booking->user_id !== (int) $user->id) {
            return ['status' => 403, 'body' => ['message' => 'You are not authorized to cancel this ticket.']];
        }

        if ($booking->status === 'PENDING') {
            $booking->update(['status' => 'CANCELLED']);

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Ticket booking cancelled.',
                    'booking_id' => $booking->id,
                    'status' => 'CANCELLED',
                ],
            ];
        }

        if ($booking->status === 'CANCELLED') {
            return ['status' => 400, 'body' => ['message' => 'Ticket is already cancelled.']];
        }

        if ($booking->status === 'CANCEL_REQUESTED') {
            return [
                'status' => 400,
                'body' => ['message' => 'Cancellation request already submitted. Please wait for admin approval.'],
            ];
        }

        $booking->update(['status' => 'CANCEL_REQUESTED']);

        return [
            'status' => 200,
            'body' => [
                'message' => 'Cancellation request submitted. It will be cancelled after admin approval.',
                'booking_id' => $booking->id,
                'status' => 'CANCEL_REQUESTED',
            ],
        ];
    }

    public function formatForApi(Booking $booking, ?Schedule $schedule = null): array
    {
        $booking->loadMissing([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ]);

        $schedule = $schedule ?? $booking->schedule;

        return [
            'id' => $booking->id,
            'pnr' => $booking->pnr,
            'passenger_name' => $booking->passenger_name,
            'passenger_phone' => $booking->passenger_phone,
            'passenger_email' => $booking->passenger_email,
            'seat_numbers' => $booking->seat_numbers,
            'total_fare' => floatval($booking->total_fare),
            'payment_method' => $booking->payment_method,
            'payment_invoice_id' => $booking->payment_invoice_id,
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

    protected function attachZinipayInvoice(Booking $booking, Schedule $schedule, string $context): array
    {
        $invoice = $this->zinipayService->createInvoice($booking, $context);

        if ($invoice && isset($invoice['payment_url'])) {
            $booking->update(['payment_invoice_id' => $invoice['invoice_id']]);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Booking initiated. Redirecting to payment...',
                    'payment_url' => $invoice['payment_url'],
                    'invoice_id' => $invoice['invoice_id'],
                    'booking' => $this->formatForApi($booking, $schedule),
                ],
            ];
        }

        throw new \Exception('Failed to create payment invoice with ZiniPay.');
    }

    protected function calculateTotalFare(int $seatCount, float $baseFare, string $paymentMethod, ?string $promoCode): float
    {
        $applyGateway = $paymentMethod !== 'cash';
        $pricing = $this->seatMapService->pricingBreakdown($seatCount, $baseFare, $applyGateway);
        $totalFare = $pricing['total'];

        if ($promoCode) {
            $promotion = Promotion::where('code', strtoupper($promoCode))->first();
            if ($promotion) {
                $totalFare = max(0.00, $totalFare - floatval($promotion->discount_amount));
            }
        }

        return $totalFare;
    }

    protected function resolveStatus(string $paymentMethod): string
    {
        if ($paymentMethod === 'zinipay') {
            return 'PENDING';
        }

        if (in_array($paymentMethod, ['zinipay', 'bkash', 'nagad', 'card'])) {
            return 'SOLD';
        }

        return 'BOOKED';
    }

    /** @return list<string> */
    protected function parseSeatNumbers(string $seatNumbers): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $seatNumbers))));
    }
}
