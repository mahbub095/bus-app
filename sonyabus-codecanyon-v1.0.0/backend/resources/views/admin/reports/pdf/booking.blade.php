<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #555; margin-bottom: 16px; }
        .summary table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary td { padding: 4px 8px; border: 1px solid #ccc; }
        .summary td.label { background: #f3f4f6; font-weight: bold; width: 20%; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        table.data th { background: #6366F1; color: #fff; font-size: 9px; }
        table.data tr:nth-child(even) { background: #f9fafb; }
    </style>
</head>
<body>
    <h1>Booking Report</h1>
    <div class="meta">
        <div><strong>Filters:</strong> {{ $filterLabel }}</div>
        <div><strong>Generated:</strong> {{ $generatedAt }}</div>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td class="label">Total Bookings</td><td>{{ $summary['total_tickets'] }}</td>
                <td class="label">Total Seats</td><td>{{ $summary['total_seats'] }}</td>
                <td class="label">Total Fare (BDT)</td><td>{{ number_format($summary['total_fare'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">AC Bookings</td><td>{{ $summary['ac_tickets'] }}</td>
                <td class="label">AC Fare (BDT)</td><td>{{ number_format($summary['ac_fare'], 2) }}</td>
                <td class="label">Non AC Bookings</td><td>{{ $summary['non_ac_tickets'] }}</td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>PNR</th><th>Booking Date</th><th>Passenger</th><th>Phone</th>
                <th>Route</th><th>Departure</th><th>Operator</th><th>Coach</th>
                <th>Seats</th><th>Fare</th><th>Payment</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['pnr'] }}</td>
                    <td>{{ $row['booking_date'] }}</td>
                    <td>{{ $row['passenger_name'] }}</td>
                    <td>{{ $row['passenger_phone'] }}</td>
                    <td>{{ $row['route'] }}</td>
                    <td>{{ $row['departure'] }}</td>
                    <td>{{ $row['operator'] }}</td>
                    <td>{{ $row['coach_type'] }}</td>
                    <td>{{ $row['seats'] }}</td>
                    <td>{{ number_format($row['fare'], 2) }}</td>
                    <td>{{ $row['payment_method'] }}</td>
                    <td>{{ $row['status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="12" style="text-align:center;">No bookings found for selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
