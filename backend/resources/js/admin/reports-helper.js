/**
 * reports-helper.js
 *
 * Consolidate table configurations, row renderers, and stat cards for reports.
 */

// Format a number with thousands separator
export function fmtNum(n) {
    return Number(n || 0).toLocaleString();
}

export const HEADERS = {
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

export function buildRow(type, row) {
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
                <td style="color:var(--gold);font-weight:bold">BDT ${fmtNum(row.fare)}</td>
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
                <td style="color:var(--gold);font-weight:bold">BDT ${fmtNum(row.fare)}</td>
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
                <td style="color:var(--gold);font-weight:bold">BDT ${fmtNum(row.fare)}</td>
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
                <td style="color:var(--gold);font-weight:bold">BDT ${fmtNum(row.fare)}</td>
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
                <td style="color:var(--gold);font-weight:bold">BDT ${fmtNum(row.fare)}</td>
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
                <td style="color:var(--gold);font-weight:bold">BDT ${fmtNum(row.refund_amount)}</td>
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
                <td style="color:var(--gold);font-weight:bold">BDT ${fmtNum(row.amount)}</td>
                <td>${row.payment_method}</td>
                <td style="font-size:11px;color:var(--text-muted)">${row.invoice_id}</td>
                <td>${row.status}</td>
            </tr>`;

        case 'route-sales':
            return `<tr>
                <td style="font-weight:bold">${row.route}</td>
                <td>${row.total_bookings}</td>
                <td>${row.total_seats}</td>
                <td style="color:var(--gold);font-weight:bold">BDT ${fmtNum(row.total_revenue)}</td>
                <td>${row.ac_bookings}</td>
                <td>${row.non_ac_bookings}</td>
            </tr>`;

        case 'agent-sales':
            return `<tr>
                <td style="font-weight:bold">${row.operator}</td>
                <td style="font-size:11px;color:var(--text-muted)">${row.routes_covered}</td>
                <td>${row.total_bookings}</td>
                <td>${row.total_seats}</td>
                <td style="color:var(--gold);font-weight:bold">BDT ${fmtNum(row.total_revenue)}</td>
                <td>${row.ac_bookings}</td>
                <td>${row.non_ac_bookings}</td>
            </tr>`;

        default:
            return '';
    }
}

export function buildCardHtml(icon, color, value, label) {
    return `<div class="stat-card">
        <div class="stat-icon" style="color:${color}">${icon}</div>
        <div class="stat-info">
            <span class="stat-label">${label}</span>
            <span class="stat-value">${value}</span>
        </div>
    </div>`;
}

export function getSummaryCardsHtml(type, summary) {
    switch (type) {
        case 'selling':
        case 'booking':
        case 'passenger':
            return buildCardHtml('#',   'var(--success)',  summary.total_tickets ?? 0,              type === 'passenger' ? 'Total Passengers' : 'Total Bookings')
                 + buildCardHtml('💺', 'var(--primary)',   summary.total_seats ?? 0,                'Total Seats')
                 + buildCardHtml('৳',   'var(--gold)',      'BDT ' + fmtNum(summary.total_fare ?? 0), 'Total Fare')
                 + buildCardHtml('AC', '#818CF8',           (summary.ac_tickets ?? 0) + ' / ' + (summary.non_ac_tickets ?? 0), 'AC / Non AC');

        case 'revenue':
            return buildCardHtml('৳',   'var(--gold)',      'BDT ' + fmtNum(summary.total_revenue ?? 0), 'Total Revenue')
                 + buildCardHtml('AC', '#818CF8',           'BDT ' + fmtNum(summary.ac_revenue ?? 0),    'AC Revenue')
                 + buildCardHtml('NA', 'var(--text-muted)', 'BDT ' + fmtNum(summary.non_ac_revenue ?? 0),'Non AC Revenue')
                 + buildCardHtml('#',   'var(--success)',   summary.total_tickets ?? 0,                   'Total Tickets');

        case 'seat-occupancy':
            return buildCardHtml('🗓', 'var(--primary)',   summary.total_schedules ?? 0,              'Total Schedules')
                 + buildCardHtml('💺', 'var(--success)',   summary.booked_seats ?? 0,                 'Booked Seats')
                 + buildCardHtml('🆓', 'var(--gold)',       summary.available_seats ?? 0,              'Available Seats')
                 + buildCardHtml('%',  '#818CF8',           (summary.avg_occupancy ?? 0) + '%',        'Avg Occupancy');

        case 'cancellation':
        case 'cancel':
            return buildCardHtml('#',   'var(--danger)',   summary.total_tickets ?? 0,              'Cancelled Tickets')
                 + buildCardHtml('💺', 'var(--primary)',   summary.total_seats ?? 0,                'Seats Released')
                 + buildCardHtml('৳',   'var(--gold)',      'BDT ' + fmtNum(summary.total_fare ?? 0), 'Cancelled Fare')
                 + buildCardHtml('AC', '#818CF8',           (summary.ac_tickets ?? 0) + ' / ' + (summary.non_ac_tickets ?? 0), 'AC / Non AC');

        case 'refund':
            return buildCardHtml('#',   'var(--danger)',   summary.total_refunds ?? 0,                       'Total Refunds')
                 + buildCardHtml('৳',   'var(--gold)',      'BDT ' + fmtNum(summary.total_refund_amount ?? 0), 'Total Refund Amount')
                 + buildCardHtml('💺', 'var(--primary)',   summary.total_seats ?? 0,                         'Seats Refunded')
                 + buildCardHtml('💳', '#818CF8',           'Online Payments Only',                           '');

        case 'payment': {
            const methodList = Object.entries(summary.by_method ?? {})
                .map(([m, d]) => `${m}: ${d.count} (BDT ${fmtNum(d.amount)})`)
                .join(' | ') || '-';
            return buildCardHtml('#',   'var(--primary)',   summary.total_transactions ?? 0,                'Total Transactions')
                 + buildCardHtml('৳',   'var(--gold)',       'BDT ' + fmtNum(summary.total_amount ?? 0),    'Total Amount')
                 + buildCardHtml('💳', '#818CF8',            methodList,                                     'By Method')
                 + buildCardHtml('✓',   'var(--success)',    '',                                             '');
        }

        case 'route-sales':
            return buildCardHtml('🛣',  'var(--primary)',  summary.total_routes ?? 0,                'Total Routes')
                 + buildCardHtml('#',   'var(--success)',  summary.total_bookings ?? 0,              'Total Bookings')
                 + buildCardHtml('💺', 'var(--gold)',      summary.total_seats ?? 0,                 'Total Seats')
                 + buildCardHtml('৳',   '#818CF8',          'BDT ' + fmtNum(summary.total_revenue ?? 0), 'Total Revenue');

        case 'agent-sales':
            return buildCardHtml('👤',  'var(--primary)',  summary.total_agents ?? 0,                'Total Agents')
                 + buildCardHtml('#',   'var(--success)',  summary.total_bookings ?? 0,              'Total Bookings')
                 + buildCardHtml('💺', 'var(--gold)',      summary.total_seats ?? 0,                 'Total Seats')
                 + buildCardHtml('৳',   '#818CF8',          'BDT ' + fmtNum(summary.total_revenue ?? 0), 'Total Revenue');

        default:
            return buildCardHtml('#',   'var(--success)',  summary.total_tickets ?? 0,  'Total Tickets')
                 + buildCardHtml('💺', 'var(--primary)',   summary.total_seats ?? 0,    'Total Seats')
                 + buildCardHtml('৳',   'var(--gold)',      fmtNum(summary.total_fare ?? 0), 'Total Fare (BDT)')
                 + buildCardHtml('AC', '#818CF8',           '', '');
    }
}
