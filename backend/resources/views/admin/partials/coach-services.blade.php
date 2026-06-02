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
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
    let searchParams = { from: '', to: '', date: '', coachType: 'All' };
    let searchResults = [];
    let searchDone = false;
    let expandedScheduleId = null;
    let selectedSeatBooking = null;
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

    function renderSeatMap(schedule) {
        const booked = schedule.booked_seats || [];
        const seatBookings = schedule.seat_bookings || {};

        let html = `
            <div class="bus-blueprint">
                <div class="bus-head">
                    <div class="driver-wheel" title="Driver Cabin">⭕</div>
                    <span style="font-size: 11px; color: var(--text-muted); font-weight: bold;">ENTRANCE</span>
                </div>
                <div class="bus-body-seats">`;

        rows.forEach(row => {
            const seats = [`${row}1`, `${row}2`, `${row}3`, `${row}4`];
            html += `<div class="seat-row">`;
            html += `<div class="seat-pair">`;
            seats.slice(0, 2).forEach(seat => {
                const isBooked = booked.includes(seat);
                html += `<div class="seat ${isBooked ? 'booked admin-booked' : ''}"
                    data-seat="${seat}" data-schedule="${schedule.id}"
                    title="${isBooked ? 'Click to view booking' : 'Available'}">${seat}</div>`;
            });
            html += `</div><div class="bus-aisle"></div><div class="seat-pair">`;
            seats.slice(2, 4).forEach(seat => {
                const isBooked = booked.includes(seat);
                html += `<div class="seat ${isBooked ? 'booked admin-booked' : ''}"
                    data-seat="${seat}" data-schedule="${schedule.id}"
                    title="${isBooked ? 'Click to view booking' : 'Available'}">${seat}</div>`;
            });
            html += `</div></div>`;
        });

        html += `
                </div>
                <div class="seat-legend">
                    <div class="legend-item"><div class="legend-dot available"></div><span>Available</span></div>
                    <div class="legend-item"><div class="legend-dot booked"></div><span>Booked</span></div>
                </div>
            </div>`;

        return html;
    }

    function renderBookingSidebar(schedule) {
        if (!selectedSeatBooking) {
            return `
                <div class="booking-form-sidebar">
                    <h3 class="booking-summary-title">Seat Booking Details</h3>
                    <p style="color: var(--text-secondary); font-size: 13px;">
                        Click a <strong style="color: #E53E3E;">booked seat</strong> on the layout to view passenger details and cancel the reservation.
                    </p>
                    <div class="summary-row">
                        <span class="summary-label">Coach</span>
                        <span class="summary-value">${escapeHtml(schedule.bus.operator_name)} (${escapeHtml(schedule.bus.coach_type)})</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Available Seats</span>
                        <span class="summary-value" style="color: var(--success);">${schedule.available_seats_count} / ${schedule.bus.total_seats}</span>
                    </div>
                </div>`;
        }

        const b = selectedSeatBooking;
        return `
            <div class="booking-form-sidebar">
                <h3 class="booking-summary-title">Selected Seat Booking</h3>
                <div class="summary-row">
                    <span class="summary-label">Seat</span>
                    <span class="summary-value"><span class="selected-seats-badge">${escapeHtml(b.seat)}</span></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">PNR</span>
                    <span class="summary-value" style="color: var(--primary); font-weight: bold;">${escapeHtml(b.pnr)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Passenger</span>
                    <span class="summary-value">${escapeHtml(b.passenger_name)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Phone</span>
                    <span class="summary-value">${escapeHtml(b.passenger_phone)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Email</span>
                    <span class="summary-value">${escapeHtml(b.passenger_email)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">All Seats</span>
                    <span class="summary-value">${escapeHtml(b.seat_numbers)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Fare Paid</span>
                    <span class="summary-value" style="color: var(--gold);">BDT ${Number(b.total_fare).toLocaleString()}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Payment</span>
                    <span class="summary-value">${escapeHtml(b.payment_method)}</span>
                </div>
                <button type="button" class="btn btn-danger" id="cs-cancel-booking-btn" style="margin-top: 16px; width: 100%; height: 42px;">
                    Cancel This Booking
                </button>
                <p style="font-size: 11px; color: var(--text-muted); margin-top: 8px; text-align: center;">
                    Cancelling releases all seats in this booking (${escapeHtml(b.seat_numbers)}).
                </p>
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
                                        Bus Seat Layout (Click booked seat to cancel)
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
                } else {
                    expandedScheduleId = id;
                    selectedSeatBooking = null;
                }
                renderResults();
            });
        });

        document.querySelectorAll('.seat.admin-booked').forEach(seatEl => {
            seatEl.addEventListener('click', () => {
                const seat = seatEl.dataset.seat;
                const scheduleId = parseInt(seatEl.dataset.schedule, 10);
                const schedule = searchResults.find(s => s.id === scheduleId);
                if (!schedule || !schedule.seat_bookings || !schedule.seat_bookings[seat]) return;

                selectedSeatBooking = { seat, ...schedule.seat_bookings[seat] };
                expandedScheduleId = scheduleId;
                renderResults();
            });
        });

        const cancelBtn = document.getElementById('cs-cancel-booking-btn');
        if (cancelBtn && selectedSeatBooking) {
            cancelBtn.addEventListener('click', () => handleCancelBooking(selectedSeatBooking.booking_id));
        }
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

        document.getElementById('cs-empty-hint').style.display = 'none';
        document.getElementById('cs-results').style.display = 'block';

        await fetchCoachServices();
        startPolling();
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
