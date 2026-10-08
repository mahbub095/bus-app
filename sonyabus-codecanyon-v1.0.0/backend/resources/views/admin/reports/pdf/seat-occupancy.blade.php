<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Seat Occupancy Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #555; margin-bottom: 16px; }
        .summary table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary td { padding: 4px 8px; border: 1px solid #ccc; }
        .summary td.label { background: #f3f4f6; font-weight: bold; width: 20%; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        table.data th { background: #0284C7; color: #fff; font-size: 9px; }
        table.data tr:nth-child(even) { background: #f0f9ff; }
        .pct-high   { color: #DC2626; font-weight: bold; }
        .pct-medium { color: #D97706; font-weight: bold; }
        .pct-low    { color: #16A34A; }
    </style>
</head>
<body>
    <h1>Seat Occupancy Report</h1>
    <div class="meta">
        <div><strong>Filters:</strong> {{ $filterLabel }}</div>
        <div><strong>Generated:</strong> {{ $generatedAt }}</div>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td class="label">Total Schedules</td><td>{{ $summary['total_schedules'] }}</td>
                <td class="label">Total Seats</td><td>{{ $summary['total_seats'] }}</td>
                <td class="label">Booked Seats</td><td>{{ $summary['booked_seats'] }}</td>
            </tr>
            <tr>
                <td class="label">Available Seats</td><td>{{ $summary['available_seats'] }}</td>
                <td class="label">Avg Occupancy</td><td>{{ $summary['avg_occupancy'] }}%</td>
                <td class="label"></td><td></td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>#</th><th>Departure</th><th>Route</th><th>Operator</th>
                <th>Coach #</th><th>Type</th><th>Total Seats</th>
                <th>Booked</th><th>Available</th><th>Occupancy %</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                @php
                    $pctClass = $row['occupancy_pct'] >= 80 ? 'pct-high' : ($row['occupancy_pct'] >= 50 ? 'pct-medium' : 'pct-low');
                @endphp
                <tr>
                    <td>{{ $row['schedule_id'] }}</td>
                    <td>{{ $row['departure'] }}</td>
                    <td>{{ $row['route'] }}</td>
                    <td>{{ $row['operator'] }}</td>
                    <td>{{ $row['coach_number'] }}</td>
                    <td>{{ $row['coach_type'] }}</td>
                    <td>{{ $row['total_seats'] }}</td>
                    <td>{{ $row['booked_seats'] }}</td>
                    <td>{{ $row['available_seats'] }}</td>
                    <td class="{{ $pctClass }}">{{ $row['occupancy_pct'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center;">No schedules found for selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
