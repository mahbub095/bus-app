/**
 * reports.js
 *
 * Ticket Reports panel — handles generating, rendering, and exporting
 * both the Selling and Cancel report tables.
 *
 * Data contract (set in reports.blade.php before this file loads):
 *   window.Reports.routes.selling.{ preview, excel, pdf }
 *   window.Reports.routes.cancel.{  preview, excel, pdf }
 *
 * Both report types share the same flow:
 *   1. User adjusts filter inputs and clicks "Generate Report".
 *   2. getFilters(type) reads the current filter values into URLSearchParams.
 *   3. generateReport(type) fetches the preview endpoint and renders results.
 *   4. Export buttons call exportReport(type, format) to trigger a file download.
 */

(function () {
    const reportRoutes = window.Reports.routes;

    // Table column headers differ slightly between report types
    const SELLING_HEADERS = ['PNR', 'Sold Date',   'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'];
    const CANCEL_HEADERS  = ['PNR', 'Cancel Date', 'Booked Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'];

    // ─── Filter helpers ───────────────────────────────────────────────────────

    /**
     * Read all filter inputs for a given report type into a URLSearchParams object.
     * @param {'selling'|'cancel'} type
     */
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

    /** Show or hide the custom date range inputs based on the selected period. */
    function toggleCustomDateRange(type) {
        const period   = document.querySelector(`.report-period[data-report="${type}"]`)?.value;
        const customEl = document.getElementById(`${type}-custom-dates`);
        if (customEl) customEl.style.display = period === 'custom' ? 'grid' : 'none';
    }

    // ─── Rendering ────────────────────────────────────────────────────────────

    /**
     * Render the four summary stat cards above the report table.
     * @param {'selling'|'cancel'} type
     * @param {object} summary
     */
    function renderSummary(type, summary) {
        const el = document.getElementById(`${type}-summary`);
        if (!el) return;

        const fareLabel   = type === 'selling' ? 'Total Revenue'   : 'Cancelled Fare';
        const ticketLabel = type === 'selling' ? 'Tickets Sold'    : 'Cancelled Tickets';

        el.innerHTML = `
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--success)">#</div>
                <div class="stat-info">
                    <span class="stat-label">${ticketLabel}</span>
                    <span class="stat-value">${summary.total_tickets}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--primary)">💺</div>
                <div class="stat-info">
                    <span class="stat-label">Total Seats</span>
                    <span class="stat-value">${summary.total_seats}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:var(--gold)">$</div>
                <div class="stat-info">
                    <span class="stat-label">${fareLabel} (BDT)</span>
                    <span class="stat-value">${Number(summary.total_fare).toLocaleString()}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color:#818CF8">AC</div>
                <div class="stat-info">
                    <span class="stat-label">AC / Non AC</span>
                    <span class="stat-value">${summary.ac_tickets} / ${summary.non_ac_tickets}</span>
                </div>
            </div>`;
        el.style.display = 'grid';
    }

    /**
     * Build a single <tr> for the selling report.
     * @param {object} row
     */
    function sellingRowHtml(row) {
        return `
            <tr>
                <td style="font-weight:bold; color:var(--primary)">${row.pnr}</td>
                <td>${row.sold_date}</td>
                <td>${row.passenger_name}</td>
                <td>${row.passenger_phone}</td>
                <td>${row.route}</td>
                <td>${row.departure}</td>
                <td>${row.operator}</td>
                <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                <td style="font-weight:bold">${row.seats}</td>
                <td style="color:var(--gold); font-weight:bold">BDT ${Number(row.fare).toLocaleString()}</td>
                <td>${row.payment_method}</td>
            </tr>`;
    }

    /**
     * Build a single <tr> for the cancel report.
     * @param {object} row
     */
    function cancelRowHtml(row) {
        return `
            <tr>
                <td style="font-weight:bold; color:var(--primary)">${row.pnr}</td>
                <td style="color:var(--danger)">${row.cancel_date}</td>
                <td>${row.booked_date}</td>
                <td>${row.passenger_name}</td>
                <td>${row.passenger_phone}</td>
                <td>${row.route}</td>
                <td>${row.departure}</td>
                <td>${row.operator}</td>
                <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                <td style="font-weight:bold">${row.seats}</td>
                <td style="color:var(--gold); font-weight:bold">BDT ${Number(row.fare).toLocaleString()}</td>
                <td>${row.payment_method}</td>
            </tr>`;
    }

    /**
     * Render the report table header and all data rows.
     * @param {'selling'|'cancel'} type
     * @param {object[]} rows
     */
    function renderTable(type, rows) {
        const head    = document.getElementById(`${type}-table-head`);
        const body    = document.getElementById(`${type}-table-body`);
        const headers = type === 'selling' ? SELLING_HEADERS : CANCEL_HEADERS;

        head.innerHTML = `<tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>`;

        if (!rows.length) {
            body.innerHTML = `
                <tr>
                    <td colspan="${headers.length}"
                        style="text-align:center; padding:30px; color:var(--text-muted)">
                        No records found.
                    </td>
                </tr>`;
            return;
        }

        const rowFn = type === 'selling' ? sellingRowHtml : cancelRowHtml;
        body.innerHTML = rows.map(rowFn).join('');
    }

    // ─── Data fetching ────────────────────────────────────────────────────────

    /** Fetch the preview data and render the summary + table. */
    async function generateReport(type) {
        const params = getFilters(type);
        const btn    = document.querySelector(`.report-generate-btn[data-report="${type}"]`);

        if (params.get('period') === 'custom' && (!params.get('from_date') || !params.get('to_date'))) {
            alert('Please select both From and To dates for custom range.');
            return;
        }

        if (btn) { btn.textContent = 'Loading...'; btn.disabled = true; }

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

            // Show results UI
            document.getElementById(`${type}-empty-hint`).style.display   = 'none';
            document.getElementById(`${type}-filter-label`).textContent   = data.filter_label;
            document.getElementById(`${type}-filter-label`).style.display = 'block';
            document.getElementById(`${type}-table-panel`).style.display  = 'block';

            renderSummary(type, data.summary);
            renderTable(type, data.rows);

            // Enable export buttons now that data is loaded
            document.querySelector(`.report-export-excel-btn[data-report="${type}"]`).disabled = false;
            document.querySelector(`.report-export-pdf-btn[data-report="${type}"]`).disabled   = false;

        } catch {
            alert('Network error while loading report.');
        } finally {
            if (btn) { btn.textContent = 'Generate Report'; btn.disabled = false; }
        }
    }

    /** Trigger a file download by navigating to the export endpoint. */
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

    // Pre-fill the custom date range to the current month as a sensible default
    ['selling', 'cancel'].forEach(type => {
        const today      = new Date();
        const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);

        const fromInput = document.querySelector(`.report-from-date[data-report="${type}"]`);
        const toInput   = document.querySelector(`.report-to-date[data-report="${type}"]`);

        if (fromInput) fromInput.value = monthStart.toISOString().split('T')[0];
        if (toInput)   toInput.value   = today.toISOString().split('T')[0];
    });
})();
