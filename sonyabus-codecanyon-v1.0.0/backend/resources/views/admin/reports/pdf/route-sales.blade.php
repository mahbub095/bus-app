<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Route-wise Sales Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #555; margin-bottom: 16px; }
        .summary table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary td { padding: 4px 8px; border: 1px solid #ccc; }
        .summary td.label { background: #f3f4f6; font-weight: bold; width: 20%; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        table.data th { background: #B45309; color: #fff; font-size: 9px; }
        table.data tr:nth-child(even) { background: #fffbeb; }
    </style>
</head>
<body>
    <h1>Route-wise Sales Report</h1>
    <div class="meta">
        <div><strong>Filters:</strong> {{ $filterLabel }}</div>
        <div><strong>Generated:</strong> {{ $generatedAt }}</div>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td class="label">Total Routes</td><td>{{ $summary['total_routes'] }}</td>
                <td class="label">Total Bookings</td><td>{{ $summary['total_bookings'] }}</td>
                <td class="label">Total Revenue (BDT)</td><td>{{ number_format($summary['total_revenue'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Total Seats Sold</td><td>{{ $summary['total_seats'] }}</td>
                <td class="label"></td><td></td>
                <td class="label"></td><td></td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>Route</th><th>Total Bookings</th><th>Total Seats</th>
                <th>Total Revenue (BDT)</th><th>AC Bookings</th><th>Non AC Bookings</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['route'] }}</td>
                    <td>{{ $row['total_bookings'] }}</td>
                    <td>{{ $row['total_seats'] }}</td>
                    <td>{{ number_format($row['total_revenue'], 2) }}</td>
                    <td>{{ $row['ac_bookings'] }}</td>
                    <td>{{ $row['non_ac_bookings'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;">No route sales data found for selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
