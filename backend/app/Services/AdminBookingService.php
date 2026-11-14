<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminBookingService
{
    /**
     * Resolve a date range from the request's period / custom-date parameters.
     * Returns null when no filtering should be applied (period = "all").
     *
     * @return array{0:Carbon,1:Carbon}|null
     */
    private function resolveLogDateRange(Request $request): ?array
    {
        $period = $request->input('period', 'all');

        if ($period === 'all') {
            return null;
        }

        $now = Carbon::now();

        return match ($period) {
            'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month'  => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'this_year'   => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom'      => $this->resolveCustomRange($request),
            default       => null,
        };
    }

    /**
     * Parse the from_date / to_date inputs for a custom date range.
     *
     * @return array{0:Carbon,1:Carbon}|null
     */
    private function resolveCustomRange(Request $request): ?array
    {
        $from = $request->input('from_date');
        $to = $request->input('to_date');

        if (! $from || ! $to) {
            return null;
        }

        return [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        ];
    }

    public function getBookingLogs(Request $request): array
    {
        $query = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ]);

        $range = $this->resolveLogDateRange($request);
        if ($range) {
            $query->whereBetween('created_at', $range);
        }

        $recentBookings = $query
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return [
            'bookings' => $recentBookings->map(fn ($booking) => $this->formatLogEntry($booking))->values(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function getCancelRequestsLogs(Request $request): array
    {
        $query = Booking::where('status', 'CANCEL_REQUESTED')
            ->with([
                'schedule.bus',
                'schedule.route.departureStation',
                'schedule.route.arrivalStation',
            ]);

        $range = $this->resolveLogDateRange($request);
        if ($range) {
            $query->whereBetween('updated_at', $range);
        }

        $cancelRequests = $query
            ->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get();

        return [
            'cancel_requests' => $cancelRequests->map(fn ($booking) => $this->formatLogEntry($booking))->values(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Set a booking to CANCEL_REQUESTED so it appears in the cancel-requests queue.
     *
     * @return array{success: bool, status: int, body: array<string, mixed>}
     */
    public function requestCancellation(int $id): array
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return [
                'success' => false,
                'status' => 404,
                'body' => ['message' => 'Booking not found.'],
            ];
        }

        if ($booking->status === 'CANCELLED') {
            return [
                'success' => false,
                'status' => 400,
                'body' => ['message' => 'Ticket is already cancelled.'],
            ];
        }

        if ($booking->status === 'CANCEL_REQUESTED') {
            return [
                'success' => false,
                'status' => 400,
                'body' => ['message' => 'Cancellation request already submitted.'],
            ];
        }

        $booking->update(['status' => 'CANCEL_REQUESTED']);

        return [
            'success' => true,
            'status' => 200,
            'body' => [
                'message' => 'Cancellation request submitted successfully.',
                'booking_id' => $booking->id,
                'status' => 'CANCEL_REQUESTED',
            ],
        ];
    }

    /**
     * @return array{success: bool, status: int, body: array<string, mixed>}
     */
    public function cancelBooking(int $id): array
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return [
                'success' => false,
                'status' => 404,
                'body' => ['message' => 'Booking not found.'],
            ];
        }

        if ($booking->status === 'CANCELLED') {
            return [
                'success' => false,
                'status' => 400,
                'body' => ['message' => 'Ticket is already cancelled.'],
            ];
        }

        $booking->update(['status' => 'CANCELLED']);

        return [
            'success' => true,
            'status' => 200,
            'body' => [
                'message' => 'Booking successfully cancelled and seat released!',
                'booking_id' => $booking->id,
                'status' => 'CANCELLED',
            ],
        ];
    }

    /**
     * @return array{success: bool, status: int, body: array<string, mixed>}
     */
    public function approveCancelRequest(int $id): array
    {
        $booking = Booking::find($id);

        if (! $booking) {
            return [
                'success' => false,
                'status' => 404,
                'body' => ['message' => 'Booking not found.'],
            ];
        }

        if ($booking->status !== 'CANCEL_REQUESTED') {
            return [
                'success' => false,
                'status' => 400,
                'body' => ['message' => 'This booking has no pending cancellation request.'],
            ];
        }

        $booking->update(['status' => 'CANCELLED']);

        return [
            'success' => true,
            'status' => 200,
            'body' => [
                'message' => 'Cancellation request approved successfully. Booking is now cancelled.',
                'booking_id' => $booking->id,
                'status' => 'CANCELLED',
            ],
        ];
    }

    protected function formatLogEntry(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'pnr' => $booking->pnr,
            'passenger_name' => $booking->passenger_name,
            'passenger_phone' => $booking->passenger_phone,
            'passenger_email' => $booking->passenger_email,
            'seat_numbers' => $booking->seat_numbers,
            'total_fare' => (float) $booking->total_fare,
            'status' => $booking->status,
            'payment_method' => $booking->payment_method,
            'created_at' => optional($booking->created_at)->toIso8601String(),
            'updated_at' => optional($booking->updated_at)->toIso8601String(),
            'schedule' => [
                'departure_time' => optional($booking->schedule?->departure_time)->toIso8601String(),
                'bus' => [
                    'operator_name' => $booking->schedule?->bus?->operator_name,
                    'coach_number'  => $booking->schedule?->bus?->coach_number,
                ],
                'route' => [
                    'from' => $booking->schedule?->route?->departureStation?->name,
                    'to' => $booking->schedule?->route?->arrivalStation?->name,
                ],
            ],
        ];
    }
}
