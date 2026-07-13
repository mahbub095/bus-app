<div class="admin-panel" style="grid-column: 1 / -1;">
    <h3 class="admin-panel-title">
        Pending Cancellation Requests
        <span id="cancel-requests-live-text" class="live-status" style="font-size: 11px;">
            <span class="live-dot"></span>
            Live disabled
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
            <tbody id="cancel-requests-log-body">
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">
                        Open this tab to load cancellation requests…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    window.CancelRequests = {
        logsUrl: @json(route('admin.cancel-requests.logs.api')),
        approveCancelRouteTemplate: @json(route('admin.bookings.approve-cancel', ['id' => '__ID__'])),
    };
</script>
<script src="{{ asset('js/admin/cancel-requests.js') }}"></script>

