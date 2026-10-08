<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #555; margin-bottom: 16px; }
        .summary table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary td { padding: 4px 8px; border: 1px solid #ccc; }
        .summary td.label { background: #f3f4f6; font-weight: bold; width: 20%; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        table.data th { background: #0F766E; color: #fff; font-size: 9px; }
        table.data tr:nth-child(even) { background: #f0fdfa; }
    </style>
</head>
<body>
    <h1>Payment Report</h1>
    <div class="meta">
        <div><strong>Filters:</strong> {{ $filterLabel }}</div>
        <div><strong>Generated:</strong> {{ $generatedAt }}</div>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td class="label">Total Transactions</td><td>{{ $summary['total_transactions'] }}</td>
                <td class="label">Total Amount (BDT)</td><td>{{ number_format($summary['total_amount'], 2) }}</td>
                <td class="label"></td><td></td>
            </tr>
            @foreach($summary['by_method'] as $method => $info)
            <tr>
                <td class="label">{{ $method }}</td>
                <td>{{ $info['count'] }} txn — BDT {{ number_format($info['amount'], 2) }}</td>
                <td></td><td></td><td></td><td></td>
            </tr>
            @endforeach
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>PNR</th><th>Payment Date</th><th>Passenger</th><th>Phone</th>
                <th>Route</th><th>Operator</th><th>Coach</th><th>Amount (BDT)</th>
                <th>Method</th><th>Invoice ID</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['pnr'] }}</td>
                    <td>{{ $row['payment_date'] }}</td>
                    <td>{{ $row['passenger_name'] }}</td>
                    <td>{{ $row['passenger_phone'] }}</td>
                    <td>{{ $row['route'] }}</td>
                    <td>{{ $row['operator'] }}</td>
                    <td>{{ $row['coach_type'] }}</td>
                    <td>{{ number_format($row['amount'], 2) }}</td>
                    <td>{{ $row['payment_method'] }}</td>
                    <td>{{ $row['invoice_id'] }}</td>
                    <td>{{ $row['status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="11" style="text-align:center;">No payment records found for selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
