/**
 * dashboard-overview.js
 *
 * Dashboard analytics panel — renders six Chart.js charts and six metric cards,
 * all updated when the admin selects a different time period from the filter menu.
 * Charts re-render automatically when the admin toggles the dark/light theme.
 *
 * Data contract (set in dashboard-overview.blade.php before this file loads):
 *   window.DashboardOverview.analyticsUrl  — AJAX endpoint for period-filtered data
 *   window.DashboardOverview.initialPeriod — active period on page load, e.g. 'this_month'
 *   window.DashboardOverview.initialData   — pre-rendered analytics payload (avoids an
 *                                            extra request on first load)
 *
 * Depends on:  Chart.js (loaded via CDN before this script)
 */

(function () {
    const { analyticsUrl, initialPeriod, initialData } = window.DashboardOverview;

    let currentPeriod     = initialPeriod;
    let chartInstances    = {};       // keyed by chart name, e.g. chartInstances.revenue
    let lastAnalyticsData = initialData;

    const filterRoot = document.getElementById('dashboard-filter');
    const filterBtn  = document.getElementById('dashboard-filter-btn');
    const filterMenu = document.getElementById('dashboard-filter-menu');

    // ─── Theme helpers ────────────────────────────────────────────────────────

    /**
     * Read current CSS custom-property values so charts always match the
     * active theme (light or dark) without hard-coding any colours.
     */
    function readThemeColors() {
        const css  = getComputedStyle(document.documentElement);
        const get  = (prop, fallback) => css.getPropertyValue(prop).trim() || fallback;
        return {
            primary: get('--primary',        '#6366F1'),
            accent:  get('--accent',         '#8B5CF6'),
            success: get('--success',        '#10B981'),
            danger:  get('--danger',         '#EF4444'),
            warning: get('--warning',        '#F59E0B'),
            gold:    get('--gold',           '#F59E0B'),
            muted:   get('--text-muted',     '#94A3B8'),
            grid:    get('--chart-grid',     'rgba(15, 23, 42, 0.08)'),
            text:    get('--text-secondary', '#475569'),
            border:  get('--chart-border',   '#FFFFFF'),
        };
    }

    /** Ordered colour palette used by pie/doughnut charts. */
    function getThemePalette() {
        const c = readThemeColors();
        return [c.primary, c.accent, c.success, c.warning, c.danger, c.gold, '#818CF8', '#34D399'];
    }

    // ─── Metrics ──────────────────────────────────────────────────────────────

    function formatNumber(value) {
        return Number(value || 0).toLocaleString();
    }

    /** Update the six stat card values without touching their surrounding markup. */
    function updateMetrics(metrics) {
        document.getElementById('metric-sales-revenue').textContent      = `BDT ${formatNumber(metrics.sales_revenue)}`;
        document.getElementById('metric-confirmed-bookings').textContent = `${formatNumber(metrics.confirmed_bookings)} Tickets`;
        document.getElementById('metric-pending-bookings').textContent   = `${formatNumber(metrics.pending_bookings)} Pending`;
        document.getElementById('metric-cancelled-bookings').textContent = `${formatNumber(metrics.cancelled_bookings)} Cancelled`;
        document.getElementById('metric-cancel-requests').textContent    = `${formatNumber(metrics.cancel_requests)} Requests`;
        document.getElementById('metric-avg-fare').textContent           = `BDT ${formatNumber(metrics.avg_fare)}`;
    }

    // ─── Charts ───────────────────────────────────────────────────────────────

    /**
     * Build a base Chart.js options object, optionally merged with extras.
     * Reads theme colours so the axes and legend always match the active theme.
     */
    function baseChartOptions(extra = {}) {
        const c = readThemeColors();
        return {
            responsive:           true,
            maintainAspectRatio:  false,
            plugins: {
                legend: { labels: { color: c.text, boxWidth: 12, padding: 14 } },
            },
            scales: extra.scales || {},
            ...extra,
        };
    }

    /** Destroy all existing Chart.js instances before re-rendering. */
    function destroyAllCharts() {
        Object.values(chartInstances).forEach(chart => chart.destroy());
        chartInstances = {};
    }

    /** Render all six charts from the provided analytics data payload. */
    function renderCharts(data) {
        destroyAllCharts();

        const charts  = data.charts || {};
        const c       = readThemeColors();
        const palette = getThemePalette();

        // Helper for x/y axis config used by bar and line charts
        const axisConfig = (extra = {}) => ({
            x: { ticks: { color: c.text }, grid: { color: c.grid }, ...extra.x },
            y: { ticks: { color: c.text }, grid: { color: c.grid }, beginAtZero: true, ...extra.y },
        });

        chartInstances.status = new Chart(document.getElementById('chart-booking-status'), {
            type: 'doughnut',
            data: {
                labels:   charts.booking_status?.labels || [],
                datasets: [{ data: charts.booking_status?.data || [], backgroundColor: palette, borderColor: c.border, borderWidth: 2 }],
            },
            options: baseChartOptions(),
        });

        chartInstances.payment = new Chart(document.getElementById('chart-payment-methods'), {
            type: 'pie',
            data: {
                labels:   charts.payment_methods?.labels || [],
                datasets: [{ data: charts.payment_methods?.data || [], backgroundColor: palette, borderColor: c.border, borderWidth: 2 }],
            },
            options: baseChartOptions(),
        });

        chartInstances.revenue = new Chart(document.getElementById('chart-revenue-trend'), {
            type: 'bar',
            data: {
                labels:   charts.revenue_trend?.labels || [],
                datasets: [{
                    label:           'Revenue (BDT)',
                    data:            charts.revenue_trend?.data || [],
                    backgroundColor: 'rgba(99, 102, 241, 0.75)',
                    borderColor:     c.primary,
                    borderWidth:     1,
                    borderRadius:    6,
                }],
            },
            options: baseChartOptions({ scales: axisConfig() }),
        });

        chartInstances.bookings = new Chart(document.getElementById('chart-bookings-trend'), {
            type: 'line',
            data: {
                labels:   charts.bookings_trend?.labels || [],
                datasets: [{
                    label:                'Bookings',
                    data:                 charts.bookings_trend?.data || [],
                    borderColor:          c.success,
                    backgroundColor:      'rgba(16, 185, 129, 0.15)',
                    fill:                 true,
                    tension:              0.35,
                    pointBackgroundColor: c.success,
                }],
            },
            options: baseChartOptions({ scales: axisConfig({ y: { stepSize: 1 } }) }),
        });

        chartInstances.coach = new Chart(document.getElementById('chart-coach-types'), {
            type: 'doughnut',
            data: {
                labels:   charts.coach_types?.labels || [],
                datasets: [{
                    data:            charts.coach_types?.data || [],
                    backgroundColor: [c.primary, c.accent, c.muted],
                    borderColor:     c.border,
                    borderWidth:     2,
                }],
            },
            options: baseChartOptions(),
        });

        chartInstances.routes = new Chart(document.getElementById('chart-top-routes'), {
            type: 'bar',
            data: {
                labels:   charts.top_routes?.labels || [],
                datasets: [{
                    label:           'Bookings',
                    data:            charts.top_routes?.data || [],
                    backgroundColor: 'rgba(168, 85, 247, 0.75)',
                    borderColor:     c.accent,
                    borderWidth:     1,
                    borderRadius:    6,
                }],
            },
            // Horizontal bar — flip the indexAxis
            options: baseChartOptions({
                indexAxis: 'y',
                scales:    axisConfig({ x: { stepSize: 1 } }),
            }),
        });
    }

    // ─── Analytics loading ────────────────────────────────────────────────────

    /**
     * Apply a full analytics response to the page: update the period label,
     * metric cards, and all charts.
     */
    function applyAnalytics(data) {
        lastAnalyticsData = data;
        document.getElementById('dashboard-period-label').textContent = data.period_label;
        updateMetrics(data.metrics);
        renderCharts(data);
    }

    /** Highlight the active filter menu option. */
    function setActiveFilterOption(period) {
        document.querySelectorAll('.dashboard-filter-option').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.period === period);
        });
    }

    /** Fetch analytics for a new period and re-render everything. */
    async function loadAnalytics(period) {
        currentPeriod      = period;
        filterBtn.disabled = true;
        setActiveFilterOption(period);

        try {
            const res = await fetch(`${analyticsUrl}?period=${encodeURIComponent(period)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            applyAnalytics(await res.json());
        } catch (err) {
            console.error('Dashboard analytics request failed:', err);
        } finally {
            filterBtn.disabled = false;
        }
    }

    // ─── Filter menu ──────────────────────────────────────────────────────────

    function openFilterMenu() {
        filterMenu.hidden = false;
        filterBtn.setAttribute('aria-expanded', 'true');
    }

    function closeFilterMenu() {
        filterMenu.hidden = true;
        filterBtn.setAttribute('aria-expanded', 'false');
    }

    filterBtn?.addEventListener('click', e => {
        e.stopPropagation();
        filterMenu.hidden ? openFilterMenu() : closeFilterMenu();
    });

    document.querySelectorAll('.dashboard-filter-option').forEach(btn => {
        btn.addEventListener('click', () => {
            const period = btn.dataset.period;
            closeFilterMenu();
            if (period && period !== currentPeriod) loadAnalytics(period);
        });
    });

    // Close the dropdown when clicking anywhere outside it
    document.addEventListener('click', e => {
        if (!filterRoot?.contains(e.target)) closeFilterMenu();
    });

    // Re-render charts whenever the admin switches between light and dark themes
    window.addEventListener('admin-theme-change', () => {
        if (lastAnalyticsData) renderCharts(lastAnalyticsData);
    });

    // ─── Initialisation ───────────────────────────────────────────────────────

    applyAnalytics(initialData);
})();
