<div class="admin-panel" style="grid-column: 1 / -1;">
    <h3 class="admin-panel-title">
        Pending Cancellation Requests
        <span id="cancel-requests-live-text" class="live-status" style="font-size: 11px;">
            <span class="live-dot"></span>
            Live disabled
        </span>
    </h3>

    {{-- Date filter bar --}}
    <div class="log-filter-bar" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin-bottom:16px; padding:12px 16px; background:rgba(99,102,241,0.05); border-radius:var(--border-radius-sm);">
        <div class="input-group" style="flex:0 0 auto; min-width:170px;">
            <label style="font-size:12px; font-weight:600; margin-bottom:4px;">Date Filter</label>
            <select id="cancel-period" class="coupon-input" style="height:38px;">
                <option value="all">All Records</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="this_year">This Year</option>
                <option value="custom">Custom Date Range</option>
            </select>
        </div>
        <div id="cancel-custom-dates" style="display:none; gap:12px; align-items:flex-end;">
            <div class="input-group" style="flex:0 0 auto; min-width:150px;">
                <label style="font-size:12px; font-weight:600; margin-bottom:4px;">From Date</label>
                <input type="date" id="cancel-from-date" class="coupon-input" style="height:38px;">
            </div>
            <div class="input-group" style="flex:0 0 auto; min-width:150px;">
                <label style="font-size:12px; font-weight:600; margin-bottom:4px;">To Date</label>
                <input type="date" id="cancel-to-date" class="coupon-input" style="height:38px;">
            </div>
        </div>
        <button type="button" id="cancel-apply-filter" class="btn btn-primary" style="height:38px; padding:0 18px;">Apply Filter</button>
    </div>

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
@vite('resources/js/admin/cancel-requests.js')

