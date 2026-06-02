<div class="admin-panel" style="grid-column: 1 / -1;">
    <h3 class="admin-panel-title">
        Pending Cancellation Requests
        <span style="font-size: 12px; color: var(--text-secondary);">
            Total: {{ $cancelRequests->count() }}
        </span>
    </h3>

    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>PNR</th>
                    <th>Passenger</th>
                    <th>Contact</th>
                    <th>Journey</th>
                    <th>Bus</th>
                    <th>Seats</th>
                    <th>Fare</th>
                    <th>Request Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cancelRequests as $b)
                    <tr>
                        <td style="font-weight: bold; color: var(--primary);">
                            SE{{ str_pad($b->id, 5, '0', STR_PAD_LEFT) }}
                        </td>
                        <td style="font-weight: 600;">{{ $b->passenger_name }}</td>
                        <td>
                            <div>{{ $b->passenger_phone }}</div>
                            <div style="font-size: 11px; color: var(--text-secondary)">{{ $b->passenger_email }}</div>
                        </td>
                        <td>
                            @if($b->schedule && $b->schedule->route)
                                <div>{{ $b->schedule->route->departureStation->name }} ➔ {{ $b->schedule->route->arrivalStation->name }}</div>
                                <div style="font-size: 11px; color: var(--text-secondary)">
                                    {{ $b->schedule->departure_time->format('D, M d, Y @ h:i A') }}
                                </div>
                            @else
                                <span style="color: var(--text-muted)">N/A</span>
                            @endif
                        </td>
                        <td>{{ $b->schedule->bus?->operator_name ?? 'N/A' }}</td>
                        <td style="font-weight: bold;">{{ $b->seat_numbers }}</td>
                        <td style="color: var(--gold); font-weight: bold;">BDT {{ number_format($b->total_fare) }}</td>
                        <td style="font-size: 12px; color: var(--text-secondary);">
                            {{ optional($b->updated_at)->format('D, M d, Y @ h:i A') }}
                        </td>
                        <td>
                            <form action="{{ route('admin.bookings.approve-cancel', $b->id) }}" method="POST" onsubmit="return confirm('Approve this cancellation request?');">
                                @csrf
                                <button class="btn btn-danger btn-sm" type="submit">Approve Cancel</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">
                            No pending cancellation requests.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
