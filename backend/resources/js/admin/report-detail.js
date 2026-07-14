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

    // ─── Column headers per report type ──────────────────────────────────────
    const HEADERS = {
        selling:           ['PNR', 'Sold Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'],
        booking:           ['PNR', 'Booking Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment', 'Status'],
        revenue:           ['PNR', 'Sold Date', 'Route', 'Operator', 'Coach Type', 'Seat Count', 'Fare (BDT)', 'Payment Method'],
        passenger:         ['PNR', 'Travel Date', 'Passenger', 'Phone', 'Gender', 'Route', 'Boarding', 'Dropping', 'Seats', 'Fare (BDT)'],
        'seat-occupancy':  ['Schedule #', 'Departure', 'Route', 'Operator', 'Coach #', 'Type', 'Total Seats', 'Booked', 'Available', 'Occupancy %'],
        cancellation:      ['PNR', 'Cancel Date', 'Booked Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'],
        cancel:            ['PNR', 'Cancel Date', 'Booked Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'],
        refund:            ['PNR', 'Cancel Date', 'Booking Date', 'Passenger', 'Phone', 'Route', 'Operator', 'Coach Type', 'Seats', 'Refund (BDT)', 'Method', 'Invoice ID'],
        payment:           ['PNR', 'Payment Date', 'Passenger', 'Phone', 'Route', 'Operator', 'Coach Type', 'Amount (BDT)', 'Method', 'Invoice ID', 'Status'],
        'route-sales':     ['Route', 'Total Bookings', 'Total Seats', 'Revenue (BDT)', 'AC Bookings', 'Non AC Bookings'],
        'agent-sales':     ['Operator / Agent', 'Routes Covered', 'Total Bookings', 'Total Seats', 'Revenue (BDT)', 'AC Bookings', 'Non AC Bookings'],
    };

    // ─── Row builders ─────────────────────────────────────────────────────────
    function buildRow(row) {
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
                    <td><strong>${row.seats}</strong></td>
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
                    <td><strong>${row.seats}</strong></td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.fare)}</td>
                    <td>${row.payment_method}</td>
                    <td><span class="coach-tag">${row.status}</span></td>
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
                    <td><strong>${row.seats}</strong></td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.fare)}</td>
                </tr>`;

            case 'seat-occupancy': {
                const pct = parseFloat(row.occupancy_pct);
                const pctColor = pct >= 80 ? 'var(--danger)' : pct >= 50 ? 'var(--gold)' : 'var(--success)';
                return `<tr>
                    <td><strong>${row.schedule_id}</strong></td>
                    <td>${row.departure}</td>
                    <td>${row.route}</td>
                    <td>${row.operator}</td>
                    <td>${row.coach_number}</td>
                    <td><span class="coach-tag ${row.coach_type === 'AC' ? 'ac' : ''}">${row.coach_type}</span></td>
                    <td>${row.total_seats}</td>
                    <td><strong>${row.booked_seats}</strong></td>
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
                    <td><strong>${row.seats}</strong></td>
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
                    <td><strong>${row.seats}</strong></td>
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
                    <td><strong>${row.route}</strong></td>
                    <td>${row.total_bookings}</td>
                    <td>${row.total_seats}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.total_revenue)}</td>
                    <td>${row.ac_bookings}</td>
                    <td>${row.non_ac_bookings}</td>
                </tr>`;

            case 'agent-sales':
                return `<tr>
                    <td><strong>${row.operator}</strong></td>
                    <td style="font-size:11px;color:var(--text-muted)">${row.routes_covered}</td>
                    <td>${row.total_bookings}</td>
                    <td>${row.total_seats}</td>
                    <td style="color:var(--gold);font-weight:bold">BDT ${fmt(row.total_revenue)}</td>
                    <td>${row.ac_bookings}</td>
                    <td>${row.non_ac_bookings}</td>
                </tr>`;

            default: return '';
        }
    }

    // ─── Summary cards ────────────────────────────────────────────────────────
    function renderSummary(summary) {
        const el = document.getElementById('rp-summary');
        if (!el) return;

        let cards = '';

        switch (type) {
            case 'selling':
            case 'booking':
            case 'passenger':
                cards = card('#',   'var(--success)',  summary.total_tickets ?? 0,              type === 'passenger' ? 'Total Passengers' : 'Total Bookings')
                      + card('💺', 'var(--primary)',   summary.total_seats ?? 0,                'Total Seats')
                      + card('৳',   'var(--gold)',      'BDT ' + fmtN(summary.total_fare ?? 0), 'Total Fare')
                      + card('AC', '#818CF8',           (summary.ac_tickets ?? 0) + ' / ' + (summary.non_ac_tickets ?? 0), 'AC / Non AC');
                break;
            case 'revenue':
                cards = card('৳',   'var(--gold)',       'BDT ' + fmtN(summary.total_revenue ?? 0),   'Total Revenue')
                      + card('AC', '#818CF8',            'BDT ' + fmtN(summary.ac_revenue ?? 0),       'AC Revenue')
                      + card('NA', 'var(--text-muted)',  'BDT ' + fmtN(summary.non_ac_revenue ?? 0),   'Non AC Revenue')
                      + card('#',   'var(--success)',    summary.total_tickets ?? 0,                    'Total Tickets');
                break;
            case 'seat-occupancy':
                cards = card('🗓', 'var(--primary)',  summary.total_schedules ?? 0,          'Total Schedules')
                      + card('💺', 'var(--success)',  summary.booked_seats ?? 0,             'Booked Seats')
                      + card('🆓', 'var(--gold)',      summary.available_seats ?? 0,          'Available Seats')
                      + card('%',  '#818CF8',          (summary.avg_occupancy ?? 0) + '%',   'Avg Occupancy');
                break;
            case 'cancellation':
            case 'cancel':
                cards = card('#',   'var(--danger)',  summary.total_tickets ?? 0,              'Cancelled Tickets')
                      + card('💺', 'var(--primary)',  summary.total_seats ?? 0,               'Seats Released')
                      + card('৳',   'var(--gold)',     'BDT ' + fmtN(summary.total_fare ?? 0), 'Cancelled Fare')
                      + card('AC', '#818CF8',          (summary.ac_tickets ?? 0) + ' / ' + (summary.non_ac_tickets ?? 0), 'AC / Non AC');
                break;
            case 'refund':
                cards = card('#',   'var(--danger)',  summary.total_refunds ?? 0,                        'Total Refunds')
                      + card('৳',   'var(--gold)',     'BDT ' + fmtN(summary.total_refund_amount ?? 0),  'Total Refund Amount')
                      + card('💺', 'var(--primary)',  summary.total_seats ?? 0,                          'Seats Refunded')
                      + card('💳', '#818CF8',          'Online payments only',                            '');
                break;
            case 'payment': {
                const methodList = Object.entries(summary.by_method ?? {})
                    .map(([m, d]) => `${m}: ${d.count} (BDT ${fmtN(d.amount)})`).join(' | ') || '—';
                cards = card('#',   'var(--primary)', summary.total_transactions ?? 0,              'Total Transactions')
                      + card('৳',   'var(--gold)',     'BDT ' + fmtN(summary.total_amount ?? 0),   'Total Amount')
                      + card('💳', '#818CF8',          methodList,                                   'By Method')
                      + card('✓',   'var(--success)',  '',                                           '');
                break;
            }
            case 'route-sales':
                cards = card('🛣',  'var(--primary)', summary.total_routes ?? 0,                  'Total Routes')
                      + card('#',   'var(--success)', summary.total_bookings ?? 0,                 'Total Bookings')
                      + card('💺', 'var(--gold)',     summary.total_seats ?? 0,                    'Total Seats')
                      + card('৳',   '#818CF8',         'BDT ' + fmtN(summary.total_revenue ?? 0), 'Total Revenue');
                break;
            case 'agent-sales':
                cards = card('👤',  'var(--primary)', summary.total_agents ?? 0,                   'Total Agents')
                      + card('#',   'var(--success)', summary.total_bookings ?? 0,                  'Total Bookings')
                      + card('💺', 'var(--gold)',     summary.total_seats ?? 0,                     'Total Seats')
                      + card('৳',   '#818CF8',         'BDT ' + fmtN(summary.total_revenue ?? 0),  'Total Revenue');
                break;
            default:
                cards = card('#', 'var(--success)', summary.total_tickets ?? 0, 'Total Tickets');
        }

        el.innerHTML     = cards;
        el.style.display = 'grid';
    }

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

        body.innerHTML = rows.map(r => buildRow(r)).join('');
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

    // ─── Utilities ────────────────────────────────────────────────────────────
    function fmt(n)  { return Number(n).toLocaleString(); }
    function fmtN(n) { return Number(n).toLocaleString(); }

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
