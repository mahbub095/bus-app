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
    const toggleBlockUrlTemplate = @json(route('admin.schedules.seats.toggle-block', ['id' => '__ID__']));
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
    let adminPassengerPhone = '';
    let adminPassengerName = '';
    let adminPassengerEmail = '';
    let adminGender = 'M';
    let adminPaymentMethod = 'Cash';
    let adminBlockMode = false;
    let pollTimer = null;
    let isFetching = false;
    let coachListEventsBound = false;

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
        const layout = schedule.bus?.seat_layout || '2+2';
        const totalSeats = schedule.bus?.total_seats || 36;
        const rowLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

        let seatCodes = [];
        if (layout === '1+2') {
            let seatsCount = 0;
            for (let r = 0; r < rowLetters.length; r++) {
                let row = rowLetters[r];
                for (let num = 1; num <= 3; num++) {
                    if (seatsCount >= totalSeats) break;
                    seatCodes.push(row + num);
                    seatsCount++;
                }
                if (seatsCount >= totalSeats) break;
            }
        } else if (layout === 'sleeper') {
            let lowerCount = Math.ceil(totalSeats / 2);
            let upperCount = totalSeats - lowerCount;

            // Lower deck
            let seatsCount = 0;
            for (let r = 0; r < rowLetters.length; r++) {
                let row = rowLetters[r];
                for (let num = 1; num <= 3; num++) {
                    if (seatsCount >= lowerCount) break;
                    seatCodes.push('L-' + row + num);
                    seatsCount++;
                }
                if (seatsCount >= lowerCount) break;
            }

            // Upper deck
            seatsCount = 0;
            for (let r = 0; r < rowLetters.length; r++) {
                let row = rowLetters[r];
                for (let num = 1; num <= 3; num++) {
                    if (seatsCount >= upperCount) break;
                    seatCodes.push('U-' + row + num);
                    seatsCount++;
                }
                if (seatsCount >= upperCount) break;
            }
        } else {
            // '2+2'
            let seatsCount = 0;
            for (let r = 0; r < rowLetters.length; r++) {
                let row = rowLetters[r];
                for (let num = 1; num <= 4; num++) {
                    if (seatsCount >= totalSeats) break;
                    seatCodes.push(row + num);
                    seatsCount++;
                }
                if (seatsCount >= totalSeats) break;
            }
        }

        seatCodes.forEach(code => {
            map[code] = booked.includes(code) ? 'sold_m' : 'available';
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

    function captureBookingFormState() {
        const phone = document.getElementById('cs-booking-phone');
        const name = document.getElementById('cs-booking-name');
        const email = document.getElementById('cs-booking-email');
        if (phone) adminPassengerPhone = phone.value;
        if (name) adminPassengerName = name.value;
        if (email) adminPassengerEmail = email.value;
        const boarding = document.getElementById('cs-boarding-point');
        const dropping = document.getElementById('cs-dropping-point');
        if (boarding) adminBoardingPoint = boarding.value;
        if (dropping) adminDroppingPoint = dropping.value;
        const gender = document.getElementById('cs-booking-gender');
        if (gender) adminGender = gender.value;
    }

    function renderPaymentToggles() {
        return ['Cash', 'bKash', 'Nagad', 'Card'].map(method => (
            `<button type="button" class="payment-toggle ${adminPaymentMethod === method ? 'active' : ''}" data-payment="${escapeHtml(method)}">${escapeHtml(method)}</button>`
        )).join('');
    }

    function renderBookingExtrasHtml(schedule) {
        const seatClass = schedule.seat_class || 'E-Class';
        const pricing = calcPricing(schedule, Math.max(1, adminSelectedSeats.length), adminPaymentMethod);
        const seatRows = adminSelectedSeats.length
            ? adminSelectedSeats.map(seat => `<tr><td>${escapeHtml(seat)}</td><td>${escapeHtml(seatClass)}</td><td>${formatBdt(schedule.fare)}</td></tr>`).join('')
            : `<tr><td colspan="3" style="color:#9ca3af; font-style:italic;">Select seat(s) from the map</td></tr>`;

        return `
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
            </div>`;
    }

    function updateBookingExtras(schedule) {
        const extrasEl = document.getElementById(`cs-booking-extras-${schedule.id}`);
        if (extrasEl) {
            extrasEl.innerHTML = renderBookingExtrasHtml(schedule);
        }
    }

    function refreshLiveSeatMapOnly() {
        if (!expandedScheduleId || selectedSeatBooking) {
            renderResults();
            return;
        }

        const sched = searchResults.find(s => s.id === expandedScheduleId);
        if (!sched) {
            renderResults();
            return;
        }

        const seatMapEl = document.getElementById(`cs-seat-map-${expandedScheduleId}`);
        if (!seatMapEl) {
            renderResults();
            return;
        }

        seatMapEl.innerHTML = renderSeatMap(sched);
        updateBookingExtras(sched);
    }

    function generateDefaultGridForServices(layout, totalSeats) {
        const rowLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        
        if (layout === '2+2_last5') {
            const grid = [];
            grid.push([
                { type: 'engine', label: 'Engine' },
                { type: 'empty' },
                { type: 'aisle' },
                { type: 'empty' },
                { type: 'driver', label: 'Driver' }
            ]);

            let remainingSeats = totalSeats - 5;
            let normalRows = Math.ceil(remainingSeats / 4);
            
            let seatsCount = 0;
            let rIndex = 0;
            for (let r = 0; r < normalRows; r++) {
                const rowLetter = rowLetters[rIndex++];
                const row = [];
                row.push({ type: 'seat', label: rowLetter + '1' });
                row.push({ type: 'seat', label: rowLetter + '2' });
                row.push({ type: 'aisle' });
                row.push({ type: 'seat', label: rowLetter + '3' });
                row.push({ type: 'seat', label: rowLetter + '4' });
                grid.push(row);
            }

            const lastRowLetter = rowLetters[rIndex++];
            const lastRow = [];
            for (let num = 1; num <= 5; num++) {
                lastRow.push({ type: 'seat', label: lastRowLetter + num });
            }
            grid.push(lastRow);
            
            return grid;
        } else if (layout === '1+2') {
            const grid = [];
            grid.push([
                { type: 'engine', label: 'Engine' },
                { type: 'aisle' },
                { type: 'empty' },
                { type: 'driver', label: 'Driver' }
            ]);

            let normalRows = Math.ceil(totalSeats / 3);
            let rIndex = 0;
            for (let r = 0; r < normalRows; r++) {
                const rowLetter = rowLetters[rIndex++];
                const row = [];
                row.push({ type: 'seat', label: rowLetter + '1' });
                row.push({ type: 'aisle' });
                row.push({ type: 'seat', label: rowLetter + '2' });
                row.push({ type: 'seat', label: rowLetter + '3' });
                grid.push(row);
            }
            return grid;
        } else if (layout === 'sleeper') {
            const lowerCount = Math.ceil(totalSeats / 2);
            const upperCount = totalSeats - lowerCount;
            
            const lowerGrid = [];
            lowerGrid.push([
                { type: 'engine', label: 'Engine' },
                { type: 'aisle' },
                { type: 'empty' },
                { type: 'driver', label: 'Driver' }
            ]);
            let remainingSeats = lowerCount - 4;
            let lowerRows = Math.ceil(remainingSeats / 3);
            if (lowerRows < 0) lowerRows = 0;
            let rIndex = 0;
            for (let r = 0; r < lowerRows; r++) {
                const rowLetter = rowLetters[rIndex++];
                const row = [];
                row.push({ type: 'seat', label: 'L-' + rowLetter + '1' });
                row.push({ type: 'aisle' });
                row.push({ type: 'seat', label: 'L-' + rowLetter + '2' });
                row.push({ type: 'seat', label: 'L-' + rowLetter + '3' });
                lowerGrid.push(row);
            }
            const lastRowLetter = rowLetters[rIndex++];
            const lastRow = [];
            for (let num = 1; num <= 4; num++) {
                lastRow.push({ type: 'seat', label: 'L-' + lastRowLetter + num });
            }
            lowerGrid.push(lastRow);

            const upperGrid = [];
            upperGrid.push([
                { type: 'empty' },
                { type: 'aisle' },
                { type: 'empty' },
                { type: 'empty' }
            ]);
            let remainingSeatsU = upperCount - 4;
            let upperRows = Math.ceil(remainingSeatsU / 3);
            if (upperRows < 0) upperRows = 0;
            rIndex = 0;
            for (let r = 0; r < upperRows; r++) {
                const rowLetter = rowLetters[rIndex++];
                const row = [];
                row.push({ type: 'seat', label: 'U-' + rowLetter + '1' });
                row.push({ type: 'aisle' });
                row.push({ type: 'seat', label: 'U-' + rowLetter + '2' });
                row.push({ type: 'seat', label: 'U-' + rowLetter + '3' });
                upperGrid.push(row);
            }
            const lastRowLetterU = rowLetters[rIndex++];
            const lastRowU = [];
            for (let num = 1; num <= 4; num++) {
                lastRowU.push({ type: 'seat', label: 'U-' + lastRowLetterU + num });
            }
            upperGrid.push(lastRowU);

            return { lower: lowerGrid, upper: upperGrid };
        }
        
        // Fallback default 2+2
        const grid = [];
        grid.push([
            { type: 'engine', label: 'Engine' },
            { type: 'empty' },
            { type: 'aisle' },
            { type: 'empty' },
            { type: 'driver', label: 'Driver' }
        ]);
        let normalRows = Math.ceil(totalSeats / 4);
        let rIndex = 0;
        for (let r = 0; r < normalRows; r++) {
            const rowLetter = rowLetters[rIndex++];
            const row = [];
            row.push({ type: 'seat', label: rowLetter + '1' });
            row.push({ type: 'seat', label: rowLetter + '2' });
            row.push({ type: 'aisle' });
            row.push({ type: 'seat', label: rowLetter + '3' });
            row.push({ type: 'seat', label: rowLetter + '4' });
            grid.push(row);
        }
        return grid;
    }

    function renderSeatMap(schedule) {
        const seatMap = getSeatMap(schedule);
        const layout = schedule.bus?.seat_layout || '2+2';
        const totalSeats = schedule.bus?.total_seats || 36;
        
        let grid = schedule.bus?.seat_layout_grid;
        if (typeof grid === 'string') {
            try {
                grid = JSON.parse(grid);
            } catch (e) {
                grid = null;
            }
        }
        if (!grid || typeof grid !== 'object') {
            grid = generateDefaultGridForServices(layout, totalSeats);
        }

        const isSleeper = grid.lower !== undefined;

        function renderDeckHtml(deckGrid, hasDriver = false) {
            let driverCell = null;
            let engineCell = null;

            if (hasDriver) {
                deckGrid.forEach(row => {
                    row.forEach(cell => {
                        if (cell.type === 'driver') driverCell = cell;
                        if (cell.type === 'engine') engineCell = cell;
                    });
                });
            }

            let engineHtml = engineCell ? `<div class="seat status-engine" title="Engine cover" style="cursor:not-allowed; background-color:#374151; border-color:#1f2937; color:#9ca3af; font-size:9px; font-weight:bold; display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px;">ENG</div>` : `<div style="width:36px;"></div>`;
            let entranceHtml = `<span style="font-size: 11px; color: var(--text-muted); font-weight: bold;">${hasDriver ? 'ENTRANCE' : 'UPPER DECK FRONT'}</span>`;
            let driverHtml = driverCell ? `<div class="seat status-driver" title="Driver Seat" style="cursor:not-allowed; background-color:#10B981; border-color:#059669; color:#fff; display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2.5"/>
                    <circle cx="12" cy="12" r="3.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>
                    <line x1="12" y1="8.5" x2="12" y2="2.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <line x1="9" y1="14" x2="4" y2="18.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <line x1="15" y1="14" x2="20" y2="18.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>` : `<div style="width:36px;"></div>`;

            let html = `
                <div class="bus-blueprint">
                    <div class="bus-head" style="display:flex; justify-content:space-between; align-items:center; padding-bottom:16px; border-bottom:2px dashed #2A2A44; margin-bottom:20px;">
                        ${engineHtml}
                        ${entranceHtml}
                        ${driverHtml}
                    </div>
                    <div class="bus-body-seats">`;

            deckGrid.forEach(row => {
                if (row.some(cell => cell.type === 'driver' || cell.type === 'engine')) {
                    return;
                }
                html += `<div class="seat-row">`;
                
                row.forEach(cell => {
                    if (cell.type === 'seat') {
                        html += renderSeatCell(schedule, cell.label, seatMap);
                    } else if (cell.type === 'aisle') {
                        html += `<div class="bus-aisle"></div>`;
                    } else {
                        html += `<div class="seat seat-placeholder"></div>`;
                    }
                });
                
                html += `</div>`;
            });

            html += `</div></div>`;
            return html;
        }

        let finalHtml = '';
        if (isSleeper) {
            finalHtml = `
                <div class="sleeper-decks">
                    <div>
                        <div class="deck-title">Lower Deck</div>
                        ${renderDeckHtml(grid.lower, true)}
                    </div>
                    <div>
                        <div class="deck-title">Upper Deck</div>
                        ${renderDeckHtml(grid.upper, false)}
                    </div>
                </div>
                <div class="seat-legend" style="margin-top: 20px;">${renderSeatLegend()}</div>
            `;
        } else {
            finalHtml = `${renderDeckHtml(grid, true)}
                         <div class="seat-legend">${renderSeatLegend()}</div>`;
        }

        return finalHtml;
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
        const canManageBlock = status === 'blocked' || (adminBlockMode && status === 'available');
        const selectable = isPicking
            || (status === 'available' && !adminBlockMode)
            || canManageBlock
            || (isViewing && status !== 'available' && status !== 'blocked');
        let titleStatus = isPicking ? 'selected' : status;
        if (status === 'blocked') {
            titleStatus = adminBlockMode ? 'Blocked — click to unblock' : 'Blocked';
        } else if (adminBlockMode && status === 'available') {
            titleStatus = 'Available — click to block';
        }
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

        return `
            <div class="booking-form-sidebar">
                <div class="ticket-booking-panel">
                    <h3>Boarding / Dropping Point</h3>
                    <label>Boarding Point *</label>
                    <select id="cs-boarding-point" class="ticket-field">${boardingOpts}</select>
                    <p class="boarding-point-info" id="cs-boarding-info">${selectedBoarding ? `Reporting: ${escapeHtml(selectedBoarding.reporting_time || '—')} · Departure: ${escapeHtml(selectedBoarding.departure_time || '—')}` : 'Select a boarding point'}</p>
                    <label>Dropping Point *</label>
                    <select id="cs-dropping-point" class="ticket-field">
                        <option value="">Select dropping point</option>
                        ${droppingOpts}
                    </select>
                    <p class="boarding-point-info" id="cs-dropping-info">${selectedDropping ? `Estimated arrival: ${escapeHtml(selectedDropping.arrival_time || '—')}` : 'Select a dropping point'}</p>
                    <label>Mobile Number *</label>
                    <input type="tel" id="cs-booking-phone" class="ticket-field" placeholder="01XXXXXXXXX" autocomplete="tel" value="${escapeHtml(adminPassengerPhone)}">
                    <label>Passenger Name *</label>
                    <input type="text" id="cs-booking-name" class="ticket-field" placeholder="Full name" autocomplete="name" value="${escapeHtml(adminPassengerName)}">
                    <label>Email *</label>
                    <input type="email" id="cs-booking-email" class="ticket-field" placeholder="email@example.com" autocomplete="email" value="${escapeHtml(adminPassengerEmail)}">
                    <label>Gender</label>
                    <select id="cs-booking-gender" class="ticket-field">
                        <option value="M" ${adminGender === 'M' ? 'selected' : ''}>Male</option>
                        <option value="F" ${adminGender === 'F' ? 'selected' : ''}>Female</option>
                    </select>
                    <label>Payment Method</label>
                    <div class="payment-toggle-group" id="cs-payment-toggles">${renderPaymentToggles()}</div>
                    <div id="cs-booking-error" style="color:#dc2626; font-size:13px; display:none; margin-bottom:8px;"></div>
                    <button type="button" class="btn-ticket-submit" id="cs-booking-submit">Submit</button>
                    <div id="cs-booking-extras-${schedule.id}">${renderBookingExtrasHtml(schedule)}</div>
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
                                    <h3 style="font-size: 14px; margin-bottom: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px;">
                                        Bus Seat Layout
                                    </h3>
                                    <div class="seat-map-toolbar">
                                        <button type="button" class="btn btn-secondary cs-block-mode-toggle ${adminBlockMode ? 'active' : ''}"
                                            data-schedule-id="${sched.id}" style="padding: 6px 12px; font-size: 12px;">
                                            ${adminBlockMode ? 'Done blocking' : 'Manage blocked seats'}
                                        </button>
                                        <span class="seat-map-toolbar-hint">
                                            ${adminBlockMode
                                                ? 'Click an available seat to block it, or a grey blocked seat to unblock.'
                                                : 'Only admins can block seats. Customers cannot book blocked seats.'}
                                        </span>
                                    </div>
                                    <div id="cs-seat-map-${sched.id}">${renderSeatMap(sched)}</div>
                                </div>
                                <div id="cs-sidebar-${sched.id}">
                                    ${renderBookingSidebar(sched)}
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>`;
        }).join('');

        bindCoachListEvents();
    }

    function bindCoachListEvents() {
        const listEl = document.getElementById('cs-bus-list');
        if (!listEl || coachListEventsBound) return;
        coachListEventsBound = true;

        listEl.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('.cs-toggle-map');
            if (toggleBtn) {
                captureBookingFormState();
                const id = parseInt(toggleBtn.dataset.id, 10);
                if (expandedScheduleId === id) {
                    expandedScheduleId = null;
                    selectedSeatBooking = null;
                    adminSelectedSeats = [];
                    adminBlockMode = false;
                } else {
                    expandedScheduleId = id;
                    selectedSeatBooking = null;
                    adminSelectedSeats = [];
                    adminBlockMode = false;
                    adminPassengerPhone = '';
                    adminPassengerName = '';
                    adminPassengerEmail = '';
                    const sched = searchResults.find(s => s.id === id);
                    if (sched?.boarding_points?.[0]) adminBoardingPoint = sched.boarding_points[0].value;
                    adminDroppingPoint = '';
                }
                renderResults();
                return;
            }

            const paymentBtn = e.target.closest('.payment-toggle[data-payment]');
            if (paymentBtn) {
                captureBookingFormState();
                adminPaymentMethod = paymentBtn.dataset.payment;
                document.querySelectorAll('#cs-payment-toggles .payment-toggle').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.payment === adminPaymentMethod);
                });
                const schedule = searchResults.find(s => s.id === expandedScheduleId);
                if (schedule) updateBookingExtras(schedule);
                return;
            }

            if (e.target.id === 'cs-booking-submit') {
                const schedule = searchResults.find(s => s.id === expandedScheduleId);
                if (schedule) handleBookingSubmit(schedule);
                return;
            }

            if (e.target.id === 'cs-cancel-booking-btn' && selectedSeatBooking) {
                handleCancelBooking(selectedSeatBooking.booking_id);
                return;
            }

            if (e.target.id === 'cs-back-to-book-btn') {
                selectedSeatBooking = null;
                renderResults();
                return;
            }

            const blockModeBtn = e.target.closest('.cs-block-mode-toggle');
            if (blockModeBtn) {
                const schedId = parseInt(blockModeBtn.dataset.scheduleId, 10);
                if (expandedScheduleId === schedId) {
                    adminBlockMode = !adminBlockMode;
                    if (adminBlockMode) {
                        adminSelectedSeats = [];
                    }
                    renderResults();
                }
                return;
            }

            const seatEl = e.target.closest('.seat[data-seat]');
            if (!seatEl) return;

            captureBookingFormState();
            const seat = seatEl.dataset.seat;
            const status = seatEl.dataset.status;
            const scheduleId = parseInt(seatEl.dataset.schedule, 10);
            const schedule = searchResults.find(s => s.id === scheduleId);
            if (!schedule) return;

            if (status === 'blocked' || (adminBlockMode && status === 'available')) {
                if (!adminBlockMode && status === 'blocked') {
                    alert('Turn on "Manage blocked seats" to unblock this seat.');
                    return;
                }
                toggleSeatBlock(scheduleId, seat);
                return;
            }

            if (status !== 'available') {
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
            refreshLiveSeatMapOnly();
        });

        listEl.addEventListener('input', (e) => {
            if (e.target.id === 'cs-booking-phone') adminPassengerPhone = e.target.value;
            if (e.target.id === 'cs-booking-name') adminPassengerName = e.target.value;
            if (e.target.id === 'cs-booking-email') adminPassengerEmail = e.target.value;
        });

        listEl.addEventListener('change', (e) => {
            if (e.target.id === 'cs-boarding-point') {
                adminBoardingPoint = e.target.value;
                const schedule = searchResults.find(s => s.id === expandedScheduleId);
                const bp = schedule?.boarding_points?.find(p => p.value === adminBoardingPoint);
                const info = document.getElementById('cs-boarding-info');
                if (info) {
                    info.textContent = bp
                        ? `Reporting: ${bp.reporting_time || '—'} · Departure: ${bp.departure_time || '—'}`
                        : 'Select a boarding point';
                }
            }
            if (e.target.id === 'cs-dropping-point') {
                adminDroppingPoint = e.target.value;
                const schedule = searchResults.find(s => s.id === expandedScheduleId);
                const dp = schedule?.dropping_points?.find(p => p.value === adminDroppingPoint);
                const info = document.getElementById('cs-dropping-info');
                if (info) {
                    info.textContent = dp
                        ? `Estimated arrival: ${dp.arrival_time || '—'}`
                        : 'Select a dropping point';
                }
            }
            if (e.target.id === 'cs-booking-gender') {
                adminGender = e.target.value;
            }
        });
    }

    async function toggleSeatBlock(scheduleId, seat) {
        const url = toggleBlockUrlTemplate.replace('__ID__', String(scheduleId));

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ seat }),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                alert(data.message || 'Could not update blocked seat.');
                return;
            }

            const sched = searchResults.find(s => s.id === scheduleId);
            if (sched && data.seat_map) {
                sched.seat_map = data.seat_map;
                sched.booked_seats = data.booked_seats || sched.booked_seats;
                sched.available_seats_count = data.available_seats_count ?? sched.available_seats_count;
                adminSelectedSeats = adminSelectedSeats.filter(s => (data.seat_map[s] || 'available') === 'available');
            }

            captureBookingFormState();
            renderResults();
        } catch (err) {
            alert('Network error while updating blocked seat.');
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

                if (silent && expandedScheduleId && !selectedSeatBooking) {
                    refreshLiveSeatMapOnly();
                } else {
                    captureBookingFormState();
                    renderResults();
                }
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
        adminPassengerPhone = '';
        adminPassengerName = '';
        adminPassengerEmail = '';
        adminDroppingPoint = '';

        document.getElementById('cs-empty-hint').style.display = 'none';
        document.getElementById('cs-results').style.display = 'block';

        await fetchCoachServices();
        startPolling();
    }

    async function handleBookingSubmit(schedule) {
        captureBookingFormState();
        const errorEl = document.getElementById('cs-booking-error');
        const name = adminPassengerName.trim();
        const phone = adminPassengerPhone.trim();
        const email = adminPassengerEmail.trim();
        const boarding = adminBoardingPoint;
        const dropping = adminDroppingPoint;
        const gender = adminGender;
        const payment = adminPaymentMethod;

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
                adminPassengerPhone = '';
                adminPassengerName = '';
                adminPassengerEmail = '';
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
    bindCoachListEvents();

    window.coachServicesModule = { startPolling, stopPolling };
})();
</script>
