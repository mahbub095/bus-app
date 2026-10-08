<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportFilterService
{
    public function validateFilters(Request $request): array
    {
        return $request->validate([
                        'group_by' => 'nullable|array',
            'group_by.*' => 'in:date,operator,route,coach_type,payment_method,status',
            'from_date' => 'required_if:period,custom|nullable|date_format:Y-m-d',
            'to_date' => 'required_if:period,custom|nullable|date_format:Y-m-d|after_or_equal:from_date',
            'coach_type' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'route_id' => 'nullable',
            'operator' => 'nullable|string',
        ]);
    }

    public function resolveDateRange(Request $request): array
    {
        $period = $request->input('period', 'monthly');
        $now = Carbon::now();

        switch ($period) {
            case 'weekly':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
            case 'monthly':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'quarterly':
                return [$now->copy()->firstOfQuarter(), $now->copy()->lastOfQuarter()];
            case 'yearly':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            case 'custom':
                return [
                    Carbon::parse($request->input('from_date'))->startOfDay(),
                    Carbon::parse($request->input('to_date'))->endOfDay(),
                ];
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }
    }

    public function periodLabel(Request $request): string
    {
        [$start, $end] = $this->resolveDateRange($request);

        return match ($request->input('period')) {
            'weekly' => 'This Week (' . $start->format('M d') . ' – ' . $end->format('M d, Y') . ')',
            'monthly' => 'This Month (' . $start->format('F Y') . ')',
            'quarterly' => 'This Quarter (' . $start->format('M Y') . ' – ' . $end->format('M Y') . ')',
            'yearly' => 'This Year (' . $start->format('Y') . ')',
            'custom' => 'Custom (' . $start->format('M d, Y') . ' – ' . $end->format('M d, Y') . ')',
            default => $start->format('M d, Y') . ' – ' . $end->format('M d, Y'),
        };
    }

    public function filterSummary(Request $request): string
    {
        $parts = [$this->periodLabel($request)];

        if ($request->filled('coach_type') && $request->coach_type !== 'All') {
            $parts[] = 'Coach: ' . $request->coach_type;
        }

        if ($request->filled('payment_method') && $request->payment_method !== 'All') {
            $parts[] = 'Payment: ' . $request->payment_method;
        }

        if ($request->filled('route_id') && $request->route_id !== 'All') {
            $parts[] = 'Route ID: ' . $request->route_id;
        }

        if ($request->filled('operator') && $request->operator !== 'All') {
            $parts[] = 'Operator: ' . $request->operator;
        }

        return implode(' | ', $parts);
    }
}
