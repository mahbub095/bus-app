<?php

namespace App\Services;

use App\Models\Booking;

class AdminBookingService
{
    public function getBookingLogs(): array
    {
        $recentBookings = Booking::with([
            'schedule.bus',
            'schedule.route.departureStation',
            'schedule.route.arrivalStation',
        ])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return [
            'bookings' => $recentBookings->map(fn ($booking) => $this->formatLogEntry($booking))->values(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function getCancelRequestsLogs(): array
    {
        $cancelRequests = Booking::where('status', 'CANCEL_REQUESTED')
            ->with([
                'schedule.bus',
                'schedule.route.departureStation',
                'schedule.route.arrivalStation',
            ])
            ->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get();

        return [
            'cancel_requests' => $cancelRequests->map(fn ($booking) => $this->formatLogEntry($booking))->values(),
            'updated_at' => now()->toIso8601String(),
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
            'pnr' => 'SE' . str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT),
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
                ],
                'route' => [
                    'from' => $booking->schedule?->route?->departureStation?->name,
                    'to' => $booking->schedule?->route?->arrivalStation?->name,
                ],
            ],
        ];
    }
}
