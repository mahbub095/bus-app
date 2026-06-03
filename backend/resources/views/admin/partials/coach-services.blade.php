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

    <div id="cs-booking-modal" style="display:none; position: fixed; inset: 0; z-index: 9999; background: rgba(14,17,34,0.82); align-items: center; justify-content: center; padding: 24px;">
        <div style="background: var(--bg); border-radius: var(--border-radius-md); width: min(520px, 100%); box-shadow: 0 24px 60px rgba(0,0,0,.25); overflow: hidden;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:18px 22px; border-bottom:1px solid rgba(255,255,255,.08);">
                <div>
                    <h3 id="cs-booking-modal-title" style="margin:0; font-size:18px;">Confirm Booking</h3>
                    <p id="cs-booking-schedule" style="margin:6px 0 0; font-size:13px; color: var(--text-secondary);"></p>
                </div>
                <button type="button" id="cs-booking-modal-close" style="background:none; border:none; color:var(--text); font-size:24px; line-height:1; cursor:pointer;">×</button>
            </div>
            <form id="cs-booking-form" style="padding:20px; display:grid; gap:16px;" onsubmit="return false;">
                <input type="hidden" id="cs-booking-schedule-id" name="schedule_id">
                <input type="hidden" id="cs-booking-seat" name="seat_numbers">
                <input type="hidden" id="cs-booking-status" name="status" value="PAID">

                <div style="display:grid; gap:12px;">
                    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:130px;">
                            <label style="font-size:12px; font-weight:600; margin-bottom:8px; display:block;">Seat</label>
                            <input type="text" id="cs-booking-seat-display" readonly style="width:100%; padding:11px 12px; border:1px solid rgba(255,255,255,.12); border-radius:10px; background:rgba(255,255,255,.04); color:var(--text);">
                        </div>
                        <div style="flex:1; min-width:130px;">
                            <label style="font-size:12px; font-weight:600; margin-bottom:8px; display:block;">Fare</label>
                            <input type="text" id="cs-booking-fare" readonly style="width:100%; padding:11px 12px; border:1px solid rgba(255,255,255,.12); border-radius:10px; background:rgba(255,255,255,.04); color:var(--text);">
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label>Passenger Name</label>
                    <input type="text" id="cs-booking-passenger-name" class="coupon-input" placeholder="Full name" required>
                </div>
                <div class="input-group">
                    <label>Passenger Phone</label>
                    <input type="text" id="cs-booking-passenger-phone" class="coupon-input" placeholder="01XXXXXXXXX" required>
                </div>
                <div class="input-group">
                    <label>Passenger Email</label>
                    <input type="email" id="cs-booking-passenger-email" class="coupon-input" placeholder="email@example.com" required>
                </div>
                <div class="input-group">
                    <label>Payment Method</label>
                    <select id="cs-booking-payment-method" class="coupon-input" required>
                        <option value="Cash">Cash</option>
                        <option value="bKash">bKash</option>
                        <option value="Nagad">Nagad</option>
                        <option value="Card">Card</option>
                    </select>
                </div>
                <div id="cs-booking-error" style="color:#ff6b6b; font-size:13px; display:none;"></div>
                <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary" id="cs-booking-cancel" style="height:42px;">Cancel</button>
                    <button type="button" class="btn btn-primary" id="cs-booking-submit" style="height:42px;">Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
