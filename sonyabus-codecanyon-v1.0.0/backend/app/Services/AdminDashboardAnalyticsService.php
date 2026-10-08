<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardAnalyticsService
{
    /** @var list<string> */
    private const SOLD_STATUSES = ['PAID', 'SOLD', 'BOOKED'];

    /** @return array{0: Carbon, 1: Carbon} */
    public function resolvePeriod(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    public function periodLabel(string $period): string
    {
        [$start, $end] = $this->resolvePeriod($period);

        return match ($period) {
            'today' => 'Today ('.$start->format('M d, Y').')',
            'last_7_days' => 'Last 7 Days ('.$start->format('M d').' – '.$end->format('M d, Y').')',
            'this_year' => 'This Year ('.$start->format('Y').')',
            default => 'This Month ('.$start->format('F Y').')',
        };
    }

    /**
     * @return array{
     *     period: string,
     *     period_label: string,
     *     metrics: array<string, float|int>,
     *     charts: array<string, mixed>
     * }
     */
    public function getAnalytics(string $period = 'this_month'): array
    {
        $period = $this->normalizePeriod($period);
        [$start, $end] = $this->resolvePeriod($period);

        $bookings = Booking::query()
            ->with(['schedule.bus', 'schedule.route.departureStation', 'schedule.route.arrivalStation'])
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $sold = $bookings->whereIn('status', self::SOLD_STATUSES);
        $salesRevenue = (float) $sold->sum('total_fare');
        $confirmedCount = $sold->count();

        $metrics = [
            'sales_revenue' => $salesRevenue,
            'confirmed_bookings' => $confirmedCount,
            'pending_bookings' => $bookings->where('status', 'PENDING')->count(),
            'cancelled_bookings' => $bookings->where('status', 'CANCELLED')->count(),
            'cancel_requests' => $bookings->where('status', 'CANCEL_REQUESTED')->count(),
            'total_bookings' => $bookings->count(),
            'avg_fare' => $confirmedCount > 0 ? round($salesRevenue / $confirmedCount, 2) : 0,
        ];

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'metrics' => $metrics,
            'charts' => [
                'booking_status' => $this->bookingStatusChart($bookings),
                'payment_methods' => $this->paymentMethodsChart($sold),
                'revenue_trend' => $this->dailyRevenueChart($sold, $start, $end),
                'bookings_trend' => $this->dailyBookingsChart($bookings, $start, $end),
                'coach_types' => $this->coachTypesChart($sold),
                'top_routes' => $this->topRoutesChart($sold),
            ],
        ];
    }

    private function normalizePeriod(string $period): string
    {
        return in_array($period, ['today', 'last_7_days', 'this_month', 'this_year'], true)
            ? $period
            : 'this_month';
    }

    /** @return array{labels: list<string>, data: list<int>} */
    private function bookingStatusChart(Collection $bookings): array
    {
        $groups = [
            'Confirmed' => 0,
            'Pending' => 0,
            'Cancelled' => 0,
            'Cancel Requested' => 0,
            'Other' => 0,
        ];

        foreach ($bookings as $booking) {
            if (in_array($booking->status, self::SOLD_STATUSES, true)) {
                $groups['Confirmed']++;
            } elseif ($booking->status === 'PENDING') {
                $groups['Pending']++;
            } elseif ($booking->status === 'CANCELLED') {
                $groups['Cancelled']++;
            } elseif ($booking->status === 'CANCEL_REQUESTED') {
                $groups['Cancel Requested']++;
            } else {
                $groups['Other']++;
            }
        }

        return [
            'labels' => array_keys($groups),
            'data' => array_values($groups),
        ];
    }

    /** @return array{labels: list<string>, data: list<int>} */
    private function paymentMethodsChart(Collection $sold): array
    {
        $counts = $sold
            ->groupBy(fn ($booking) => $booking->payment_method ?: 'Unknown')
            ->map->count()
            ->sortDesc();

        return [
            'labels' => $counts->keys()->values()->all(),
            'data' => $counts->values()->all(),
        ];
    }

    /** @return array{labels: list<string>, data: list<float>} */
    private function dailyRevenueChart(Collection $sold, Carbon $start, Carbon $end): array
    {
        $byDay = $sold->groupBy(fn ($booking) => $booking->created_at->format('Y-m-d'))
            ->map(fn ($items) => (float) $items->sum('total_fare'));

        return $this->buildDailySeries($start, $end, $byDay, 0.0);
    }

    /** @return array{labels: list<string>, data: list<int>} */
    private function dailyBookingsChart(Collection $bookings, Carbon $start, Carbon $end): array
    {
        $byDay = $bookings->groupBy(fn ($booking) => $booking->created_at->format('Y-m-d'))
            ->map->count();

        return $this->buildDailySeries($start, $end, $byDay, 0);
    }

    /** @return array{labels: list<string>, data: list<int|float>} */
    private function buildDailySeries(Carbon $start, Carbon $end, Collection $byDay, int|float $default): array
    {
        $labels = [];
        $data = [];
        $cursor = $start->copy()->startOfDay();

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('M d');
            $data[] = $byDay->get($key, $default);
            $cursor->addDay();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /** @return array{labels: list<string>, data: list<int>} */
    private function coachTypesChart(Collection $sold): array
    {
        $counts = ['AC' => 0, 'Non AC' => 0, 'Unknown' => 0];

        foreach ($sold as $booking) {
            $type = trim((string) ($booking->schedule?->bus?->coach_type ?? ''));
            if (strcasecmp($type, 'AC') === 0) {
                $counts['AC']++;
            } elseif (strcasecmp($type, 'Non AC') === 0) {
                $counts['Non AC']++;
            } else {
                $counts['Unknown']++;
            }
        }

        if ($counts['Unknown'] === 0) {
            unset($counts['Unknown']);
        }

        return [
            'labels' => array_keys($counts),
            'data' => array_values($counts),
        ];
    }

    /** @return array{labels: list<string>, data: list<int>} */
    private function topRoutesChart(Collection $sold): array
    {
        $routeCounts = $sold
            ->groupBy(function ($booking) {
                $route = $booking->schedule?->route;
                if (! $route) {
                    return 'Unknown Route';
                }

                $from = $route->departureStation->name ?? 'N/A';
                $to = $route->arrivalStation->name ?? 'N/A';

                return $from.' → '.$to;
            })
            ->map->count()
            ->sortDesc()
            ->take(5);

        if ($routeCounts->isEmpty()) {
            return ['labels' => ['No data'], 'data' => [0]];
        }

        return [
            'labels' => $routeCounts->keys()->values()->all(),
            'data' => $routeCounts->values()->all(),
        ];
    }
}
