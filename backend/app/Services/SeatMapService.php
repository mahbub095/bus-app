<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Support\Carbon;

class SeatMapService
{
    /**
     * Columns needed for seat-map and booking-detail payloads (avoids loading full booking rows).
     *
     * @return list<string>
     */
    public static function paidBookingColumns(): array
    {
        return [
            'id',
            'schedule_id',
            'seat_numbers',
            'status',
            'passenger_gender',
            'payment_method',
            'passenger_name',
            'passenger_phone',
            'passenger_email',
            'total_fare',
            'boarding_point',
            'dropping_point',
            'created_at',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Relations\Relation|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function scopePaidBookingsForSeatMap($query): void
    {
        $query->where(function ($q) {
            $q->where('status', 'PAID')
              ->orWhere(function ($qp) {
                  $qp->where('status', 'PENDING')
                     ->where('created_at', '>=', now()->subMinutes(10));
              });
        })->select(self::paidBookingColumns());
    }

    public function allSeatCodes(): array
    {
        $codes = [];
        foreach (config('booking.seat_rows', ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']) as $row) {
            foreach ([1, 2, 3, 4] as $num) {
                $codes[] = $row . $num;
            }
        }

        return $codes;
    }

    public function allSeatCodesForBus(?\App\Models\Bus $bus = null): array
    {
        if (!$bus) {
            return $this->allSeatCodes();
        }

        // Try using the customized seat layout grid first
        if (!empty($bus->seat_layout_grid) && is_array($bus->seat_layout_grid)) {
            $grid = $bus->seat_layout_grid;
            $seats = [];
            
            // Check if sleeper layout (nested under lower and upper keys)
            if (isset($grid['lower']) || isset($grid['upper'])) {
                foreach (['lower', 'upper'] as $deck) {
                    if (isset($grid[$deck]) && is_array($grid[$deck])) {
                        foreach ($grid[$deck] as $row) {
                            if (is_array($row)) {
                                foreach ($row as $cell) {
                                    if (isset($cell['type']) && $cell['type'] === 'seat' && !empty($cell['label'])) {
                                        $seats[] = $cell['label'];
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($grid as $row) {
                    if (is_array($row)) {
                        foreach ($row as $cell) {
                            if (isset($cell['type']) && $cell['type'] === 'seat' && !empty($cell['label'])) {
                                $seats[] = $cell['label'];
                            }
                        }
                    }
                }
            }
            return $seats;
        }

        $totalSeats = $bus->total_seats;
        $layout = $bus->seat_layout ?? '2+2';
        $rowLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $seats = [];

        if ($layout === '1+2') {
            $seatsCount = 0;
            for ($r = 0; $r < count($rowLetters); $r++) {
                $row = $rowLetters[$r];
                for ($num = 1; $num <= 3; $num++) {
                    if ($seatsCount >= $totalSeats) {
                        break 2;
                    }
                    $seats[] = $row . $num;
                    $seatsCount++;
                }
            }
        } elseif ($layout === 'sleeper') {
            $lowerCount = (int) ceil($totalSeats / 2);
            $upperCount = $totalSeats - $lowerCount;

            // Lower deck seats:
            $remainingSeats = $lowerCount - 4;
            $normalRows = (int) ceil($remainingSeats / 3);
            if ($normalRows < 0) $normalRows = 0;
            $rIndex = 0;
            for ($r = 0; $r < $normalRows; $r++) {
                $row = $rowLetters[$rIndex++];
                for ($num = 1; $num <= 3; $num++) {
                    $seats[] = 'L-' . $row . $num;
                }
            }
            $lastRow = $rowLetters[$rIndex++];
            for ($num = 1; $num <= 4; $num++) {
                $seats[] = 'L-' . $lastRow . $num;
            }

            // Upper deck seats:
            $remainingSeatsU = $upperCount - 4;
            $normalRowsU = (int) ceil($remainingSeatsU / 3);
            if ($normalRowsU < 0) $normalRowsU = 0;
            $rIndex = 0;
            for ($r = 0; $r < $normalRowsU; $r++) {
                $row = $rowLetters[$rIndex++];
                for ($num = 1; $num <= 3; $num++) {
                    $seats[] = 'U-' . $row . $num;
                }
            }
            $lastRowU = $rowLetters[$rIndex++];
            for ($num = 1; $num <= 4; $num++) {
                $seats[] = 'U-' . $lastRowU . $num;
            }
        } elseif ($layout === '2+2_last5') {
            $seatsCount = 0;
            $remainingSeats = $totalSeats - 5;
            $normalRows = (int) ceil($remainingSeats / 4);

            for ($r = 0; $r < $normalRows; $r++) {
                $row = $rowLetters[$r];
                for ($num = 1; $num <= 4; $num++) {
                    if ($seatsCount >= $remainingSeats) {
                        break 2;
                    }
                    $seats[] = $row . $num;
                    $seatsCount++;
                }
            }

            $lastRow = $rowLetters[$normalRows];
            for ($num = 1; $num <= 5; $num++) {
                $seats[] = $lastRow . $num;
            }
        } else {
            // '2+2' or default fallback
            $seatsCount = 0;
            for ($r = 0; $r < count($rowLetters); $r++) {
                $row = $rowLetters[$r];
                for ($num = 1; $num <= 4; $num++) {
                    if ($seatsCount >= $totalSeats) {
                        break 2;
                    }
                    $seats[] = $row . $num;
                    $seatsCount++;
                }
            }
        }

        return $seats;
    }

    /**
     * @return array<string, string> seat code => status key
     */
    public function buildSeatMap(Schedule $schedule, iterable $bookings): array
    {
        $map = [];
        foreach ($this->allSeatCodesForBus($schedule->bus) as $seat) {
            $map[$seat] = 'available';
        }

        $blocked = $this->parseSeatList($schedule->blocked_seats ?? '');
        foreach ($blocked as $seat) {
            if (isset($map[$seat])) {
                $map[$seat] = 'blocked';
            }
        }

        foreach ($bookings as $booking) {
            $isPaid = $booking->status === 'PAID';
            $isRecentPending = $booking->status === 'PENDING' && $booking->created_at->gt(now()->subMinutes(10));

            if (!$isPaid && !$isRecentPending) {
                continue;
            }

            $gender = strtoupper((string) ($booking->passenger_gender ?? 'M')) === 'F' ? 'F' : 'M';
            $isCounter = strtolower((string) $booking->payment_method) === 'cash';
            $statusKey = ($isCounter ? 'booked' : 'sold') . '_' . strtolower($gender);

            foreach ($this->parseSeatList($booking->seat_numbers) as $seat) {
                if (! isset($map[$seat]) || $map[$seat] === 'blocked') {
                    continue;
                }
                $map[$seat] = $statusKey;
            }
        }

        return $map;
    }

    public function boardingPoints(Schedule $schedule): array
    {
        $stored = $schedule->route->boarding_points ?? [];

        if (! empty($stored)) {
            return $this->formatBoardingPoints($stored);
        }

        $station = $schedule->route->departureStation;
        $time = $this->formatScheduleTime($schedule->departure_time);

        return [
            [
                'id' => 'main',
                'name' => $station->name,
                'label' => sprintf('[%s] %s', $time, $station->name),
                'value' => $station->name,
                'reporting_time' => $time,
                'departure_time' => $time,
            ],
        ];
    }

    public function droppingPoints(Schedule $schedule): array
    {
        $stored = $schedule->route->dropping_points ?? [];

        if (! empty($stored)) {
            return $this->formatDroppingPoints($stored);
        }

        $station = $schedule->route->arrivalStation;
        $time = $this->formatScheduleTime($schedule->arrival_time);

        return [
            [
                'id' => 'main',
                'name' => $station->name,
                'label' => sprintf('[%s] %s', $time, $station->name),
                'value' => $station->name,
                'arrival_time' => $time,
            ],
        ];
    }

    protected function formatBoardingPoints(array $points): array
    {
        return array_values(array_map(function ($point, $index) {
            $name = trim((string) ($point['name'] ?? ''));
            $reporting = trim((string) ($point['reporting_time'] ?? ''));
            $departure = trim((string) ($point['departure_time'] ?? ''));

            return [
                'id' => 'bp-' . $index,
                'name' => $name,
                'value' => $name,
                'reporting_time' => $reporting,
                'departure_time' => $departure,
                'label' => $departure !== '' ? sprintf('[%s] %s', $departure, $name) : $name,
            ];
        }, $points, array_keys($points)));
    }

    protected function formatDroppingPoints(array $points): array
    {
        return array_values(array_map(function ($point, $index) {
            $name = trim((string) ($point['name'] ?? ''));
            $arrival = trim((string) ($point['arrival_time'] ?? ''));

            return [
                'id' => 'dp-' . $index,
                'name' => $name,
                'value' => $name,
                'arrival_time' => $arrival,
                'label' => $arrival !== '' ? sprintf('[%s] %s', $arrival, $name) : $name,
            ];
        }, $points, array_keys($points)));
    }

    protected function formatScheduleTime($dateTime): string
    {
        if ($dateTime instanceof Carbon) {
            return $dateTime->format('h:i A');
        }

        return Carbon::parse($dateTime)->format('h:i A');
    }

    public function seatClassForCoach(?string $coachType): string
    {
        if ($coachType && stripos($coachType, 'AC') !== false) {
            return 'E-Class';
        }

        return config('booking.default_seat_class', 'E-Class');
    }

    public function pricingBreakdown(int $seatCount, float $farePerSeat, bool $applyGatewayCharges = true): array
    {
        $seatFare = round($farePerSeat * $seatCount, 2);
        $serviceCharge = round((float) config('booking.service_charge', 20) * $seatCount, 2);
        $gatewayCharge = $applyGatewayCharges
            ? round((float) config('booking.gateway_charge', 16) * $seatCount, 2)
            : 0.0;
        $scDiscount = round((float) config('booking.service_charge_discount', 20) * $seatCount, 2);
        $gcDiscount = $applyGatewayCharges
            ? round((float) config('booking.gateway_charge_discount', 16) * $seatCount, 2)
            : 0.0;

        $total = max(0, $seatFare + $serviceCharge + $gatewayCharge - $scDiscount - $gcDiscount);

        return [
            'seat_fare' => $seatFare,
            'service_charge' => $serviceCharge,
            'gateway_charge' => $gatewayCharge,
            'sc_discount' => $scDiscount,
            'gc_discount' => $gcDiscount,
            'total' => round($total, 2),
        ];
    }

    public function formatSchedulePayload(Schedule $schedule, iterable $bookings): array
    {
        $schedule->loadMissing('route.departureStation', 'route.arrivalStation');
        $seatMap = $this->buildSeatMap($schedule, $bookings);
        $bookedSeats = array_keys(array_filter($seatMap, fn ($s) => $s !== 'available'));
        $routePoints = $this->routePoints($schedule);

        return [
            'seat_map' => $seatMap,
            'booked_seats' => array_values($bookedSeats),
            'boarding_points' => $routePoints['boarding'],
            'dropping_points' => $routePoints['dropping'],
            'seat_class' => $this->seatClassForCoach($schedule->bus->coach_type ?? null),
            'pricing' => $this->pricingBreakdown(1, (float) $schedule->fare),
        ];
    }

    /**
     * @return array{boarding: array, dropping: array}
     */
    public function routePoints(Schedule $schedule): array
    {
        $schedule->loadMissing('route.departureStation', 'route.arrivalStation');

        return [
            'boarding' => $this->boardingPoints($schedule),
            'dropping' => $this->droppingPoints($schedule),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function seatBookingDetails(Schedule $schedule, iterable $bookings): array
    {
        $details = [];

        foreach ($bookings as $booking) {
            $isPaid = $booking->status === 'PAID';
            $isRecentPending = $booking->status === 'PENDING' && $booking->created_at->gt(now()->subMinutes(10));

            if (!$isPaid && !$isRecentPending) {
                continue;
            }

            foreach ($this->parseSeatList($booking->seat_numbers) as $seat) {
                $details[$seat] = [
                    'booking_id' => $booking->id,
                    'pnr' => 'SE' . str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT),
                    'passenger_name' => $booking->passenger_name,
                    'passenger_phone' => $booking->passenger_phone,
                    'passenger_email' => $booking->passenger_email,
                    'passenger_gender' => $booking->passenger_gender ?? 'M',
                    'seat_numbers' => $booking->seat_numbers,
                    'total_fare' => (float) $booking->total_fare,
                    'payment_method' => $booking->payment_method,
                    'status' => $booking->status,
                    'boarding_point' => $booking->boarding_point,
                    'dropping_point' => $booking->dropping_point,
                ];
            }
        }

        return $details;
    }

    public function isSeatSelectable(string $status): bool
    {
        return $status === 'available';
    }

    public function isValidSeatCode(string $seat, ?\App\Models\Bus $bus = null): bool
    {
        $seat = strtoupper(trim($seat));

        if ($bus) {
            return in_array($seat, $this->allSeatCodesForBus($bus), true);
        }

        return in_array($seat, $this->allSeatCodes(), true);
    }

    /**
     * @return list<string>
     */
    public function blockedSeatList(Schedule $schedule): array
    {
        return $this->parseSeatList($schedule->blocked_seats ?? '');
    }

    /**
     * @return array{seat: string, blocked: bool, blocked_seats: list<string>}
     */
    public function toggleBlockedSeat(Schedule $schedule, string $seat, iterable $bookings): array
    {
        $seat = strtoupper(trim($seat));

        if (! $this->isValidSeatCode($seat, $schedule->bus)) {
            throw new \InvalidArgumentException('Invalid seat code.');
        }

        $seatMap = $this->buildSeatMap($schedule, $bookings);
        $status = $seatMap[$seat] ?? 'available';
        $blocked = $this->blockedSeatList($schedule);
        $index = array_search($seat, $blocked, true);

        if ($index !== false) {
            unset($blocked[$index]);
            $blocked = array_values($blocked);
            $schedule->blocked_seats = $blocked === [] ? null : implode(',', $blocked);
            $schedule->save();

            return [
                'seat' => $seat,
                'blocked' => false,
                'blocked_seats' => $blocked,
            ];
        }

        if ($status !== 'available') {
            throw new \InvalidArgumentException('Only available seats can be blocked.');
        }

        $blocked[] = $seat;
        sort($blocked);
        $schedule->blocked_seats = implode(',', $blocked);
        $schedule->save();

        return [
            'seat' => $seat,
            'blocked' => true,
            'blocked_seats' => $blocked,
        ];
    }

    protected function parseSeatList(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
