<div class="coach-services-panel" style="grid-column: 1 / -1;">

    <!-- Search Form -->
    <div class="search-card" style="margin-bottom: 30px;">
        <h3 class="admin-panel-title" style="margin-bottom: 20px;">Search Coach Services</h3>
        <form class="search-form" id="coach-search-form" onsubmit="return false;">
            <div class="input-group">
                <label>From Station</label>
                <select id="cs-from" class="coupon-input" required>
                    <option value="">Select departure...</option>
                    @foreach($stations as $st)
                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label>To Station</label>
                <select id="cs-to" class="coupon-input" required>
                    <option value="">Select destination...</option>
                    @foreach($stations as $st)
                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label>Journey Date</label>
                <input type="date" id="cs-date" class="coupon-input" required>
            </div>
            <div class="input-group">
                <label>Coach Type</label>
                <select id="cs-coach-type" class="coupon-input">
                    <option value="All">All Coach Types</option>
                    <option value="AC">AC (Air Conditioned)</option>
                    <option value="Non AC">Non AC</option>
                </select>
            </div>
        </form>
        <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <button type="button" class="btn btn-primary" id="cs-search-btn" style="max-width: 250px;">
                Search Buses
            </button>
            <div id="cs-live-status" class="live-status" style="display: none;">
                <span class="live-dot"></span>
                <span id="cs-live-text">Live — updating every 5s</span>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div id="cs-results" style="display: none;">
        <div class="results-header">
            <h2 class="section-title">Available Coach Services</h2>
            <span class="results-count" id="cs-results-count"></span>
        </div>
        <div class="bus-list" id="cs-bus-list"></div>
    </div>

    <div id="cs-empty-hint" class="notice-info-box">
        Search by route and date to view available coach services with a live seat map. Booked seats can be cancelled directly from the layout.
    </div>

</div>

