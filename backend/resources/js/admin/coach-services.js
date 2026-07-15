/**
 * coach-services.js
 *
 * Admin Coach Services panel — search schedules, view live seat maps, book
 * tickets on behalf of passengers, block/unblock individual seats, and cancel
 * existing bookings, with 5-second live polling while the tab is open.
 *
 * Data contract (set in coach-services.blade.php before this file loads):
 *   window.CoachServices.stations
 *   window.CoachServices.searchUrl
 *   window.CoachServices.cancelUrlTemplate
 *   window.CoachServices.toggleBlockUrlTemplate
 *   window.CoachServices.bookUrl
 *
 * Public API (consumed by layout.js):
 *   window.coachServicesModule.startPolling()
 *   window.coachServicesModule.stopPolling()
 */

(function () {
    const { stations, searchUrl, cancelUrlTemplate, toggleBlockUrlTemplate, bookUrl } = window.CoachServices;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // ─── Module state ──────────────────────────────────────────────────────────
    let searchParams        = { from: '', to: '', date: '', coachType: 'All' };
    let searchResults       = [];
    let searchDone          = false;
    let expandedScheduleId  = null;
    let selectedSeatBooking = null;
    let adminSelectedSeats  = [];
    let adminBoardingPoint  = '';
    let adminDroppingPoint  = '';
    let adminPassengerPhone = '';
    let adminPassengerName  = '';
    let adminPassengerEmail = '';
    let adminGender         = 'M';
    let adminPaymentMethod  = 'Cash';
    let adminBlockMode      = false;
    let pollTimer           = null;
    let isFetching          = false;
    let coachListEventsBound = false;
    let lastFetchedTimeText = '';

    const dateInput = document.getElementById('cs-date');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }

    // ─── Inline seat styles (bypasses all CSS specificity issues) ─────────────
    // Using inline style= so nothing — Tailwind, cascade order, !important rules
    // from other selectors — can override the selected green colour.
    const SEAT_STYLES = {
        selected:  'background:#22c55e;border-color:#16a34a;color:#fff;box-shadow:0 0 10px rgba(34,197,94,.45);cursor:pointer;',
        available: 'background:#fff;border-color:#d1d5db;color:#374151;cursor:pointer;',
        blocked:   'background:#6b7280;border-color:#4b5563;color:#f3f4f6;cursor:not-allowed;opacity:.85;',
        booked_m:  'background:#fecaca;border-color:#f87171;color:#7f1d1d;cursor:pointer;',
        booked_f:  'background:#fdf4ff;border-color:#f0abfc;color:#a21caf;cursor:pointer;',
        sold_m:    'background:#ef4444;border-color:#b91c1c;color:#fff;cursor:pointer;',
        sold_f:    'background:#ec4899;border-color:#be185d;color:#fff;cursor:pointer;',
    };

    // ─── General helpers ───────────────────────────────────────────────────────
    const stationName = id => stations.find(s => s.id === parseInt(id, 10))?.name || '';

    const formatTime = iso => iso
        ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : '';

    function escapeHtml(str) {
        const el = document.createElement('div');
        el.textContent = str ?? '';
        return el.innerHTML;
    }

    const formatBdt = amount =>
        '৳ ' + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // ─── Seat map helpers ──────────────────────────────────────────────────────
    function getSeatMap(schedule) {
        if (schedule.seat_map) return schedule.seat_map;
        const booked  = schedule.booked_seats || [];
        const layout  = schedule.bus?.seat_layout || '2+2';
        const total   = schedule.bus?.total_seats || 36;
        const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
        const codes   = [];
        if (layout === '1+2') {
            outer: for (const r of LETTERS) { for (let n = 1; n <= 3; n++) { if (codes.length >= total) break outer; codes.push(r + n); } }
        } else if (layout === 'sleeper') {
            const lc = Math.ceil(total / 2), uc = total - lc;
            let cnt = 0;
            outer: for (const r of LETTERS) { for (let n = 1; n <= 3; n++) { if (cnt >= lc) break outer; codes.push(`L-${r}${n}`); cnt++; } }
            cnt = 0;
            outer: for (const r of LETTERS) { for (let n = 1; n <= 3; n++) { if (cnt >= uc) break outer; codes.push(`U-${r}${n}`); cnt++; } }
        } else {
            outer: for (const r of LETTERS) { for (let n = 1; n <= 4; n++) { if (codes.length >= total) break outer; codes.push(r + n); } }
        }
        const map = {};
        codes.forEach(code => { map[code] = booked.includes(code) ? 'sold_m' : 'available'; });
        return map;
    }

    function calcPricing(schedule, seatCount, paymentMethod) {
        const fare = Number(schedule.fare || 0);
        const p    = schedule.pricing || {};
        const gw   = String(paymentMethod).toLowerCase() !== 'cash';
        const sf   = fare * seatCount;
        const sc   = (p.service_charge ?? 20) * seatCount;
        const gc   = gw ? (p.gateway_charge ?? 16) * seatCount : 0;
        const scd  = (p.sc_discount ?? 20) * seatCount;
        const gcd  = gw ? (p.gc_discount ?? 16) * seatCount : 0;
        return { seatFare: sf, serviceCharge: sc, gatewayCharge: gc, scDiscount: scd, gcDiscount: gcd, total: Math.max(0, sf + sc + gc - scd - gcd) };
    }

    const seatStatusLabel = status => ({ available:'Available', selected:'Selected', blocked:'Blocked', booked_m:'Booked (M)', booked_f:'Booked (F)', sold_m:'Sold (M)', sold_f:'Sold (F)' }[status] || status);

    function renderSeatLegend() {
        return [['booked_m','Booked (M)'],['booked_f','Booked (F)'],['blocked','Blocked'],['available','Available'],['selected','Selected'],['sold_m','Sold (M)'],['sold_f','Sold (F)']]
            .map(([key,label]) => `<div class="legend-item"><div class="legend-dot status-${key}"></div><span>${label}</span></div>`).join('');
    }

    // ─── Seat cell rendering ───────────────────────────────────────────────────
    function renderSeatCell(schedule, seatCode, seatMap) {
        const status    = seatMap[seatCode] || 'available';
        const isPicking = adminSelectedSeats.includes(seatCode) && status === 'available';
        const isViewing = selectedSeatBooking?.seat === seatCode;

        const baseStyle   = isPicking ? SEAT_STYLES.selected : (SEAT_STYLES[status] || SEAT_STYLES.available);
        const viewOutline = isViewing ? 'outline:3px solid #6366f1;outline-offset:2px;z-index:1;' : '';

        let title = isPicking ? 'selected' : status;
        if (status === 'blocked')                         title = adminBlockMode ? 'Blocked — click to unblock' : 'Blocked';
        else if (adminBlockMode && status === 'available') title = 'Available — click to block';

        return `<div class="seat selectable"
                     style="${baseStyle}${viewOutline}"
                     data-seat="${seatCode}"
                     data-schedule="${schedule.id}"
                     data-status="${status}"
                     title="${seatStatusLabel(title)}">${seatCode}</div>`;
    }

    // ─── Grid generation ───────────────────────────────────────────────────────
    function generateDefaultGrid(layout, totalSeats) {
        const L = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
        if (layout === '2+2_last5') {
            const grid = [[{type:'engine',label:'Engine'},{type:'empty'},{type:'aisle'},{type:'empty'},{type:'driver',label:'Driver'}]];
            const nr = Math.ceil((totalSeats - 5) / 4);
            for (let r = 0; r < nr; r++) grid.push([{type:'seat',label:`${L[r]}1`},{type:'seat',label:`${L[r]}2`},{type:'aisle'},{type:'seat',label:`${L[r]}3`},{type:'seat',label:`${L[r]}4`}]);
            grid.push([1,2,3,4,5].map(n => ({type:'seat',label:`${L[nr]}${n}`})));
            return grid;
        }
        if (layout === '1+2') {
            const grid = [[{type:'engine',label:'Engine'},{type:'aisle'},{type:'empty'},{type:'driver',label:'Driver'}]];
            const rows = Math.ceil(totalSeats / 3);
            for (let r = 0; r < rows; r++) grid.push([{type:'seat',label:`${L[r]}1`},{type:'aisle'},{type:'seat',label:`${L[r]}2`},{type:'seat',label:`${L[r]}3`}]);
            return grid;
        }
        if (layout === 'sleeper') {
            const lc = Math.ceil(totalSeats / 2), uc = totalSeats - lc;
            function sleeperDeck(count, prefix, hasDriver) {
                const deck = [hasDriver ? [{type:'engine',label:'Engine'},{type:'aisle'},{type:'empty'},{type:'driver',label:'Driver'}] : [{type:'empty'},{type:'aisle'},{type:'empty'},{type:'empty'}]];
                const nr = Math.max(0, Math.ceil((count - 4) / 3));
                for (let r = 0; r < nr; r++) deck.push([{type:'seat',label:`${prefix}${L[r]}1`},{type:'aisle'},{type:'seat',label:`${prefix}${L[r]}2`},{type:'seat',label:`${prefix}${L[r]}3`}]);
                deck.push([1,2,3,4].map(n => ({type:'seat',label:`${prefix}${L[nr]}${n}`})));
                return deck;
            }
            return { lower: sleeperDeck(lc, 'L-', true), upper: sleeperDeck(uc, 'U-', false) };
        }
        const grid = [[{type:'engine',label:'Engine'},{type:'empty'},{type:'aisle'},{type:'empty'},{type:'driver',label:'Driver'}]];
        const rows = Math.ceil(totalSeats / 4);
        for (let r = 0; r < rows; r++) grid.push([{type:'seat',label:`${L[r]}1`},{type:'seat',label:`${L[r]}2`},{type:'aisle'},{type:'seat',label:`${L[r]}3`},{type:'seat',label:`${L[r]}4`}]);
        return grid;
    }

    const DRIVER_SVG = `<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2.5"/><circle cx="12" cy="12" r="3.5" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="1" fill="currentColor"/><line x1="12" y1="8.5" x2="12" y2="2.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="9" y1="14" x2="4" y2="18.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="15" y1="14" x2="20" y2="18.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>`;

    function renderSeatMap(schedule) {
        const seatMap    = getSeatMap(schedule);
        const layout     = schedule.bus?.seat_layout || '2+2';
        const totalSeats = schedule.bus?.total_seats || 36;
        let grid = schedule.bus?.seat_layout_grid;
        if (typeof grid === 'string') { try { grid = JSON.parse(grid); if (typeof grid === 'string') grid = JSON.parse(grid); } catch { grid = null; } }
        if (!grid || typeof grid !== 'object') grid = generateDefaultGrid(layout, totalSeats);
        const isSleeper = grid.lower !== undefined;

        function renderDeckHtml(deckGrid, hasDriver) {
            let driverCell = null, engineCell = null;
            if (hasDriver) deckGrid.forEach(row => row.forEach(c => { if (c.type === 'driver') driverCell = c; if (c.type === 'engine') engineCell = c; }));
            const engineHtml = engineCell
                ? `<div class="seat" style="cursor:not-allowed;background:#374151;border-color:#1f2937;color:#9ca3af;font-size:9px;font-weight:bold;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;">ENG</div>`
                : `<div style="width:36px;"></div>`;
            const driverHtml = driverCell
                ? `<div class="seat" style="cursor:not-allowed;background:#10B981;border-color:#059669;color:#fff;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;">${DRIVER_SVG}</div>`
                : `<div style="width:36px;"></div>`;
            let html = `<div class="bus-blueprint"><div class="bus-head" style="display:flex;justify-content:space-between;align-items:center;padding-bottom:16px;border-bottom:2px dashed #2A2A44;margin-bottom:20px;">${engineHtml}<span style="font-size:11px;color:var(--text-muted);font-weight:bold;">${hasDriver ? 'ENTRANCE' : 'UPPER DECK FRONT'}</span>${driverHtml}</div><div class="bus-body-seats">`;
            deckGrid.forEach(row => {
                if (row.some(c => c.type === 'driver' || c.type === 'engine')) return;
                html += `<div class="seat-row">`;
                row.forEach(cell => {
                    if      (cell.type === 'seat')  html += renderSeatCell(schedule, cell.label, seatMap);
                    else if (cell.type === 'aisle') html += `<div class="bus-aisle"></div>`;
                    else                            html += `<div class="seat seat-placeholder"></div>`;
                });
                html += `</div>`;
            });
            html += `</div></div>`;
            return html;
        }

        if (isSleeper) {
            return `<div class="sleeper-decks"><div><div class="deck-title">Lower Deck</div>${renderDeckHtml(grid.lower, true)}</div><div><div class="deck-title">Upper Deck</div>${renderDeckHtml(grid.upper, false)}</div></div><div class="seat-legend" style="margin-top:20px;">${renderSeatLegend()}</div>`;
        }
        return `${renderDeckHtml(grid, true)}<div class="seat-legend">${renderSeatLegend()}</div>`;
    }

    // ─── Booking form helpers ──────────────────────────────────────────────────
    function captureBookingFormState() {
        const g = id => document.getElementById(id);
        const p = g('cs-booking-phone'), n = g('cs-booking-name'), e = g('cs-booking-email'),
              b = g('cs-boarding-point'), d = g('cs-dropping-point'), gen = g('cs-booking-gender');
        if (p)   adminPassengerPhone = p.value;
        if (n)   adminPassengerName  = n.value;
        if (e)   adminPassengerEmail = e.value;
        if (b)   adminBoardingPoint  = b.value;
        if (d)   adminDroppingPoint  = d.value;
        if (gen) adminGender         = gen.value;
    }

    function renderPaymentToggles() {
        return ['Cash','bKash','Nagad','Card'].map(m =>
            `<button type="button" class="payment-toggle ${adminPaymentMethod === m ? 'active' : ''}" data-payment="${escapeHtml(m)}">${escapeHtml(m)}</button>`
        ).join('');
    }

    function renderBookingExtrasHtml(schedule) {
        const seatClass = schedule.seat_class || 'E-Class';
        const pricing   = calcPricing(schedule, Math.max(1, adminSelectedSeats.length), adminPaymentMethod);
        const seatRows  = adminSelectedSeats.length
            ? adminSelectedSeats.map(s => `<tr><td>${escapeHtml(s)}</td><td>${escapeHtml(seatClass)}</td><td>${formatBdt(schedule.fare)}</td></tr>`).join('')
            : `<tr><td colspan="3" style="color:#9ca3af;font-style:italic;">Select seat(s) from the map</td></tr>`;
        return `<h4 style="margin-top:20px;">Seat Information</h4>
            <table class="seat-info-table"><thead><tr><th>Seats</th><th>Class</th><th>Fare</th></tr></thead><tbody>${seatRows}</tbody></table>
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
        const el = document.getElementById(`cs-booking-extras-${schedule.id}`);
        if (el) el.innerHTML = renderBookingExtrasHtml(schedule);
    }

    // ─── Booking sidebar ───────────────────────────────────────────────────────
    function renderBookingSidebar(schedule) {
        if (selectedSeatBooking) {
            const b = selectedSeatBooking;
            return `<div class="booking-form-sidebar">
                <h3 class="booking-summary-title">Seat Booking Details</h3>
                <div class="summary-row"><span class="summary-label">Seat</span><span class="summary-value"><span class="selected-seats-badge">${escapeHtml(b.seat)}</span></span></div>
                <div class="summary-row"><span class="summary-label">PNR</span><span class="summary-value" style="color:var(--primary);font-weight:bold;">${escapeHtml(b.pnr)}</span></div>
                <div class="summary-row"><span class="summary-label">Passenger</span><span class="summary-value">${escapeHtml(b.passenger_name)}</span></div>
                <div class="summary-row"><span class="summary-label">Phone</span><span class="summary-value">${escapeHtml(b.passenger_phone)}</span></div>
                <div class="summary-row"><span class="summary-label">Fare Paid</span><span class="summary-value" style="color:var(--gold);">${formatBdt(b.total_fare)}</span></div>
                <button type="button" class="btn btn-danger" id="cs-cancel-booking-btn" style="margin-top:16px;width:100%;height:42px;">Cancel This Booking</button>
                <button type="button" class="btn btn-secondary" id="cs-back-to-book-btn" style="margin-top:8px;width:100%;">Book New Seat</button>
            </div>`;
        }

        const boardingOpts = (schedule.boarding_points || []).map(bp =>
            `<option value="${escapeHtml(bp.value)}" ${adminBoardingPoint === bp.value ? 'selected' : ''}>${escapeHtml(bp.label)} — Reporting: ${escapeHtml(bp.reporting_time || '—')}, Departure: ${escapeHtml(bp.departure_time || '—')}</option>`).join('');
        const droppingOpts = (schedule.dropping_points || []).map(dp =>
            `<option value="${escapeHtml(dp.value)}" ${adminDroppingPoint === dp.value ? 'selected' : ''}>${escapeHtml(dp.label)} — Arrival: ${escapeHtml(dp.arrival_time || '—')}</option>`).join('');
        const selBp = (schedule.boarding_points || []).find(bp => bp.value === adminBoardingPoint);
        const selDp = (schedule.dropping_points || []).find(dp => dp.value === adminDroppingPoint);
        const bpInfo = selBp ? `Reporting: ${escapeHtml(selBp.reporting_time||'—')} · Departure: ${escapeHtml(selBp.departure_time||'—')}` : 'Select a boarding point';
        const dpInfo = selDp ? `Estimated arrival: ${escapeHtml(selDp.arrival_time||'—')}` : 'Select a dropping point';

        return `<div class="booking-form-sidebar"><div class="ticket-booking-panel">
            <h3>Boarding / Dropping Point</h3>
            <label>Boarding Point *</label>
            <select id="cs-boarding-point" class="ticket-field">${boardingOpts}</select>
            <p class="boarding-point-info" id="cs-boarding-info">${bpInfo}</p>
            <label>Dropping Point *</label>
            <select id="cs-dropping-point" class="ticket-field"><option value="">Select dropping point</option>${droppingOpts}</select>
            <p class="boarding-point-info" id="cs-dropping-info">${dpInfo}</p>
            <label>Mobile Number *</label>
            <input type="tel" id="cs-booking-phone" class="ticket-field" placeholder="01XXXXXXXXX" autocomplete="tel" value="${escapeHtml(adminPassengerPhone)}">
            <label>Passenger Name *</label>
            <input type="text" id="cs-booking-name" class="ticket-field" placeholder="Full name" autocomplete="name" value="${escapeHtml(adminPassengerName)}">
            <label>Email *</label>
            <input type="email" id="cs-booking-email" class="ticket-field" placeholder="email@example.com" autocomplete="email" value="${escapeHtml(adminPassengerEmail)}">
            <label>Gender</label>
            <select id="cs-booking-gender" class="ticket-field">
                <option value="M" ${adminGender==='M'?'selected':''}>Male</option>
                <option value="F" ${adminGender==='F'?'selected':''}>Female</option>
            </select>
            <label>Payment Method</label>
            <div class="payment-toggle-group" id="cs-payment-toggles">${renderPaymentToggles()}</div>
            <div id="cs-booking-error" style="color:#dc2626;font-size:13px;display:none;margin-bottom:8px;"></div>
            <button type="button" class="btn-ticket-submit" id="cs-booking-submit">Submit</button>
            <div id="cs-booking-extras-${schedule.id}">${renderBookingExtrasHtml(schedule)}</div>
        </div></div>`;
    }

    // ─── Results rendering ─────────────────────────────────────────────────────
    function renderResults() {
        const listEl  = document.getElementById('cs-bus-list');
        const countEl = document.getElementById('cs-results-count');
        if (!listEl) return;
        countEl.textContent = `Showing ${searchResults.length} schedule${searchResults.length === 1 ? '' : 's'}`;
        if (!searchResults.length) {
            listEl.innerHTML = `<div class="search-card" style="padding:60px;text-align:center;"><h3>No Coaches Scheduled</h3><p style="color:var(--text-secondary);margin-top:8px;">No scheduled buses match this criteria.</p></div>`;
            return;
        }
        coachListEventsBound = false;
        listEl.innerHTML = searchResults.map(sched => {
            const isExpanded = expandedScheduleId === sched.id;
            const availColor = sched.available_seats_count === 0 ? 'var(--danger)' : 'var(--success)';
            return `<div class="bus-card" data-schedule-id="${sched.id}">
                <div class="bus-main-info">
                    <div class="operator-block">
                        <span class="operator-name">${escapeHtml(sched.bus.operator_name)}</span>
                        <span style="font-size:11px;color:var(--text-muted);">Coach ${escapeHtml(sched.bus.coach_number)}</span>
                        <span class="coach-tag ${sched.bus.coach_type==='AC'?'ac':''}">${escapeHtml(sched.bus.coach_type)}</span>
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
                        <span class="time-value" style="font-weight:500;">${escapeHtml(sched.route.duration)}</span>
                        <span class="station-value" style="font-size:11px;">${escapeHtml(sched.route.distance)}</span>
                    </div>
                    <div class="seats-block">
                        <span class="time-label">Seats Available</span>
                        <span class="seats-count" style="color:${availColor};">${sched.available_seats_count} Seats</span>
                    </div>
                    <div class="price-block">
                        <span class="time-label">Fare Price</span>
                        <span class="price-amount">BDT ${Number(sched.fare).toLocaleString()}</span>
                        <button type="button" class="btn ${isExpanded?'btn-secondary':'btn-primary'} cs-toggle-map" data-id="${sched.id}" style="margin-top:8px;padding:6px 12px;font-size:12px;">
                            ${isExpanded ? 'Close Map' : 'View Seat Plan'}
                        </button>
                    </div>
                </div>
                ${isExpanded ? `
                <div class="seats-selector-container">
                    <div class="seat-selection-grid">
                        <div>
                            <h3 style="font-size:14px;margin-bottom:10px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;gap:8px;">
                                Bus Seat Layout
                                <span class="live-status" style="font-size:11px;margin-left:10px;">
                                    <span class="live-dot"></span>
                                    <span id="cs-seat-map-live-text-${sched.id}">${lastFetchedTimeText || 'Live'}</span>
                                </span>
                            </h3>
                            <div class="seat-map-toolbar">
                                <button type="button" class="btn btn-secondary cs-block-mode-toggle ${adminBlockMode?'active':''}" data-schedule-id="${sched.id}" style="padding:6px 12px;font-size:12px;">
                                    ${adminBlockMode ? 'Done blocking' : 'Manage blocked seats'}
                                </button>
                                <span class="seat-map-toolbar-hint">${adminBlockMode ? 'Click an available seat to block it, or a blocked seat to unblock.' : 'Only admins can block seats. Customers cannot book blocked seats.'}</span>
                            </div>
                            <div id="cs-seat-map-${sched.id}">${renderSeatMap(sched)}</div>
                        </div>
                        <div id="cs-sidebar-${sched.id}">${renderBookingSidebar(sched)}</div>
                    </div>
                </div>` : ''}
            </div>`;
        }).join('');
        bindCoachListEvents();
    }

    function refreshSeatMapOnly() {
        if (!expandedScheduleId || selectedSeatBooking) { renderResults(); return; }
        const sched     = searchResults.find(s => s.id === expandedScheduleId);
        const seatMapEl = sched && document.getElementById(`cs-seat-map-${expandedScheduleId}`);
        if (!sched || !seatMapEl) { renderResults(); return; }
        seatMapEl.innerHTML = renderSeatMap(sched);
        updateBookingExtras(sched);
    }

    // Direct DOM mutation for instant seat toggle — no full re-render needed
    function toggleSeatVisual(seatEl, isNowSelected) {
        seatEl.style.cssText = isNowSelected ? SEAT_STYLES.selected : SEAT_STYLES.available;
        seatEl.title = seatStatusLabel(isNowSelected ? 'selected' : 'available');
    }

    // ─── Event binding ─────────────────────────────────────────────────────────
    function bindCoachListEvents() {
        const listEl = document.getElementById('cs-bus-list');
        if (!listEl || coachListEventsBound) return;
        coachListEventsBound = true;

        listEl.addEventListener('click', e => {
            // Toggle map expand/collapse
            const toggleBtn = e.target.closest('.cs-toggle-map');
            if (toggleBtn) {
                captureBookingFormState();
                const id = parseInt(toggleBtn.dataset.id, 10);
                if (expandedScheduleId === id) {
                    expandedScheduleId = null; selectedSeatBooking = null; adminSelectedSeats = []; adminBlockMode = false;
                } else {
                    expandedScheduleId = id; selectedSeatBooking = null; adminSelectedSeats = []; adminBlockMode = false;
                    adminPassengerPhone = ''; adminPassengerName = ''; adminPassengerEmail = ''; adminDroppingPoint = '';
                    const sched = searchResults.find(s => s.id === id);
                    if (sched?.boarding_points?.[0]) adminBoardingPoint = sched.boarding_points[0].value;
                }
                renderResults(); return;
            }

            // Payment method toggle
            const payBtn = e.target.closest('.payment-toggle[data-payment]');
            if (payBtn) {
                captureBookingFormState();
                adminPaymentMethod = payBtn.dataset.payment;
                document.querySelectorAll('#cs-payment-toggles .payment-toggle').forEach(btn => btn.classList.toggle('active', btn.dataset.payment === adminPaymentMethod));
                const sched = searchResults.find(s => s.id === expandedScheduleId);
                if (sched) updateBookingExtras(sched);
                return;
            }

            if (e.target.id === 'cs-booking-submit') { const s = searchResults.find(s => s.id === expandedScheduleId); if (s) handleBookingSubmit(s); return; }
            if (e.target.id === 'cs-cancel-booking-btn' && selectedSeatBooking) { handleCancelBooking(selectedSeatBooking.booking_id); return; }
            if (e.target.id === 'cs-back-to-book-btn') { selectedSeatBooking = null; renderResults(); return; }

            // Block mode toggle
            const blockBtn = e.target.closest('.cs-block-mode-toggle');
            if (blockBtn) {
                const schedId = parseInt(blockBtn.dataset.scheduleId, 10);
                if (expandedScheduleId === schedId) { adminBlockMode = !adminBlockMode; if (adminBlockMode) adminSelectedSeats = []; renderResults(); }
                return;
            }

            // Seat click
            const seatEl = e.target.closest('.seat[data-seat]');
            if (!seatEl) return;

            captureBookingFormState();
            const seatCode   = seatEl.dataset.seat;
            const status     = seatEl.dataset.status;
            const scheduleId = parseInt(seatEl.dataset.schedule, 10);
            const schedule   = searchResults.find(s => s.id === scheduleId);
            if (!schedule) return;

            // Block / unblock
            if (status === 'blocked' || (adminBlockMode && status === 'available')) {
                if (!adminBlockMode && status === 'blocked') { alert('Turn on "Manage blocked seats" to unblock this seat.'); return; }
                toggleSeatBlock(scheduleId, seatCode); return;
            }

            // Click booked/sold seat → show booking details
            if (status !== 'available') {
                if (schedule.seat_bookings?.[seatCode]) {
                    selectedSeatBooking = { seat: seatCode, ...schedule.seat_bookings[seatCode] };
                    adminSelectedSeats = []; renderResults();
                }
                return;
            }

            // Select / deselect available seat — mutate the DOM element directly
            // for instant green feedback, then update the fare sidebar
            if (adminSelectedSeats.includes(seatCode)) {
                adminSelectedSeats = adminSelectedSeats.filter(s => s !== seatCode);
                toggleSeatVisual(seatEl, false);
            } else {
                adminSelectedSeats.push(seatCode);
                toggleSeatVisual(seatEl, true);
            }
            selectedSeatBooking = null;
            updateBookingExtras(schedule);
        });

        listEl.addEventListener('input', e => {
            if (e.target.id === 'cs-booking-phone') adminPassengerPhone = e.target.value;
            if (e.target.id === 'cs-booking-name')  adminPassengerName  = e.target.value;
            if (e.target.id === 'cs-booking-email') adminPassengerEmail = e.target.value;
        });

        listEl.addEventListener('change', e => {
            if (e.target.id === 'cs-booking-gender') { adminGender = e.target.value; return; }
            const schedule = searchResults.find(s => s.id === expandedScheduleId);
            if (e.target.id === 'cs-boarding-point') {
                adminBoardingPoint = e.target.value;
                const bp = schedule?.boarding_points?.find(p => p.value === adminBoardingPoint);
                const el = document.getElementById('cs-boarding-info');
                if (el) el.textContent = bp ? `Reporting: ${bp.reporting_time||'—'} · Departure: ${bp.departure_time||'—'}` : 'Select a boarding point';
            }
            if (e.target.id === 'cs-dropping-point') {
                adminDroppingPoint = e.target.value;
                const dp = schedule?.dropping_points?.find(p => p.value === adminDroppingPoint);
                const el = document.getElementById('cs-dropping-info');
                if (el) el.textContent = dp ? `Estimated arrival: ${dp.arrival_time||'—'}` : 'Select a dropping point';
            }
        });
    }

    // ─── API actions ───────────────────────────────────────────────────────────
    async function toggleSeatBlock(scheduleId, seat) {
        const url = toggleBlockUrlTemplate.replace('__ID__', String(scheduleId));
        try {
            const res  = await fetch(url, { method:'POST', headers:{ Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken }, body:JSON.stringify({ seat }) });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { alert(data.message || 'Could not update blocked seat.'); return; }
            const sched = searchResults.find(s => s.id === scheduleId);
            if (sched && data.seat_map) {
                sched.seat_map = data.seat_map;
                sched.booked_seats = data.booked_seats || sched.booked_seats;
                sched.available_seats_count = data.available_seats_count ?? sched.available_seats_count;
                adminSelectedSeats = adminSelectedSeats.filter(s => (data.seat_map[s] || 'available') === 'available');
            }
            captureBookingFormState(); renderResults();
        } catch { alert('Network error while updating blocked seat.'); }
    }

    async function fetchCoachServices(silent = false) {
        if (isFetching || !searchDone) return;
        isFetching = true;
        const searchBtn = document.getElementById('cs-search-btn');
        if (!silent && searchBtn) searchBtn.textContent = 'Searching...';
        const params = new URLSearchParams({ from:searchParams.from, to:searchParams.to, date:searchParams.date, coach_type:searchParams.coachType });
        try {
            const res = await fetch(`${searchUrl}?${params}`, { headers:{ Accept:'application/json' } });
            if (!res.ok) return;
            searchResults = await res.json();
            const time = new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit', second:'2-digit' });
            lastFetchedTimeText = `Live — last updated ${time} (refreshes every 5s)`;
            if (adminSelectedSeats.length && expandedScheduleId) {
                const sched = searchResults.find(s => s.id === expandedScheduleId);
                if (sched) {
                    const map   = getSeatMap(sched);
                    const still = adminSelectedSeats.filter(seat => map[seat] === 'available');
                    if (still.length < adminSelectedSeats.length) alert('One or more selected seats were just booked. Selection updated.');
                    adminSelectedSeats = still;
                }
            }
            if (selectedSeatBooking && expandedScheduleId) {
                const sched = searchResults.find(s => s.id === expandedScheduleId);
                selectedSeatBooking = sched?.seat_bookings?.[selectedSeatBooking.seat] ? { seat:selectedSeatBooking.seat, ...sched.seat_bookings[selectedSeatBooking.seat] } : null;
            }
            if (silent && expandedScheduleId && !selectedSeatBooking) { refreshSeatMapOnly(); }
            else { captureBookingFormState(); renderResults(); }
            updateLiveStatusIndicator();
        } catch (err) { if (!silent) console.error('Coach services fetch failed:', err); }
        finally {
            isFetching = false;
            if (!silent && searchBtn) searchBtn.textContent = 'Search Buses';
        }
    }

    function updateLiveStatusIndicator() {
        const statusEl = document.getElementById('cs-live-status');
        const textEl   = document.getElementById('cs-live-text');
        if (statusEl) statusEl.style.display = 'inline-flex';
        if (textEl)   textEl.textContent = lastFetchedTimeText;
        if (expandedScheduleId) {
            const sub = document.getElementById(`cs-seat-map-live-text-${expandedScheduleId}`);
            if (sub) sub.textContent = lastFetchedTimeText;
        }
    }

    async function handleSearch() {
        const from = document.getElementById('cs-from').value, to = document.getElementById('cs-to').value,
              date = document.getElementById('cs-date').value, coachType = document.getElementById('cs-coach-type').value;
        if (!from || !to || !date) { alert('Please select from, to, and date.'); return; }
        if (from === to) { alert('Departure and destination must be different.'); return; }
        searchParams = { from, to, date, coachType };
        searchDone = true; expandedScheduleId = null; selectedSeatBooking = null; adminSelectedSeats = [];
        adminPassengerPhone = ''; adminPassengerName = ''; adminPassengerEmail = ''; adminDroppingPoint = '';
        document.getElementById('cs-empty-hint').style.display = 'none';
        document.getElementById('cs-results').style.display    = 'block';
        await fetchCoachServices(); startPolling();
    }

    async function handleBookingSubmit(schedule) {
        captureBookingFormState();
        const errorEl = document.getElementById('cs-booking-error');
        const name = adminPassengerName.trim(), phone = adminPassengerPhone.trim(), email = adminPassengerEmail.trim();
        if (!adminSelectedSeats.length) { showErr(errorEl, 'Please select at least one seat.'); return; }
        if (!name || !phone || !email || !adminBoardingPoint || !adminDroppingPoint) { showErr(errorEl, 'Please fill boarding, dropping, and passenger details.'); return; }
        const pricing = calcPricing(schedule, adminSelectedSeats.length, adminPaymentMethod);
        const payload = { schedule_id:schedule.id, passenger_name:name, passenger_phone:phone, passenger_email:email, passenger_gender:adminGender, boarding_point:adminBoardingPoint, dropping_point:adminDroppingPoint, seat_numbers:adminSelectedSeats.join(','), payment_method:adminPaymentMethod, total_fare:pricing.total, status:'PAID' };
        try {
            const res = await fetch(bookUrl, { method:'POST', headers:{ Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken }, body:JSON.stringify(payload) });
            if (res.ok) {
                const data = await res.json().catch(() => ({}));
                adminSelectedSeats = []; selectedSeatBooking = null; adminPassengerPhone = ''; adminPassengerName = ''; adminPassengerEmail = '';
                await fetchCoachServices(true);
                alert('Booking created successfully.' + (data.sms?.success ? ' SMS sent.' : '')); return;
            }
            const data = await res.json().catch(() => ({})); showErr(errorEl, data.message || 'Failed to create booking.');
        } catch { showErr(errorEl, 'Network error while submitting.'); }
    }

    async function handleCancelBooking(bookingId) {
        if (!confirm('Cancel this booking and release all seats?')) return;
        const url = cancelUrlTemplate.replace('__ID__', bookingId);
        try {
            const res  = await fetch(url, { method:'POST', headers:{ Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken } });
            const data = await res.json();
            if (res.ok) { selectedSeatBooking = null; await fetchCoachServices(true); }
            else alert(data.message || 'Failed to cancel booking.');
        } catch { alert('Network error during cancellation.'); }
    }

    function showErr(el, msg) { if (!el) return; el.style.display = 'block'; el.textContent = msg; }

    // ─── Polling ───────────────────────────────────────────────────────────────
    function startPolling() {
        stopPolling();
        const tab = document.getElementById('tab-content-coach-services');
        if (tab && tab.style.display !== 'none' && searchDone) fetchCoachServices(true);
        pollTimer = setInterval(() => {
            const t = document.getElementById('tab-content-coach-services');
            if (t && t.style.display !== 'none' && searchDone) fetchCoachServices(true);
        }, 5_000);
    }
    function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

    // ─── Init ──────────────────────────────────────────────────────────────────
    document.getElementById('cs-search-btn')?.addEventListener('click', handleSearch);
    bindCoachListEvents();
    window.coachServicesModule = { startPolling, stopPolling };
})();
