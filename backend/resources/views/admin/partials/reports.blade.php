<div class="admin-sections-layout" style="grid-column: 1 / -1; display: block;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Ticket Selling Report</h3>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
            View all paid ticket sales with date, coach type, route, and payment filters. Export to Excel or PDF.
        </p>

        @include('admin.partials.report-filters', [
            'reportType' => 'selling',
            'reportTitle' => 'Ticket Selling',
        ])
    </div>

</div>

<div class="admin-sections-layout" style="grid-column: 1 / -1; display: block; margin-top: 30px;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Ticket Cancel Report</h3>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
            View all cancelled tickets with the same filters. Export cancellation logs to Excel or PDF.
        </p>

        @include('admin.partials.report-filters', [
            'reportType' => 'cancel',
            'reportTitle' => 'Ticket Cancel',
        ])
    </div>

</div>

<script>
(function () {
    const routes = {
        selling: {
            preview: @json(route('admin.reports.selling.preview')),
            excel: @json(route('admin.reports.selling.excel')),
            pdf: @json(route('admin.reports.selling.pdf')),
        },
        cancel: {
            preview: @json(route('admin.reports.cancel.preview')),
            excel: @json(route('admin.reports.cancel.excel')),
            pdf: @json(route('admin.reports.cancel.pdf')),
        },
    };

    const sellingHeaders = ['PNR', 'Sold Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'];
    const cancelHeaders = ['PNR', 'Cancel Date', 'Booked Date', 'Passenger', 'Phone', 'Route', 'Departure', 'Operator', 'Coach Type', 'Seats', 'Fare (BDT)', 'Payment'];

    function getFilters(type) {
        const period = document.querySelector(`.report-period[data-report="${type}"]`)?.value || 'monthly';
        const params = new URLSearchParams({
            period,
            coach_type: document.querySelector(`.report-coach-type[data-report="${type}"]`)?.value || 'All',
            payment_method: document.querySelector(`.report-payment-method[data-report="${type}"]`)?.value || 'All',
            route_id: document.querySelector(`.report-route-id[data-report="${type}"]`)?.value || 'All',
            operator: document.querySelector(`.report-operator[data-report="${type}"]`)?.value || 'All',
        });

        if (period === 'custom') {
            params.set('from_date', document.querySelector(`.report-from-date[data-report="${type}"]`)?.value || '');
            params.set('to_date', document.querySelector(`.report-to-date[data-report="${type}"]`)?.value || '');
        }

        return params;
    }

    function toggleCustomDates(type) {
        const period = document.querySelector(`.report-period[data-report="${type}"]`)?.value;
        const customEl = document.getElementById(`${type}-custom-dates`);
        if (customEl) {
            customEl.style.display = period === 'custom' ? 'grid' : 'none';
        }
    }

    function renderSummary(type, summary) {
        const el = document.getElementById(`${type}-summary`);
        if (!el) return;

        const fareLabel = type === 'selling' ? 'Total Revenue' : 'Cancelled Fare';
        const ticketLabel = type === 'selling' ? 'Tickets Sold' : 'Cancelled Tickets';

        el.innerHTML = `
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--success)">#</div>
                <div class="stat-info">
                    <span class="stat-label">${ticketLabel}</span>
                    <span class="stat-value">${summary.total_tickets}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--primary)">💺</div>
                <div class="stat-info">
                    <span class="stat-label">Total Seats</span>
                    <span class="stat-value">${summary.total_seats}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--gold)">$</div>
                <div class="stat-info">
                    <span class="stat-label">${fareLabel} (BDT)</span>
                    <span class="stat-value">${Number(summary.total_fare).toLocaleString()}</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: #818CF8">AC</div>
                <div class="stat-info">
                    <span class="stat-label">AC / Non AC</span>
                    <span class="stat-value">${summary.ac_tickets} / ${summary.non_ac_tickets}</span>
                </div>
            </div>
        `;
        el.style.display = 'grid';
    }

    function renderTable(type, rows) {
        const head = document.getElementById(`${type}-table-head`);
        const body = document.getElementById(`${type}-table-body`);
        const headers = type === 'selling' ? sellingHeaders : cancelHeaders;

        head.innerHTML = '<tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr>';

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="${headers.length}" style="text-align:center;padding:30px;color:var(--text-muted)">No records found.</td></tr>`;
            return;
        }

        body.innerHTML = rows.map(row => {
            if (type === 'selling') {
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
                    <td style="color:var(--gold);font-weight:bold">BDT ${Number(row.fare).toLocaleString()}</td>
                    <td>${row.payment_method}</td>
                </tr>`;
            }

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
                <td style="color:var(--gold);font-weight:bold">BDT ${Number(row.fare).toLocaleString()}</td>
                <td>${row.payment_method}</td>
            </tr>`;
        }).join('');
    }

    async function generateReport(type) {
        const params = getFilters(type);
        const btn = document.querySelector(`.report-generate-btn[data-report="${type}"]`);

        if (params.get('period') === 'custom' && (!params.get('from_date') || !params.get('to_date'))) {
            alert('Please select both From and To dates for custom range.');
            return;
        }

        if (btn) {
            btn.textContent = 'Loading...';
            btn.disabled = true;
        }

        try {
            const res = await fetch(`${routes[type].preview}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });

            const data = await res.json();

            if (!res.ok) {
                const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to load report.');
                alert(msg);
                return;
            }

            document.getElementById(`${type}-empty-hint`).style.display = 'none';
            document.getElementById(`${type}-filter-label`).textContent = data.filter_label;
            document.getElementById(`${type}-filter-label`).style.display = 'block';
            document.getElementById(`${type}-table-panel`).style.display = 'block';

            renderSummary(type, data.summary);
            renderTable(type, data.rows);

            document.querySelector(`.report-export-excel-btn[data-report="${type}"]`).disabled = false;
            document.querySelector(`.report-export-pdf-btn[data-report="${type}"]`).disabled = false;
        } catch (err) {
            alert('Network error while loading report.');
        } finally {
            if (btn) {
                btn.textContent = 'Generate Report';
                btn.disabled = false;
            }
        }
    }

    function exportReport(type, format) {
        const params = getFilters(type);
        const url = format === 'excel' ? routes[type].excel : routes[type].pdf;
        window.location.href = `${url}?${params.toString()}`;
    }

    document.querySelectorAll('.report-period').forEach(el => {
        el.addEventListener('change', () => toggleCustomDates(el.dataset.report));
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

    ['selling', 'cancel'].forEach(type => {
        const fromInput = document.querySelector(`.report-from-date[data-report="${type}"]`);
        const toInput = document.querySelector(`.report-to-date[data-report="${type}"]`);
        const today = new Date().toISOString().split('T')[0];
        const monthStart = new Date();
        monthStart.setDate(1);
        if (fromInput) fromInput.value = monthStart.toISOString().split('T')[0];
        if (toInput) toInput.value = today;
    });
})();
</script>