<script>
(function () {
    const stations = @json($stations->map(fn($s) => ['id' => $s->id, 'name' => $s->name]));
    const searchUrl = @json(route('admin.coach-services.search'));
    const cancelUrlTemplate = @json(route('admin.bookings.cancel.api', ['id' => '__ID__']));
    const bookUrl = @json(route('admin.bookings.store'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
    let searchParams = { from: '', to: '', date: '', coachType: 'All' };
    let searchResults = [];
    let searchDone = false;
    let expandedScheduleId = null;
    let selectedSeatBooking = null;
    let adminSelectedSeats = [];
    let adminBoardingPoint = '';
    let adminDroppingPoint = '';
    let adminGender = 'M';
    let adminPaymentMethod = 'Cash';
    let pollTimer = null;
    let isFetching = false;

    const dateInput = document.getElementById('cs-date');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }

    function stationName(id) {
        const st = stations.find(s => s.id === parseInt(id, 10));
        return st ? st.name : '';
    }

    function formatTime(iso) {
        if (!iso) return '';
        return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function formatBdt(amount) {
        return '৳ ' + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getSeatMap(schedule) {
        if (schedule.seat_map) return schedule.seat_map;
        const map = {};
        const booked = schedule.booked_seats || [];
        rows.forEach(row => {
            [1, 2, 3, 4].forEach(n => {
                const code = row + n;
                map[code] = booked.includes(code) ? 'sold_m' : 'available';
            });
        });
        return map;
    }

    function calcPricing(schedule, seatCount, paymentMethod) {
        const fare = Number(schedule.fare || 0);
        const p = schedule.pricing || {};
        const applyGateway = String(paymentMethod).toLowerCase() !== 'cash';
        const seatFare = fare * seatCount;
        const serviceCharge = (p.service_charge ?? 20) * seatCount;
        const gatewayCharge = applyGateway ? (p.gateway_charge ?? 16) * seatCount : 0;
        const scDiscount = (p.sc_discount ?? 20) * seatCount;
        const gcDiscount = applyGateway ? (p.gc_discount ?? 16) * seatCount : 0;
        const total = Math.max(0, seatFare + serviceCharge + gatewayCharge - scDiscount - gcDiscount);
        return { seatFare, serviceCharge, gatewayCharge, scDiscount, gcDiscount, total };
    }

    function seatLabel(status) {
        const labels = {
            available: 'Available', selected: 'Selected', blocked: 'Blocked',
            booked_m: 'Booked (M)', booked_f: 'Booked (F)', sold_m: 'Sold (M)', sold_f: 'Sold (F)'
        };
        return labels[status] || status;
    }

    function renderSeatLegend() {
        const items = [
            ['booked_m', 'Booked (M)'], ['booked_f', 'Booked (F)'], ['blocked', 'Blocked'],
            ['available', 'Available'], ['selected', 'Selected'], ['sold_m', 'Sold (M)'], ['sold_f', 'Sold (F)']
        ];
        return items.map(([key, label]) =>
            `<div class="legend-item"><div class="legend-dot status-${key}"></div><span>${label}</span></div>`
        ).join('');
    }

    function renderSeatMap(schedule) {
        const seatMap = getSeatMap(schedule);

        let html = `
            <div class="bus-blueprint">
                <div class="bus-head">
                    <div class="driver-wheel" title="Driver Cabin">⭕</div>
                    <span style="font-size: 11px; color: var(--text-muted); font-weight: bold;">ENTRANCE</span>
                </div>
                <div class="bus-body-seats">`;

        rows.forEach(row => {
            const seats = [`${row}1`, `${row}2`, `${row}3`, `${row}4`];
            html += `<div class="seat-row"><div class="seat-pair">`;
            seats.slice(0, 2).forEach(seat => { html += renderSeatCell(schedule, seat, seatMap); });
            html += `</div><div class="bus-aisle"></div><div class="seat-pair">`;
            seats.slice(2, 4).forEach(seat => { html += renderSeatCell(schedule, seat, seatMap); });
            html += `</div></div>`;
        });

        html += `</div><div class="seat-legend">${renderSeatLegend()}</div></div>`;
        return html;
    }

    function renderSeatCell(schedule, seat, seatMap) {
        const status = seatMap[seat] || 'available';
        const isPicking = adminSelectedSeats.includes(seat) && status === 'available';
        const isViewing = selectedSeatBooking?.seat === seat;
        const cssParts = [];
        if (isPicking) {
            cssParts.push('selected');
        } else {
            cssParts.push(`status-${status}`);
        }
        if (isViewing) cssParts.push('viewing-booking');
        const selectable = status === 'available' || isPicking || (isViewing && status !== 'available' && status !== 'blocked');
        const titleStatus = isPicking ? 'selected' : status;
        return `<div class="seat ${cssParts.join(' ')} ${selectable ? 'selectable' : ''}"
            data-seat="${seat}" data-schedule="${schedule.id}" data-status="${status}"
            title="${seatLabel(titleStatus)}">${seat}</div>`;
    }

    function renderBookingSidebar(schedule) {
        if (selectedSeatBooking) {
            const b = selectedSeatBooking;
            return `
                <div class="booking-form-sidebar">
                    <h3 class="booking-summary-title">Seat Booking Details</h3>
                    <div class="summary-row"><span class="summary-label">Seat</span><span class="summary-value"><span class="selected-seats-badge">${escapeHtml(b.seat)}</span></span></div>
                    <div class="summary-row"><span class="summary-label">PNR</span><span class="summary-value" style="color: var(--primary); font-weight: bold;">${escapeHtml(b.pnr)}</span></div>
                    <div class="summary-row"><span class="summary-label">Passenger</span><span class="summary-value">${escapeHtml(b.passenger_name)}</span></div>
                    <div class="summary-row"><span class="summary-label">Phone</span><span class="summary-value">${escapeHtml(b.passenger_phone)}</span></div>
                    <div class="summary-row"><span class="summary-label">Fare Paid</span><span class="summary-value" style="color: var(--gold);">${formatBdt(b.total_fare)}</span></div>
                    <button type="button" class="btn btn-danger" id="cs-cancel-booking-btn" style="margin-top: 16px; width: 100%; height: 42px;">Cancel This Booking</button>
                    <button type="button" class="btn btn-secondary" id="cs-back-to-book-btn" style="margin-top: 8px; width: 100%;">Book New Seat</button>
                </div>`;
        }

        const boardingOpts = (schedule.boarding_points || []).map(bp =>
            `<option value="${escapeHtml(bp.value)}" ${adminBoardingPoint === bp.value ? 'selected' : ''}>${escapeHtml(bp.label)} — Reporting: ${escapeHtml(bp.reporting_time || '—')}, Departure: ${escapeHtml(bp.departure_time || '—')}</option>`
        ).join('');
        const droppingOpts = (schedule.dropping_points || []).map(dp =>
            `<option value="${escapeHtml(dp.value)}" ${adminDroppingPoint === dp.value ? 'selected' : ''}>${escapeHtml(dp.label)} — Arrival: ${escapeHtml(dp.arrival_time || '—')}</option>`
        ).join('');
        const selectedBoarding = (schedule.boarding_points || []).find(bp => bp.value === adminBoardingPoint);
        const selectedDropping = (schedule.dropping_points || []).find(dp => dp.value === adminDroppingPoint);
        const seatClass = schedule.seat_class || 'E-Class';
        const pricing = calcPricing(schedule, Math.max(1, adminSelectedSeats.length), adminPaymentMethod);
        const seatRows = adminSelectedSeats.length
            ? adminSelectedSeats.map(seat => `<tr><td>${escapeHtml(seat)}</td><td>${escapeHtml(seatClass)}</td><td>${formatBdt(schedule.fare)}</td></tr>`).join('')
            : `<tr><td colspan="3" style="color:#9ca3af; font-style:italic;">Select seat(s) from the map</td></tr>`;

        return `
            <div class="booking-form-sidebar">
                <div class="ticket-booking-panel">
                    <h3>Boarding / Dropping Point</h3>
                    <label>Boarding Point *</label>
                    <select id="cs-boarding-point" class="ticket-field-select">${boardingOpts}</select>
                    <p class="boarding-point-info" id="cs-boarding-info">${selectedBoarding ? `Reporting: ${escapeHtml(selectedBoarding.reporting_time || '—')} · Departure: ${escapeHtml(selectedBoarding.departure_time || '—')}` : 'Select a boarding point'}</p>
                    <label>Dropping Point *</label>
                    <select id="cs-dropping-point" class="ticket-field-select">
                        <option value="">Select dropping point</option>
                        ${droppingOpts}
                    </select>
                    <p class="boarding-point-info" id="cs-dropping-info">${selectedDropping ? `Estimated arrival: ${escapeHtml(selectedDropping.arrival_time || '—')}` : 'Select a dropping point'}</p>
                    <label>Mobile Number *</label>
                    <input type="tel" id="cs-booking-phone" placeholder="01XXXXXXXXX">
                    <label>Passenger Name *</label>
                    <input type="text" id="cs-booking-name" placeholder="Full name">
                    <label>Email *</label>
                    <input type="email" id="cs-booking-email" placeholder="email@example.com">
                    <label>Gender</label>
                    <select id="cs-booking-gender">
                        <option value="M" ${adminGender === 'M' ? 'selected' : ''}>Male</option>
                        <option value="F" ${adminGender === 'F' ? 'selected' : ''}>Female</option>
                    </select>
                    <label>Payment Method</label>
                    <select id="cs-booking-payment">
                        <option value="Cash" ${adminPaymentMethod === 'Cash' ? 'selected' : ''}>Cash</option>
                        <option value="bKash" ${adminPaymentMethod === 'bKash' ? 'selected' : ''}>bKash</option>
                        <option value="Nagad" ${adminPaymentMethod === 'Nagad' ? 'selected' : ''}>Nagad</option>
                        <option value="Card" ${adminPaymentMethod === 'Card' ? 'selected' : ''}>Card</option>
                    </select>
                    <div id="cs-booking-error" style="color:#dc2626; font-size:13px; display:none; margin-bottom:8px;"></div>
                    <button type="button" class="btn-ticket-submit" id="cs-booking-submit">Submit</button>
                    <h4 style="margin-top:20px;">Seat Information</h4>
                    <table class="seat-info-table">
                        <thead><tr><th>Seats</th><th>Class</th><th>Fare</th></tr></thead>
                        <tbody>${seatRows}</tbody>
                    </table>
                    <div class="fare-breakdown">
                        <div class="fare-line"><span>Seat Fare:</span><strong>${formatBdt(pricing.seatFare)}</strong></div>
                        <div class="fare-line"><span>Service Charge:</span><strong>${formatBdt(pricing.serviceCharge)}</strong></div>
                        <div class="fare-line"><span>Gateway Charge:</span><strong>${formatBdt(pricing.gatewayCharge)}</strong></div>
                        <div class="fare-line"><span>SC Discount:</span><strong>${formatBdt(pricing.scDiscount)}</strong></div>
                        <div class="fare-line"><span>GC Discount:</span><strong>${formatBdt(pricing.gcDiscount)}</strong></div>
                        <div class="fare-line fare-total"><span>Total:</span><strong>${formatBdt(pricing.total)}</strong></div>
                    </div>
                </div>
            </div>`;
    }

    function renderResults() {
        const listEl = document.getElementById('cs-bus-list');
        const countEl = document.getElementById('cs-results-count');
        if (!listEl) return;

        countEl.textContent = `Showing ${searchResults.length} schedule${searchResults.length === 1 ? '' : 's'}`;

        if (searchResults.length === 0) {
            listEl.innerHTML = `
                <div class="search-card text-center" style="padding: 60px; text-align: center;">
                    <h3>No Coaches Scheduled</h3>
                    <p style="color: var(--text-secondary); margin-top: 8px;">
                        No scheduled buses match this criteria. Try another date or route.
                    </p>
                </div>`;
            return;
        }

        listEl.innerHTML = searchResults.map(sched => {
            const isExpanded = expandedScheduleId === sched.id;
            const availColor = sched.available_seats_count === 0 ? 'var(--danger)' : 'var(--success)';

            return `
                <div class="bus-card" data-schedule-id="${sched.id}">
                    <div class="bus-main-info">
                        <div class="operator-block">
                            <span class="operator-name">${escapeHtml(sched.bus.operator_name)}</span>
                            <span style="font-size: 11px; color: var(--text-muted);">Coach ${escapeHtml(sched.bus.coach_number)}</span>
                            <span class="coach-tag ${sched.bus.coach_type === 'AC' ? 'ac' : ''}">${escapeHtml(sched.bus.coach_type)}</span>
                        </div>
                        <div class="time-block">
                            <span class="time-label">Departure</span>
                            <span class="time-value">${formatTime(sched.departure_time)}</span>
                            <span class="station-value">${escapeHtml(stationName(searchParams.from))}</span>
                        </div>
                        <div class="time-block">
                            <span class="time-label">Arrival</span>
                            <span class="time-value">${formatTime(sched.arrival_time)}</span>
                            <span class="station-value">${escapeHtml(stationName(searchParams.to))}</span>
                        </div>
                        <div class="time-block">
                            <span class="time-label">Duration</span>
                            <span class="time-value" style="font-weight: 500;">${escapeHtml(sched.route.duration)}</span>
                            <span class="station-value" style="font-size: 11px;">${escapeHtml(sched.route.distance)}</span>
                        </div>
                        <div class="seats-block">
                            <span class="time-label">Seats Available</span>
                            <span class="seats-count" style="color: ${availColor};">${sched.available_seats_count} Seats</span>
                        </div>
                        <div class="price-block">
                            <span class="time-label">Fare Price</span>
                            <span class="price-amount">BDT ${Number(sched.fare).toLocaleString()}</span>
                            <button type="button" class="btn ${isExpanded ? 'btn-secondary' : 'btn-primary'} cs-toggle-map"
                                data-id="${sched.id}" style="margin-top: 8px; padding: 6px 12px; font-size: 12px;">
                                ${isExpanded ? 'Close Map' : 'View Seat Plan'}
                            </button>
                        </div>
                    </div>
                    ${isExpanded ? `
                        <div class="seats-selector-container">
                            <div class="seat-selection-grid">
                                <div>
                                    <h3 style="font-size: 14px; margin-bottom: 15px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px;">
                                        Bus Seat Layout
                                    </h3>
                                    ${renderSeatMap(sched)}
                                </div>
                                <div id="cs-sidebar-${sched.id}">
                                    ${renderBookingSidebar(sched)}
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>`;
        }).join('');

        bindResultEvents();
    }

    function bindResultEvents() {
        document.querySelectorAll('.cs-toggle-map').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                if (expandedScheduleId === id) {
                    expandedScheduleId = null;
                    selectedSeatBooking = null;
                    adminSelectedSeats = [];
                } else {
                    expandedScheduleId = id;
                    selectedSeatBooking = null;
                    adminSelectedSeats = [];
                    const sched = searchResults.find(s => s.id === id);
                    if (sched?.boarding_points?.[0]) adminBoardingPoint = sched.boarding_points[0].value;
                    if (sched?.dropping_points?.[0]) adminDroppingPoint = '';
                }
                renderResults();
            });
        });

        document.querySelectorAll('.seat[data-seat]').forEach(seatEl => {
            seatEl.addEventListener('click', () => {
                const seat = seatEl.dataset.seat;
                const status = seatEl.dataset.status;
                const scheduleId = parseInt(seatEl.dataset.schedule, 10);
                const schedule = searchResults.find(s => s.id === scheduleId);
                if (!schedule) return;

                if (status !== 'available' && status !== 'blocked') {
                    if (schedule.seat_bookings?.[seat]) {
                        selectedSeatBooking = { seat, ...schedule.seat_bookings[seat] };
                        adminSelectedSeats = [];
                        renderResults();
                    }
                    return;
                }

                if (adminSelectedSeats.includes(seat)) {
                    adminSelectedSeats = adminSelectedSeats.filter(s => s !== seat);
                } else {
                    adminSelectedSeats.push(seat);
                }
                selectedSeatBooking = null;
                renderResults();
            });
        });

        document.getElementById('cs-cancel-booking-btn')?.addEventListener('click', () => {
            if (selectedSeatBooking) handleCancelBooking(selectedSeatBooking.booking_id);
        });
        document.getElementById('cs-back-to-book-btn')?.addEventListener('click', () => {
            selectedSeatBooking = null;
            renderResults();
        });
        document.getElementById('cs-boarding-point')?.addEventListener('change', (e) => {
            adminBoardingPoint = e.target.value;
            const schedule = searchResults.find(s => s.id === expandedScheduleId);
            const bp = schedule?.boarding_points?.find(p => p.value === adminBoardingPoint);
            const info = document.getElementById('cs-boarding-info');
            if (info) {
                info.textContent = bp
                    ? `Reporting: ${bp.reporting_time || '—'} · Departure: ${bp.departure_time || '—'}`
                    : 'Select a boarding point';
            }
        });
        document.getElementById('cs-dropping-point')?.addEventListener('change', (e) => {
            adminDroppingPoint = e.target.value;
            const schedule = searchResults.find(s => s.id === expandedScheduleId);
            const dp = schedule?.dropping_points?.find(p => p.value === adminDroppingPoint);
            const info = document.getElementById('cs-dropping-info');
            if (info) {
                info.textContent = dp
                    ? `Estimated arrival: ${dp.arrival_time || '—'}`
                    : 'Select a dropping point';
            }
        });
        document.getElementById('cs-booking-gender')?.addEventListener('change', (e) => {
            adminGender = e.target.value;
            renderResults();
        });
        document.getElementById('cs-booking-payment')?.addEventListener('change', (e) => {
            adminPaymentMethod = e.target.value;
            renderResults();
        });
        document.getElementById('cs-booking-submit')?.addEventListener('click', () => {
            const schedule = searchResults.find(s => s.id === expandedScheduleId);
            if (schedule) handleBookingSubmit(schedule);
        });
    }

    async function fetchCoachServices(silent = false) {
        if (isFetching || !searchDone) return;
        isFetching = true;

        if (!silent) {
            const btn = document.getElementById('cs-search-btn');
            if (btn) btn.textContent = 'Searching...';
        }

        const params = new URLSearchParams({
            from: searchParams.from,
            to: searchParams.to,
            date: searchParams.date,
            coach_type: searchParams.coachType,
        });

        try {
            const res = await fetch(`${searchUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (res.ok) {
                searchResults = await res.json();

                if (adminSelectedSeats.length && expandedScheduleId) {
                    const sched = searchResults.find(s => s.id === expandedScheduleId);
                    if (sched) {
                        const map = getSeatMap(sched);
                        const stillAvailable = adminSelectedSeats.filter(seat => map[seat] === 'available');
                        if (stillAvailable.length < adminSelectedSeats.length) {
                            alert('One or more selected seats were just booked. Selection updated.');
                        }
                        adminSelectedSeats = stillAvailable;
                    }
                }

                if (selectedSeatBooking && expandedScheduleId) {
                    const schedule = searchResults.find(s => s.id === expandedScheduleId);
                    if (schedule?.seat_bookings?.[selectedSeatBooking.seat]) {
                        selectedSeatBooking = {
                            seat: selectedSeatBooking.seat,
                            ...schedule.seat_bookings[selectedSeatBooking.seat],
                        };
                    } else {
                        selectedSeatBooking = null;
                    }
                }

                renderResults();
                updateLiveStatus();
            }
        } catch (err) {
            console.error('Coach services fetch failed', err);
        } finally {
            isFetching = false;
            const btn = document.getElementById('cs-search-btn');
            if (btn) btn.textContent = 'Search Buses';
        }
    }

    function updateLiveStatus() {
        const statusEl = document.getElementById('cs-live-status');
        const textEl = document.getElementById('cs-live-text');
        if (!statusEl || !textEl) return;

        statusEl.style.display = 'inline-flex';
        const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        textEl.textContent = `Live — last updated ${now} (refreshes every 5s)`;
    }

    async function handleSearch() {
        const from = document.getElementById('cs-from').value;
        const to = document.getElementById('cs-to').value;
        const date = document.getElementById('cs-date').value;
        const coachType = document.getElementById('cs-coach-type').value;

        if (!from || !to || !date) {
            alert('Please select from, to, and date.');
            return;
        }

        if (from === to) {
            alert('Departure and destination must be different.');
            return;
        }

        searchParams = { from, to, date, coachType };
        searchDone = true;
        expandedScheduleId = null;
        selectedSeatBooking = null;
        adminSelectedSeats = [];

        document.getElementById('cs-empty-hint').style.display = 'none';
        document.getElementById('cs-results').style.display = 'block';

        await fetchCoachServices();
        startPolling();
    }

    async function handleBookingSubmit(schedule) {
        const errorEl = document.getElementById('cs-booking-error');
        const name = document.getElementById('cs-booking-name')?.value.trim() || '';
        const phone = document.getElementById('cs-booking-phone')?.value.trim() || '';
        const email = document.getElementById('cs-booking-email')?.value.trim() || '';
        const boarding = document.getElementById('cs-boarding-point')?.value || adminBoardingPoint;
        const dropping = document.getElementById('cs-dropping-point')?.value || adminDroppingPoint;
        const gender = document.getElementById('cs-booking-gender')?.value || adminGender;
        const payment = document.getElementById('cs-booking-payment')?.value || adminPaymentMethod;

        if (!adminSelectedSeats.length) {
            if (errorEl) { errorEl.style.display = 'block'; errorEl.textContent = 'Please select at least one seat.'; }
            return;
        }
        if (!name || !phone || !email || !boarding || !dropping) {
            if (errorEl) { errorEl.style.display = 'block'; errorEl.textContent = 'Please fill boarding, dropping, and passenger details.'; }
            return;
        }

        const pricing = calcPricing(schedule, adminSelectedSeats.length, payment);
        const payload = {
            schedule_id: schedule.id,
            passenger_name: name,
            passenger_phone: phone,
            passenger_email: email,
            passenger_gender: gender,
            boarding_point: boarding,
            dropping_point: dropping,
            seat_numbers: adminSelectedSeats.join(','),
            payment_method: payment,
            total_fare: pricing.total,
            status: 'PAID',
        };

        try {
            const res = await fetch(bookUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
            });

            if (res.ok) {
                const data = await res.json().catch(() => ({}));
                adminSelectedSeats = [];
                selectedSeatBooking = null;
                await fetchCoachServices(true);
                const smsNote = data.sms?.success ? ' SMS sent.' : '';
                alert('Booking created successfully.' + smsNote);
                return;
            }
            const data = await res.json().catch(() => ({}));
            if (errorEl) { errorEl.style.display = 'block'; errorEl.textContent = data.message || 'Failed to create booking.'; }
        } catch (err) {
            if (errorEl) { errorEl.style.display = 'block'; errorEl.textContent = 'Network error while submitting.'; }
        }
    }

    async function handleCancelBooking(bookingId) {
        if (!confirm('Cancel this booking and release all seats?')) return;

        const cancelUrl = cancelUrlTemplate.replace('__ID__', bookingId);

        try {
            const res = await fetch(cancelUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            const data = await res.json();

            if (res.ok) {
                selectedSeatBooking = null;
                await fetchCoachServices(true);
            } else {
                alert(data.message || 'Failed to cancel booking.');
            }
        } catch (err) {
            alert('Network error during cancellation.');
        }
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(() => {
            const tab = document.getElementById('tab-content-coach-services');
            if (tab && tab.style.display !== 'none' && searchDone) {
                fetchCoachServices(true);
            }
        }, 5000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    document.getElementById('cs-search-btn')?.addEventListener('click', handleSearch);

    window.coachServicesModule = { startPolling, stopPolling };
})();
</script>
