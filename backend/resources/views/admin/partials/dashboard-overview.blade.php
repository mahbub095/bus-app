<div class="dashboard-overview" id="dashboard-overview">
    <div class="dashboard-toolbar">
        <p class="dashboard-period-label" id="dashboard-period-label">{{ $analytics['period_label'] }}</p>

        <div class="dashboard-filter" id="dashboard-filter">
            <button type="button" class="dashboard-filter-btn" id="dashboard-filter-btn" aria-expanded="false" aria-haspopup="true">
                <svg class="dashboard-filter-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Filters
            </button>
            <div class="dashboard-filter-menu" id="dashboard-filter-menu" role="menu" hidden>
                <button type="button" class="dashboard-filter-option" data-period="today" role="menuitem">Today</button>
                <button type="button" class="dashboard-filter-option" data-period="last_7_days" role="menuitem">Last 7 Days</button>
                <button type="button" class="dashboard-filter-option is-active" data-period="this_month" role="menuitem">This Month</button>
                <button type="button" class="dashboard-filter-option" data-period="this_year" role="menuitem">This Year</button>
            </div>
        </div>
    </div>

    <section class="admin-stats-grid dashboard-metrics" id="dashboard-metrics">
        <div class="stat-card">
            <div class="stat-icon" style="color: var(--gold)">৳</div>
            <div class="stat-info">
                <span class="stat-label">Sales Revenue</span>
                <span class="stat-value" id="metric-sales-revenue">BDT {{ number_format($analytics['metrics']['sales_revenue']) }}</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="color: var(--success)">✔</div>
            <div class="stat-info">
                <span class="stat-label">Confirmed Bookings</span>
                <span class="stat-value" id="metric-confirmed-bookings">{{ number_format($analytics['metrics']['confirmed_bookings']) }} Tickets</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="color: var(--warning)">⏳</div>
            <div class="stat-info">
                <span class="stat-label">Pending Bookings</span>
                <span class="stat-value" id="metric-pending-bookings">{{ number_format($analytics['metrics']['pending_bookings']) }} Pending</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="color: var(--danger)">🗙</div>
            <div class="stat-info">
                <span class="stat-label">Cancelled Tickets</span>
                <span class="stat-value" id="metric-cancelled-bookings">{{ number_format($analytics['metrics']['cancelled_bookings']) }} Cancelled</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="color: var(--accent)">📝</div>
            <div class="stat-info">
                <span class="stat-label">Cancel Requests</span>
                <span class="stat-value" id="metric-cancel-requests">{{ number_format($analytics['metrics']['cancel_requests']) }} Requests</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="color: var(--primary)">📊</div>
            <div class="stat-info">
                <span class="stat-label">Average Ticket Fare</span>
                <span class="stat-value" id="metric-avg-fare">BDT {{ number_format($analytics['metrics']['avg_fare']) }}</span>
            </div>
        </div>
    </section>

    <section class="dashboard-charts-grid">
        <div class="admin-panel dashboard-chart-panel">
            <h3 class="dashboard-chart-title">Booking Status</h3>
            <div class="dashboard-chart-wrap">
                <canvas id="chart-booking-status" aria-label="Booking status distribution"></canvas>
            </div>
        </div>

        <div class="admin-panel dashboard-chart-panel">
            <h3 class="dashboard-chart-title">Payment Methods</h3>
            <div class="dashboard-chart-wrap">
                <canvas id="chart-payment-methods" aria-label="Payment methods breakdown"></canvas>
            </div>
        </div>

        <div class="admin-panel dashboard-chart-panel">
            <h3 class="dashboard-chart-title">Daily Revenue (BDT)</h3>
            <div class="dashboard-chart-wrap dashboard-chart-wrap-wide">
                <canvas id="chart-revenue-trend" aria-label="Daily revenue trend"></canvas>
            </div>
        </div>

        <div class="admin-panel dashboard-chart-panel">
            <h3 class="dashboard-chart-title">Daily Bookings</h3>
            <div class="dashboard-chart-wrap dashboard-chart-wrap-wide">
                <canvas id="chart-bookings-trend" aria-label="Daily bookings trend"></canvas>
            </div>
        </div>

        <div class="admin-panel dashboard-chart-panel">
            <h3 class="dashboard-chart-title">Coach Type Mix</h3>
            <div class="dashboard-chart-wrap">
                <canvas id="chart-coach-types" aria-label="AC versus Non AC bookings"></canvas>
            </div>
        </div>

        <div class="admin-panel dashboard-chart-panel">
            <h3 class="dashboard-chart-title">Top Routes</h3>
            <div class="dashboard-chart-wrap dashboard-chart-wrap-wide">
                <canvas id="chart-top-routes" aria-label="Top routes by bookings"></canvas>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    window.DashboardOverview = {
        analyticsUrl: @json(route('admin.dashboard.analytics')),
        initialPeriod: @json($analytics['period'] ?? 'this_month'),
        initialData: @json($analytics),
    };
</script>
<script src="{{ asset('js/admin/dashboard-overview.js') }}"></script>