(function () {
    const stations = @json($stations->map(fn($s) => ['id' => $s->id, 'name' => $s->name]));
    const searchUrl = @json(route('admin.coach-services.search'));
    const cancelUrlTemplate = @json(route('admin.bookings.cancel.api', ['id' => '__ID__']));
    const bookUrl = @json(route('admin.bookings.store'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const bookingModalEl = document.getElementById('cs-booking-modal');
    const bookingFormEl = document.getElementById('cs-booking-form');
    const bookingSeatDisplay = document.getElementById('cs-booking-seat-display');
    const bookingFareInput = document.getElementById('cs-booking-fare');
    const bookingScheduleInput = document.getElementById('cs-booking-schedule-id');
    const bookingSeatInput = document.getElementById('cs-booking-seat');
    const bookingScheduleText = document.getElementById('cs-booking-schedule');
    const bookingNameInput = document.getElementById('cs-booking-passenger-name');
    const bookingPhoneInput = document.getElementById('cs-booking-passenger-phone');
    const bookingEmailInput = document.getElementById('cs-booking-passenger-email');
    const bookingPaymentInput = document.getElementById('cs-booking-payment-method');
    const bookingErrorEl = document.getElementById('cs-booking-error');
    let activeBookingSchedule = null;

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
                if (!schedule || ! schedule.seat_bookings || ! schedule.seat_bookings[seat]) return;

                selectedSeatBooking = { seat, ...schedule.seat_bookings[seat] };
                expandedScheduleId = scheduleId;
                renderResults();
            });
        });

        document.querySelectorAll('.seat:not(.booked)').forEach(seatEl => {
            seatEl.addEventListener('click', () => {
                const seat = seatEl.dataset.seat;
                const scheduleId = parseInt(seatEl.dataset.schedule, 10);
                const schedule = searchResults.find(s => s.id === scheduleId);
                if (!schedule) return;

                openBookingModal(scheduleId, seat, schedule);
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

    function openBookingModal(scheduleId, seat, schedule) {
        activeBookingSchedule = schedule;

        if (bookingScheduleInput) bookingScheduleInput.value = scheduleId;
        if (bookingSeatInput) bookingSeatInput.value = seat;
        if (bookingSeatDisplay) bookingSeatDisplay.value = seat;
        if (bookingFareInput) bookingFareInput.value = `BDT ${Number(schedule.fare).toLocaleString()}`;
        if (bookingScheduleText) bookingScheduleText.textContent = `${escapeHtml(schedule.route.from)} → ${escapeHtml(schedule.route.to)} • ${formatTime(schedule.departure_time)}`;
        if (bookingNameInput) bookingNameInput.value = '';
        if (bookingPhoneInput) bookingPhoneInput.value = '';
        if (bookingEmailInput) bookingEmailInput.value = '';
        if (bookingPaymentInput) bookingPaymentInput.value = 'Cash';
        if (bookingErrorEl) {
            bookingErrorEl.style.display = 'none';
            bookingErrorEl.textContent = '';
        }
        if (bookingModalEl) bookingModalEl.style.display = 'flex';
    }

    function closeBookingModal() {
        if (bookingModalEl) bookingModalEl.style.display = 'none';
    }

    async function handleBookingSubmit() {
        if (!activeBookingSchedule) return;
        if (!bookingFormEl || !bookingScheduleInput || !bookingSeatInput || !bookingNameInput || !bookingPhoneInput || !bookingEmailInput || !bookingPaymentInput) {
            return;
        }

        const payload = {
            schedule_id: bookingScheduleInput.value,
            passenger_name: bookingNameInput.value.trim(),
            passenger_phone: bookingPhoneInput.value.trim(),
            passenger_email: bookingEmailInput.value.trim(),
            seat_numbers: bookingSeatInput.value,
            payment_method: bookingPaymentInput.value,
            total_fare: activeBookingSchedule.fare,
            status: 'PAID'
        };

        if (!payload.passenger_name || !payload.passenger_phone || !payload.passenger_email) {
            if (bookingErrorEl) {
                bookingErrorEl.style.display = 'block';
                bookingErrorEl.textContent = 'Please fill in all passenger details before submitting.';
            }
            return;
        }

        try {
            const res = await fetch(bookUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (res.ok) {
                closeBookingModal();
                selectedSeatBooking = null;
                await fetchCoachServices(true);
                alert('Booking created successfully. SMS notification has been submitted.');
                return;
            }

            const data = await res.json().catch(() => ({}));
            if (bookingErrorEl) {
                bookingErrorEl.style.display = 'block';
                bookingErrorEl.textContent = data.message || 'Failed to create booking.';
            }
        } catch (err) {
            if (bookingErrorEl) {
                bookingErrorEl.style.display = 'block';
                bookingErrorEl.textContent = 'Network error while submitting the booking.';
            }
        }
    }

    document.getElementById('cs-booking-cancel')?.addEventListener('click', closeBookingModal);
    document.getElementById('cs-booking-modal-close')?.addEventListener('click', closeBookingModal);
    document.getElementById('cs-booking-submit')?.addEventListener('click', handleBookingSubmit);

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
