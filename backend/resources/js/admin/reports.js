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

    // ─── Column header definitions ────────────────────────────────────────────

    const HEADERS = {
        selling:        ['PNR', 'Sold Date',   'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'],
        booking:        ['PNR', 'Booking Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment', 'Status'],
        revenue:        ['PNR', 'Sold Date',   'Route', 'Operator', 'Coach Type', 'Seat Count', 'Fare (BDT)', 'Payment Method'],
        passenger:      ['PNR', 'Travel Date', 'Passenger', 'Phone', 'Gender', 'Route', 'Boarding', 'Dropping', 'Seats', 'Fare (BDT)'],
        'seat-occupancy': ['Schedule #', 'Departure', 'Route', 'Operator', 'Coach #', 'Type', 'Total Seats', 'Booked', 'Available', 'Occupancy %'],
        cancellation:   ['PNR', 'Cancel Date', 'Booked Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'],
        cancel:         ['PNR', 'Cancel Date', 'Booked Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'],
        refund:         ['PNR', 'Cancel Date', 'Booking Date', 'Passenger', 'Phone', 'Route', 'Operator', 'Coach Type', 'Seats', 'Refund (BDT)', 'Method', 'Invoice ID'],
        payment:        ['PNR', 'Payment Date', 'Passenger', 'Phone', 'Route', 'Operator', 'Coach Type', 'Amount (BDT)', 'Method', 'Invoice ID', 'Status'],
        'route-sales':  ['Route', 'Total Bookings', 'Total Seats', 'Revenue (BDT)', 'AC Bookings', 'Non AC Bookings'],
        'agent-sales':  ['Operator / Agent', 'Routes Covered', 'Total Bookings', 'Total Seats', 'Revenue (BDT)', 'AC Bookings', 'Non AC Bookings'],
    };

    // ─── Row renderers ────────────────────────────────────────────────────────

    /**
     * Returns an HTML <tr> string for a given report type and data row.
     * @param {string} type
     * @param {object} row
     */
    function buildRow(type, row) {
        switch (type) {
            case 'selling':
                return `<tr>
                    <td style="font-weight:bold;color:var(--primary)">${row.pnr}</td>
                    <td>${row.sold_date}</td>
                    <td>${row.passenger_name}</td>
                    <td>${row.passenger_phone}</td>
                    <td>${row.route}</td>
                    <td>${row.departure}</td>
                    <td>${row.operator}</td>
                    <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                    <td style="font-weight:bold">${row.seats}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.fare)}</td>
                    <td>${row.payment_method}</td>
                </tr>`;

            case 'booking':
                return `<tr>
                    <td style="font-weight:bold;color:var(--primary)">${row.pnr}</td>
                    <td>${row.booking_date}</td>
                    <td>${row.passenger_name}</td>
                    <td>${row.passenger_phone}</td>
                    <td>${row.route}</td>
                    <td>${row.departure}</td>
                    <td>${row.operator}</td>
                    <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                    <td style="font-weight:bold">${row.seats}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.fare)}</td>
                    <td>${row.payment_method}</td>
                    <td><span class="status-badge status-${(row.status || '').toLowerCase()}">${row.status}</span></td>
                </tr>`;

            case 'revenue':
                return `<tr>
                    <td style="font-weight:bold;color:var(--primary)">${row.pnr}</td>
                    <td>${row.sold_date}</td>
                    <td>${row.route}</td>
                    <td>${row.operator}</td>
                    <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                    <td>${row.seat_count}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.fare)}</td>
                    <td>${row.payment_method}</td>
                </tr>`;

            case 'passenger':
                return `<tr>
                    <td style="font-weight:bold;color:var(--primary)">${row.pnr}</td>
                    <td>${row.travel_date}</td>
                    <td>${row.passenger_name}</td>
                    <td>${row.passenger_phone}</td>
                    <td>${row.gender}</td>
                    <td>${row.route}</td>
                    <td>${row.boarding_point}</td>
                    <td>${row.dropping_point}</td>
                    <td style="font-weight:bold">${row.seats}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.fare)}</td>
                </tr>`;

            case 'seat-occupancy': {
                const pct     = parseFloat(row.occupancy_pct);
                const pctColor = pct >= 80 ? 'var(--danger)' : pct >= 50 ? 'var(--gold)' : 'var(--success)';
                return `<tr>
                    <td style="font-weight:bold">${row.schedule_id}</td>
                    <td>${row.departure}</td>
                    <td>${row.route}</td>
                    <td>${row.operator}</td>
                    <td>${row.coach_number}</td>
                    <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                    <td>${row.total_seats}</td>
                    <td style="font-weight:bold">${row.booked_seats}</td>
                    <td>${row.available_seats}</td>
                    <td style="font-weight:bold;color:${pctColor}">${row.occupancy_pct}%</td>
                </tr>`;
            }

            case 'cancellation':
            case 'cancel':
                return `<tr>
                    <td style="font-weight:bold;color:var(--primary)">${row.pnr}</td>
                    <td style="color:var(--danger)">${row.cancel_date}</td>
                    <td>${row.booked_date}</td>
                    <td>${row.passenger_name}</td>
                    <td>${row.passenger_phone}</td>
                    <td>${row.route}</td>
                    <td>${row.departure}</td>
                    <td>${row.operator}</td>
                    <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                    <td style="font-weight:bold">${row.seats}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.fare)}</td>
                    <td>${row.payment_method}</td>
                </tr>`;

            case 'refund':
                return `<tr>
                    <td style="font-weight:bold;color:var(--primary)">${row.pnr}</td>
                    <td style="color:var(--danger)">${row.cancel_date}</td>
                    <td>${row.booking_date}</td>
                    <td>${row.passenger_name}</td>
                    <td>${row.passenger_phone}</td>
                    <td>${row.route}</td>
                    <td>${row.operator}</td>
                    <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                    <td style="font-weight:bold">${row.seats}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.refund_amount)}</td>
                    <td>${row.payment_method}</td>
                    <td style="font-size:11px;color:var(--text-muted)">${row.invoice_id}</td>
                </tr>`;

            case 'payment':
                return `<tr>
                    <td style="font-weight:bold;color:var(--primary)">${row.pnr}</td>
                    <td>${row.payment_date}</td>
                    <td>${row.passenger_name}</td>
                    <td>${row.passenger_phone}</td>
                    <td>${row.route}</td>
                    <td>${row.operator}</td>
                    <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.amount)}</td>
                    <td>${row.payment_method}</td>
                    <td style="font-size:11px;color:var(--text-muted)">${row.invoice_id}</td>
                    <td>${row.status}</td>
                </tr>`;

            case 'route-sales':
                return `<tr>
                    <td style="font-weight:bold">${row.route}</td>
                    <td>${row.total_bookings}</td>
                    <td>${row.total_seats}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.total_revenue)}</td>
                    <td>${row.ac_bookings}</td>
                    <td>${row.non_ac_bookings}</td>
                </tr>`;

            case 'agent-sales':
                return `<tr>
                    <td style="font-weight:bold">${row.operator}</td>
                    <td style="font-size:11px;color:var(--text-muted)">${row.routes_covered}</td>
                    <td>${row.total_bookings}</td>
                    <td>${row.total_seats}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.total_revenue)}</td>
                    <td>${row.ac_bookings}</td>
                    <td>${row.non_ac_bookings}</td>
                </tr>`;

            default:
                return '';
        }
    }

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

        let cards = '';

        switch (type) {
            case 'selling':
            case 'booking':
            case 'passenger':
                cards = `
                    ${card('#',   'var(--success)',  summary.total_tickets ?? 0,              type === 'passenger' ? 'Total Passengers' : 'Total Bookings')}
                    ${card('💺', 'var(--primary)',   summary.total_seats ?? 0,                'Total Seats')}
                    ${card('৳',   'var(--gold)',      'BDT ' + fmtNum(summary.total_fare ?? 0), 'Total Fare')}
                    ${card('AC', '#818CF8',           (summary.ac_tickets ?? 0) + ' / ' + (summary.non_ac_tickets ?? 0), 'AC / Non AC')}`;
                break;

            case 'revenue':
                cards = `
                    ${card('৳',   'var(--gold)',      'BDT ' + fmtNum(summary.total_revenue ?? 0), 'Total Revenue')}
                    ${card('AC', '#818CF8',           'BDT ' + fmtNum(summary.ac_revenue ?? 0),    'AC Revenue')}
                    ${card('NA', 'var(--text-muted)', 'BDT ' + fmtNum(summary.non_ac_revenue ?? 0),'Non AC Revenue')}
                    ${card('#',   'var(--success)',   summary.total_tickets ?? 0,                   'Total Tickets')}`;
                break;

            case 'seat-occupancy':
                cards = `
                    ${card('🗓', 'var(--primary)',   summary.total_schedules ?? 0,              'Total Schedules')}
                    ${card('💺', 'var(--success)',   summary.booked_seats ?? 0,                 'Booked Seats')}
                    ${card('🆓', 'var(--gold)',       summary.available_seats ?? 0,              'Available Seats')}
                    ${card('%',  '#818CF8',           (summary.avg_occupancy ?? 0) + '%',        'Avg Occupancy')}`;
                break;

            case 'cancellation':
            case 'cancel':
                cards = `
                    ${card('#',   'var(--danger)',   summary.total_tickets ?? 0,              'Cancelled Tickets')}
                    ${card('💺', 'var(--primary)',   summary.total_seats ?? 0,                'Seats Released')}
                    ${card('৳',   'var(--gold)',      'BDT ' + fmtNum(summary.total_fare ?? 0), 'Cancelled Fare')}
                    ${card('AC', '#818CF8',           (summary.ac_tickets ?? 0) + ' / ' + (summary.non_ac_tickets ?? 0), 'AC / Non AC')}`;
                break;

            case 'refund':
                cards = `
                    ${card('#',   'var(--danger)',   summary.total_refunds ?? 0,                       'Total Refunds')}
                    ${card('৳',   'var(--gold)',      'BDT ' + fmtNum(summary.total_refund_amount ?? 0), 'Total Refund Amount')}
                    ${card('💺', 'var(--primary)',   summary.total_seats ?? 0,                         'Seats Refunded')}
                    ${card('💳', '#818CF8',           '',                                               'Online Payments Only')}`;
                break;

            case 'payment': {
                const methodList = Object.entries(summary.by_method ?? {})
                    .map(([m, d]) => `${m}: ${d.count} (BDT ${fmtNum(d.amount)})`)
                    .join(' | ') || '-';
                cards = `
                    ${card('#',   'var(--primary)',   summary.total_transactions ?? 0,                'Total Transactions')}
                    ${card('৳',   'var(--gold)',       'BDT ' + fmtNum(summary.total_amount ?? 0),    'Total Amount')}
                    ${card('💳', '#818CF8',            methodList,                                     'By Method')}
                    ${card('✓',   'var(--success)',    '',                                             '')}`;
                break;
            }

            case 'route-sales':
                cards = `
                    ${card('🛣',  'var(--primary)',  summary.total_routes ?? 0,                'Total Routes')}
                    ${card('#',   'var(--success)',  summary.total_bookings ?? 0,              'Total Bookings')}
                    ${card('💺', 'var(--gold)',      summary.total_seats ?? 0,                 'Total Seats')}
                    ${card('৳',   '#818CF8',          'BDT ' + fmtNum(summary.total_revenue ?? 0), 'Total Revenue')}`;
                break;

            case 'agent-sales':
                cards = `
                    ${card('👤',  'var(--primary)',  summary.total_agents ?? 0,                'Total Agents')}
                    ${card('#',   'var(--success)',  summary.total_bookings ?? 0,              'Total Bookings')}
                    ${card('💺', 'var(--gold)',      summary.total_seats ?? 0,                 'Total Seats')}
                    ${card('৳',   '#818CF8',          'BDT ' + fmtNum(summary.total_revenue ?? 0), 'Total Revenue')}`;
                break;

            default:
                cards = `
                    ${card('#',   'var(--success)',  summary.total_tickets ?? 0,  'Total Tickets')}
                    ${card('💺', 'var(--primary)',   summary.total_seats ?? 0,    'Total Seats')}
                    ${card('৳',   'var(--gold)',      fmtNum(summary.total_fare ?? 0), 'Total Fare (BDT)')}
                    ${card('AC', '#818CF8',           '', '')}`;
        }

        el.innerHTML  = cards;
        el.style.display = 'grid';
    }

    /** Build one stat-card HTML string. */
    function card(icon, color, value, label) {
        return `<div class="stat-card">
            <div class="stat-icon" style="color:${color}">${icon}</div>
            <div class="stat-info">
                <span class="stat-label">${label}</span>
                <span class="stat-value">${value}</span>
            </div>
        </div>`;
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

    // ─── Utility ──────────────────────────────────────────────────────────────

    /** Format a number with thousands separator. */
    function fmtNum(n) { return Number(n).toLocaleString(); }

    /** Format a fare value. */
    function fmt(n)    { return Number(n).toLocaleString(); }

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
