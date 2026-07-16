import { HEADERS, buildRow, getSummaryCardsHtml, fmtNum } from './reports-helper.js';

/**
 * reports.js
 *
 * Handles all report panels in the admin Reports page.
 * Supported report types (driven by data-report attributes):
 *   selling, booking, revenue, passenger, seat-occupancy,
 *   cancellation, cancel, refund, payment, route-sales, agent-sales
 *
 * Data contract (set in reports.blade.php before this file loads):
 *   window.Reports.routes.<type>.{ preview, excel, pdf }
 *
 * Flow for every report type:
 *   1. User adjusts filter inputs and clicks "Generate Report".
 *   2. getFilters(type)      — reads current filter values into URLSearchParams.
 *   3. generateReport(type)  — fetches preview endpoint and renders results.
 *   4. exportReport(type, fmt) — triggers a file download.
 */

(function () {
    'use strict';

    const reportRoutes = window.Reports.routes;

    // ─── Summary card renderers ───────────────────────────────────────────────

    /**
     * Renders the stat-card grid above the report table.
     * Each report type defines its own 4-card layout.
     * @param {string} type
     * @param {object} summary
     */
    function renderSummary(type, summary) {
        const el = document.getElementById(`${type}-summary`);
        if (!el) return;
        el.innerHTML  = getSummaryCardsHtml(type, summary);
        el.style.display = 'grid';
    }

    // ─── Table renderer ───────────────────────────────────────────────────────

    function renderTable(type, rows) {
        const head    = document.getElementById(`${type}-table-head`);
        const body    = document.getElementById(`${type}-table-body`);
        const headers = HEADERS[type] ?? [];

        if (!head || !body) return;

        head.innerHTML = `<tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>`;

        if (!rows || !rows.length) {
            body.innerHTML = `<tr><td colspan="${headers.length}"
                style="text-align:center;padding:30px;color:var(--text-muted)">
                No records found.</td></tr>`;
            return;
        }

        body.innerHTML = rows.map(r => buildRow(type, r)).join('');
    }

    // ─── Filter helpers ───────────────────────────────────────────────────────

    function getFilters(type) {
        const period = document.querySelector(`.report-period[data-report="${type}"]`)?.value || 'monthly';

        const params = new URLSearchParams({
            period,
            coach_type:     document.querySelector(`.report-coach-type[data-report="${type}"]`)?.value     || 'All',
            payment_method: document.querySelector(`.report-payment-method[data-report="${type}"]`)?.value  || 'All',
            route_id:       document.querySelector(`.report-route-id[data-report="${type}"]`)?.value        || 'All',
            operator:       document.querySelector(`.report-operator[data-report="${type}"]`)?.value        || 'All',
        });

        if (period === 'custom') {
            params.set('from_date', document.querySelector(`.report-from-date[data-report="${type}"]`)?.value || '');
            params.set('to_date',   document.querySelector(`.report-to-date[data-report="${type}"]`)?.value   || '');
        }

        return params;
    }

    function toggleCustomDateRange(type) {
        const period   = document.querySelector(`.report-period[data-report="${type}"]`)?.value;
        const customEl = document.getElementById(`${type}-custom-dates`);
        if (customEl) customEl.style.display = period === 'custom' ? 'grid' : 'none';
    }

    // ─── Core data-fetch ──────────────────────────────────────────────────────

    async function generateReport(type) {
        const params = getFilters(type);
        const btn    = document.querySelector(`.report-generate-btn[data-report="${type}"]`);

        if (params.get('period') === 'custom' && (!params.get('from_date') || !params.get('to_date'))) {
            alert('Please select both From and To dates for custom range.');
            return;
        }

        if (!reportRoutes[type]) {
            alert(`Report type "${type}" is not configured.`);
            return;
        }

        if (btn) { btn.textContent = 'Loading…'; btn.disabled = true; }

        try {
            const res  = await fetch(`${reportRoutes[type].preview}?${params}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();

            if (!res.ok) {
                const msg = data.message
                    || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to load report.');
                alert(msg);
                return;
            }

            // Reveal UI sections
            document.getElementById(`${type}-empty-hint`).style.display   = 'none';
            document.getElementById(`${type}-filter-label`).textContent   = data.filter_label;
            document.getElementById(`${type}-filter-label`).style.display = 'block';
            document.getElementById(`${type}-table-panel`).style.display  = 'block';

            renderSummary(type, data.summary);
            renderTable(type, data.rows);

            document.querySelector(`.report-export-excel-btn[data-report="${type}"]`).disabled = false;
            document.querySelector(`.report-export-pdf-btn[data-report="${type}"]`).disabled   = false;

        } catch {
            alert('Network error while loading report.');
        } finally {
            if (btn) { btn.textContent = 'Generate Report'; btn.disabled = false; }
        }
    }

    function exportReport(type, format) {
        const params = getFilters(type);
        const url    = format === 'excel' ? reportRoutes[type].excel : reportRoutes[type].pdf;
        window.location.href = `${url}?${params}`;
    }



    // ─── Event wiring ─────────────────────────────────────────────────────────

    document.querySelectorAll('.report-period').forEach(el => {
        el.addEventListener('change', () => toggleCustomDateRange(el.dataset.report));
    });

    document.querySelectorAll('.report-generate-btn').forEach(btn => {
        btn.addEventListener('click', () => generateReport(btn.dataset.report));
    });

    document.querySelectorAll('.report-export-excel-btn').forEach(btn => {
        btn.addEventListener('click', () => exportReport(btn.dataset.report, 'excel'));
    });

    document.querySelectorAll('.report-export-pdf-btn').forEach(btn => {
        btn.addEventListener('click', () => exportReport(btn.dataset.report, 'pdf'));
    });

    // ─── Initialisation ───────────────────────────────────────────────────────

    // Pre-fill custom date inputs for all report types
    const today      = new Date();
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);

    Object.keys(reportRoutes).forEach(type => {
        const fromInput = document.querySelector(`.report-from-date[data-report="${type}"]`);
        const toInput   = document.querySelector(`.report-to-date[data-report="${type}"]`);
        if (fromInput) fromInput.value = monthStart.toISOString().split('T')[0];
        if (toInput)   toInput.value   = today.toISOString().split('T')[0];
    });

})();
