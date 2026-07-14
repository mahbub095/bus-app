<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Refund Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #555; margin-bottom: 16px; }
        .summary table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary td { padding: 4px 8px; border: 1px solid #ccc; }
        .summary td.label { background: #f3f4f6; font-weight: bold; width: 20%; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        table.data th { background: #9333EA; color: #fff; font-size: 9px; }
        table.data tr:nth-child(even) { background: #fdf4ff; }
    </style>
</head>
<body>
    <h1>Refund Report</h1>
    <div class="meta">
        <div><strong>Filters:</strong> {{ $filterLabel }}</div>
        <div><strong>Generated:</strong> {{ $generatedAt }}</div>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td class="label">Total Refunds</td><td>{{ $summary['total_refunds'] }}</td>
                <td class="label">Total Refund Amount (BDT)</td><td>{{ number_format($summary['total_refund_amount'], 2) }}</td>
                <td class="label">Total Seats</td><td>{{ $summary['total_seats'] }}</td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>PNR</th><th>Cancel Date</th><th>Booking Date</th><th>Passenger</th>
                <th>Phone</th><th>Route</th><th>Operator</th><th>Coach</th>
                <th>Seats</th><th>Refund (BDT)</th><th>Method</th><th>Invoice ID</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['pnr'] }}</td>
                    <td>{{ $row['cancel_date'] }}</td>
                    <td>{{ $row['booking_date'] }}</td>
                    <td>{{ $row['passenger_name'] }}</td>
                    <td>{{ $row['passenger_phone'] }}</td>
                    <td>{{ $row['route'] }}</td>
                    <td>{{ $row['operator'] }}</td>
                    <td>{{ $row['coach_type'] }}</td>
                    <td>{{ $row['seats'] }}</td>
                    <td>{{ number_format($row['refund_amount'], 2) }}</td>
                    <td>{{ $row['payment_method'] }}</td>
                    <td>{{ $row['invoice_id'] }}</td>
                </tr>
            @empty
                <tr><td colspan="12" style="text-align:center;">No refund records found for selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
