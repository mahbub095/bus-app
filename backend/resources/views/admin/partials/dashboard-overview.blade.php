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
(function () {
    const analyticsUrl = @json(route('admin.dashboard.analytics'));
    const themeColors = {
        primary: '#6366F1',
        accent: '#A855F7',
        success: '#10B981',
        danger: '#EF4444',
        warning: '#F59E0B',
        gold: '#F59E0B',
        muted: '#6B7280',
        grid: 'rgba(255, 255, 255, 0.06)',
        text: '#9CA3AF',
    };

    const palette = [
        themeColors.primary,
        themeColors.accent,
        themeColors.success,
        themeColors.warning,
        themeColors.danger,
        themeColors.gold,
        '#818CF8',
        '#34D399',
    ];

    let currentPeriod = @json($analytics['period'] ?? 'this_month');
    let chartInstances = {};
    let initialData = @json($analytics);

    const filterRoot = document.getElementById('dashboard-filter');
    const filterBtn = document.getElementById('dashboard-filter-btn');
    const filterMenu = document.getElementById('dashboard-filter-menu');

    function formatNumber(value) {
        return Number(value || 0).toLocaleString();
    }

    function updateMetrics(metrics) {
        document.getElementById('metric-sales-revenue').textContent = 'BDT ' + formatNumber(metrics.sales_revenue);
        document.getElementById('metric-confirmed-bookings').textContent = formatNumber(metrics.confirmed_bookings) + ' Tickets';
        document.getElementById('metric-pending-bookings').textContent = formatNumber(metrics.pending_bookings) + ' Pending';
        document.getElementById('metric-cancelled-bookings').textContent = formatNumber(metrics.cancelled_bookings) + ' Cancelled';
        document.getElementById('metric-cancel-requests').textContent = formatNumber(metrics.cancel_requests) + ' Requests';
        document.getElementById('metric-avg-fare').textContent = 'BDT ' + formatNumber(metrics.avg_fare);
    }

    function baseChartOptions(extra = {}) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: themeColors.text, boxWidth: 12, padding: 14 },
                },
            },
            scales: extra.scales || {},
            ...extra,
        };
    }

    function destroyCharts() {
        Object.values(chartInstances).forEach((chart) => chart.destroy());
        chartInstances = {};
    }

    function renderCharts(data) {
        destroyCharts();
        const charts = data.charts || {};

        chartInstances.status = new Chart(document.getElementById('chart-booking-status'), {
            type: 'doughnut',
            data: {
                labels: charts.booking_status?.labels || [],
                datasets: [{
                    data: charts.booking_status?.data || [],
                    backgroundColor: palette,
                    borderColor: '#141424',
                    borderWidth: 2,
                }],
            },
            options: baseChartOptions(),
        });

        chartInstances.payment = new Chart(document.getElementById('chart-payment-methods'), {
            type: 'pie',
            data: {
                labels: charts.payment_methods?.labels || [],
                datasets: [{
                    data: charts.payment_methods?.data || [],
                    backgroundColor: palette,
                    borderColor: '#141424',
                    borderWidth: 2,
                }],
            },
            options: baseChartOptions(),
        });

        chartInstances.revenue = new Chart(document.getElementById('chart-revenue-trend'), {
            type: 'bar',
            data: {
                labels: charts.revenue_trend?.labels || [],
                datasets: [{
                    label: 'Revenue (BDT)',
                    data: charts.revenue_trend?.data || [],
                    backgroundColor: 'rgba(99, 102, 241, 0.75)',
                    borderColor: themeColors.primary,
                    borderWidth: 1,
                    borderRadius: 6,
                }],
            },
            options: baseChartOptions({
                scales: {
                    x: { ticks: { color: themeColors.text }, grid: { color: themeColors.grid } },
                    y: { ticks: { color: themeColors.text }, grid: { color: themeColors.grid }, beginAtZero: true },
                },
            }),
        });

        chartInstances.bookings = new Chart(document.getElementById('chart-bookings-trend'), {
            type: 'line',
            data: {
                labels: charts.bookings_trend?.labels || [],
                datasets: [{
                    label: 'Bookings',
                    data: charts.bookings_trend?.data || [],
                    borderColor: themeColors.success,
                    backgroundColor: 'rgba(16, 185, 129, 0.15)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: themeColors.success,
                }],
            },
            options: baseChartOptions({
                scales: {
                    x: { ticks: { color: themeColors.text }, grid: { color: themeColors.grid } },
                    y: { ticks: { color: themeColors.text, stepSize: 1 }, grid: { color: themeColors.grid }, beginAtZero: true },
                },
            }),
        });

        chartInstances.coach = new Chart(document.getElementById('chart-coach-types'), {
            type: 'doughnut',
            data: {
                labels: charts.coach_types?.labels || [],
                datasets: [{
                    data: charts.coach_types?.data || [],
                    backgroundColor: [themeColors.primary, themeColors.accent, themeColors.muted],
                    borderColor: '#141424',
                    borderWidth: 2,
                }],
            },
            options: baseChartOptions(),
        });

        chartInstances.routes = new Chart(document.getElementById('chart-top-routes'), {
            type: 'bar',
            data: {
                labels: charts.top_routes?.labels || [],
                datasets: [{
                    label: 'Bookings',
                    data: charts.top_routes?.data || [],
                    backgroundColor: 'rgba(168, 85, 247, 0.75)',
                    borderColor: themeColors.accent,
                    borderWidth: 1,
                    borderRadius: 6,
                }],
            },
            options: baseChartOptions({
                indexAxis: 'y',
                scales: {
                    x: { ticks: { color: themeColors.text, stepSize: 1 }, grid: { color: themeColors.grid }, beginAtZero: true },
                    y: { ticks: { color: themeColors.text }, grid: { color: themeColors.grid } },
                },
            }),
        });
    }

    function applyAnalytics(data) {
        document.getElementById('dashboard-period-label').textContent = data.period_label;
        updateMetrics(data.metrics);
        renderCharts(data);
    }

    function setActiveFilter(period) {
        document.querySelectorAll('.dashboard-filter-option').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.period === period);
        });
    }

    async function loadAnalytics(period) {
        currentPeriod = period;
        setActiveFilter(period);
        filterBtn.disabled = true;

        try {
            const res = await fetch(`${analyticsUrl}?period=${encodeURIComponent(period)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                return;
            }

            const data = await res.json();
            applyAnalytics(data);
        } catch (err) {
            console.error('Dashboard analytics failed', err);
        } finally {
            filterBtn.disabled = false;
        }
    }

    function closeFilterMenu() {
        filterMenu.hidden = true;
        filterBtn.setAttribute('aria-expanded', 'false');
    }

    function toggleFilterMenu() {
        const isOpen = !filterMenu.hidden;
        filterMenu.hidden = isOpen;
        filterBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    }

    filterBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleFilterMenu();
    });

    document.querySelectorAll('.dashboard-filter-option').forEach((btn) => {
        btn.addEventListener('click', () => {
            const period = btn.dataset.period;
            closeFilterMenu();
            if (period && period !== currentPeriod) {
                loadAnalytics(period);
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!filterRoot?.contains(event.target)) {
            closeFilterMenu();
        }
    });

    applyAnalytics(initialData);
})();
</script>
