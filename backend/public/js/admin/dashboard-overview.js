(function () {
    const analyticsUrl = window.DashboardOverview.analyticsUrl;

    let currentPeriod    = window.DashboardOverview.initialPeriod;
    let chartInstances   = {};
    let initialData      = window.DashboardOverview.initialData;
    let lastAnalyticsData = initialData;

    const filterRoot = document.getElementById('dashboard-filter');
    const filterBtn  = document.getElementById('dashboard-filter-btn');
    const filterMenu = document.getElementById('dashboard-filter-menu');

    function readThemeColors() {
        const styles = getComputedStyle(document.documentElement);
        const pick = (name, fallback) => styles.getPropertyValue(name).trim() || fallback;
        return {
            primary: pick('--primary', '#6366F1'),
            accent:  pick('--accent',  '#8B5CF6'),
            success: pick('--success', '#10B981'),
            danger:  pick('--danger',  '#EF4444'),
            warning: pick('--warning', '#F59E0B'),
            gold:    pick('--gold',    '#F59E0B'),
            muted:   pick('--text-muted',      '#94A3B8'),
            grid:    pick('--chart-grid',      'rgba(15, 23, 42, 0.08)'),
            text:    pick('--text-secondary',  '#475569'),
            border:  pick('--chart-border',    '#FFFFFF'),
        };
    }

    function themePalette() {
        const c = readThemeColors();
        return [c.primary, c.accent, c.success, c.warning, c.danger, c.gold, '#818CF8', '#34D399'];
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString();
    }

    function updateMetrics(metrics) {
        document.getElementById('metric-sales-revenue').textContent      = 'BDT ' + formatNumber(metrics.sales_revenue);
        document.getElementById('metric-confirmed-bookings').textContent = formatNumber(metrics.confirmed_bookings) + ' Tickets';
        document.getElementById('metric-pending-bookings').textContent   = formatNumber(metrics.pending_bookings) + ' Pending';
        document.getElementById('metric-cancelled-bookings').textContent = formatNumber(metrics.cancelled_bookings) + ' Cancelled';
        document.getElementById('metric-cancel-requests').textContent    = formatNumber(metrics.cancel_requests) + ' Requests';
        document.getElementById('metric-avg-fare').textContent           = 'BDT ' + formatNumber(metrics.avg_fare);
    }

    function baseChartOptions(extra = {}) {
        const c = readThemeColors();
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: c.text, boxWidth: 12, padding: 14 } },
            },
            scales: extra.scales || {},
            ...extra,
        };
    }

    function destroyCharts() {
        Object.values(chartInstances).forEach(chart => chart.destroy());
        chartInstances = {};
    }

    function renderCharts(data) {
        destroyCharts();
        const charts  = data.charts || {};
        const c       = readThemeColors();
        const palette = themePalette();

        chartInstances.status = new Chart(document.getElementById('chart-booking-status'), {
            type: 'doughnut',
            data: {
                labels: charts.booking_status?.labels || [],
                datasets: [{ data: charts.booking_status?.data || [], backgroundColor: palette, borderColor: c.border, borderWidth: 2 }],
            },
            options: baseChartOptions(),
        });

        chartInstances.payment = new Chart(document.getElementById('chart-payment-methods'), {
            type: 'pie',
            data: {
                labels: charts.payment_methods?.labels || [],
                datasets: [{ data: charts.payment_methods?.data || [], backgroundColor: palette, borderColor: c.border, borderWidth: 2 }],
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
                    borderColor: c.primary,
                    borderWidth: 1,
                    borderRadius: 6,
                }],
            },
            options: baseChartOptions({
                scales: {
                    x: { ticks: { color: c.text }, grid: { color: c.grid } },
                    y: { ticks: { color: c.text }, grid: { color: c.grid }, beginAtZero: true },
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
                    borderColor: c.success,
                    backgroundColor: 'rgba(16, 185, 129, 0.15)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: c.success,
                }],
            },
            options: baseChartOptions({
                scales: {
                    x: { ticks: { color: c.text }, grid: { color: c.grid } },
                    y: { ticks: { color: c.text, stepSize: 1 }, grid: { color: c.grid }, beginAtZero: true },
                },
            }),
        });

        chartInstances.coach = new Chart(document.getElementById('chart-coach-types'), {
            type: 'doughnut',
            data: {
                labels: charts.coach_types?.labels || [],
                datasets: [{ data: charts.coach_types?.data || [], backgroundColor: [c.primary, c.accent, c.muted], borderColor: c.border, borderWidth: 2 }],
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
                    borderColor: c.accent,
                    borderWidth: 1,
                    borderRadius: 6,
                }],
            },
            options: baseChartOptions({
                indexAxis: 'y',
                scales: {
                    x: { ticks: { color: c.text, stepSize: 1 }, grid: { color: c.grid }, beginAtZero: true },
                    y: { ticks: { color: c.text }, grid: { color: c.grid } },
                },
            }),
        });
    }

    function applyAnalytics(data) {
        lastAnalyticsData = data;
        document.getElementById('dashboard-period-label').textContent = data.period_label;
        updateMetrics(data.metrics);
        renderCharts(data);
    }

    function setActiveFilter(period) {
        document.querySelectorAll('.dashboard-filter-option').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.period === period);
        });
    }

    async function loadAnalytics(period) {
        currentPeriod    = period;
        setActiveFilter(period);
        filterBtn.disabled = true;

        try {
            const res = await fetch(`${analyticsUrl}?period=${encodeURIComponent(period)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
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

    filterBtn?.addEventListener('click', e => {
        e.stopPropagation();
        toggleFilterMenu();
    });

    document.querySelectorAll('.dashboard-filter-option').forEach(btn => {
        btn.addEventListener('click', () => {
            const period = btn.dataset.period;
            closeFilterMenu();
            if (period && period !== currentPeriod) {
                loadAnalytics(period);
            }
        });
    });

    document.addEventListener('click', e => {
        if (!filterRoot?.contains(e.target)) closeFilterMenu();
    });

    window.addEventListener('admin-theme-change', () => {
        if (lastAnalyticsData) renderCharts(lastAnalyticsData);
    });

    applyAnalytics(initialData);
})();
