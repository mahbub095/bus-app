import { HEADERS, buildRow, getSummaryCardsHtml, fmtNum } from './reports-helper.js';

/**
 * report-detail.js
 *
 * Drives a single standalone report detail page.
 *
 * Data contract (injected by the Blade layout before this file):
 *   window.ReportDetail = {
 *     type: 'selling',            // report type slug
 *     routes: { preview, excel, pdf }
 *   }
 */

(function () {
    'use strict';

    const { type, routes } = window.ReportDetail;

    // ─── Summary cards ────────────────────────────────────────────────────────
    function renderSummary(summary) {
        const el = document.getElementById('rp-summary');
        if (!el) return;
        el.innerHTML     = getSummaryCardsHtml(type, summary);
        el.style.display = 'grid';
    }

    // ─── Table renderer ───────────────────────────────────────────────────────
    function renderTable(rows) {
        const head    = document.getElementById('rp-table-head');
        const body    = document.getElementById('rp-table-body');
        const headers = HEADERS[type] ?? [];

        head.innerHTML = `<tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>`;

        if (!rows || !rows.length) {
            body.innerHTML = `<tr><td colspan="${headers.length}"
                style="text-align:center;padding:30px;color:var(--text-muted)">
                No records found for the selected filters.</td></tr>`;
            return;
        }

        body.innerHTML = rows.map(r => buildRow(type, r)).join('');
    }

    // ─── Filter reading ───────────────────────────────────────────────────────
    function getFilters() {
        const period = document.getElementById('rp-period')?.value || 'monthly';

        const params = new URLSearchParams({
            period,
            coach_type:     document.getElementById('rp-coach-type')?.value     || 'All',
            payment_method: document.getElementById('rp-payment-method')?.value  || 'All',
            route_id:       document.getElementById('rp-route-id')?.value        || 'All',
            operator:       document.getElementById('rp-operator')?.value        || 'All',
        });

        if (period === 'custom') {
            params.set('from_date', document.getElementById('rp-from-date')?.value  || '');
            params.set('to_date',   document.getElementById('rp-to-date')?.value    || '');
        }

        return params;
    }

    // ─── Core fetch ───────────────────────────────────────────────────────────
    async function generateReport() {
        const params = getFilters();
        const btn    = document.getElementById('rp-generate-btn');

        if (params.get('period') === 'custom' && (!params.get('from_date') || !params.get('to_date'))) {
            alert('Please select both From and To dates for custom range.');
            return;
        }

        if (btn) { btn.textContent = '⏳ Loading…'; btn.disabled = true; }

        try {
            const res  = await fetch(`${routes.preview}?${params}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();

            if (!res.ok) {
                const msg = data.message
                    || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to load report.');
                alert(msg);
                return;
            }

            // Show results
            document.getElementById('rp-empty-hint').style.display   = 'none';
            document.getElementById('rp-filter-label').textContent   = data.filter_label;
            document.getElementById('rp-filter-label').style.display = 'block';
            document.getElementById('rp-table-panel').style.display  = 'block';

            renderSummary(data.summary);
            renderTable(data.rows);

            document.getElementById('rp-excel-btn').disabled = false;
            document.getElementById('rp-pdf-btn').disabled   = false;

        } catch {
            alert('Network error while loading report.');
        } finally {
            if (btn) { btn.textContent = '⚡ Generate Report'; btn.disabled = false; }
        }
    }

    function exportReport(format) {
        const params = getFilters();
        const url    = format === 'excel' ? routes.excel : routes.pdf;
        window.location.href = `${url}?${params}`;
    }



    // ─── Event wiring ─────────────────────────────────────────────────────────
    document.getElementById('rp-period')?.addEventListener('change', () => {
        const period   = document.getElementById('rp-period')?.value;
        const customEl = document.getElementById('rp-custom-dates');
        if (customEl) customEl.style.display = period === 'custom' ? 'grid' : 'none';
    });

    document.getElementById('rp-generate-btn')?.addEventListener('click', generateReport);
    document.getElementById('rp-excel-btn')?.addEventListener('click',    () => exportReport('excel'));
    document.getElementById('rp-pdf-btn')?.addEventListener('click',      () => exportReport('pdf'));

    // ─── Pre-fill custom date inputs ──────────────────────────────────────────
    const today      = new Date();
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);

    const fi = document.getElementById('rp-from-date');
    const ti = document.getElementById('rp-to-date');
    if (fi) fi.value = monthStart.toISOString().split('T')[0];
    if (ti) ti.value = today.toISOString().split('T')[0];

})();
