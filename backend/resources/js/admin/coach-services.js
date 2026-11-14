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

import { escapeHtml, formatTime, formatBdt } from './utils.js';
import { generateDefaultGrid, DRIVER_SVG } from './bus-layout-helper.js';

(function () {
    const { stations, searchUrl, cancelUrlTemplate, toggleBlockUrlTemplate, bookUrl, holdSeatUrl, releaseSeatUrl, seatHoldMinutes = 2 } = window.CoachServices;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // ─── Module state ──────────────────────────────────────────────────────────
    let searchParams        = { from: '', to: '', date: '', coachType: 'All' };
    let searchResults       = [];
    let searchDone          = false;
    let expandedScheduleId  = null;
    let selectedSeatBooking = null;
    let adminSelectedSeats  = [];
    // Map of seatCode → ISO expiry string from server
    let adminSeatExpirations = {};
    let adminBoardingPoint  = '';
    let adminDroppingPoint  = '';
    let adminPassengerPhone = '';
    let adminPassengerName  = '';
    let adminPassengerEmail = '';
    let adminGender         = 'M';
    let adminPaymentMethod  = 'Cash';
    let pollTimer           = null;
    let holdTimerInterval   = null;
    let isFetching          = false;
    let coachListEventsBound = false;
    let lastFetchedTimeText = '';

    const dateInput = document.getElementById('cs-date');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }

    // ─── Inline seat styles (bypasses all CSS specificity issues) ─────────────
    const SEAT_STYLES = {
        selected:  'background:#22c55e;border-color:#16a34a;color:#fff;box-shadow:0 0 10px rgba(34,197,94,.45);cursor:pointer;',
        available: 'background:var(--bg-seat-map,#fff);border-color:#d1d5db;color:#374151;cursor:pointer;',
        blocked:   'background:#6b7280;border-color:#4b5563;color:#f3f4f6;cursor:pointer;opacity:.85;',
        booked_m:  'background:#fecaca;border-color:#f87171;color:#7f1d1d;cursor:pointer;',
        booked_f:  'background:#fdf4ff;border-color:#f0abfc;color:#a21caf;cursor:pointer;',
        sold_m:    'background:#ef4444;border-color:#b91c1c;color:#fff;cursor:pointer;',
        sold_f:    'background:#ec4899;border-color:#be185d;color:#fff;cursor:pointer;',
    };

    // ─── Seat action popover state ─────────────────────────────────────────────
    let activeSeatPopover = null; // { seat, scheduleId, x, y }

    function closeSeatPopover() {
        const existing = document.getElementById('cs-seat-popover');
        if (existing) existing.remove();
        activeSeatPopover = null;
    }

    function showSeatPopover(seatEl, seatCode, status, scheduleId, schedule) {
        closeSeatPopover();

        const rect = seatEl.getBoundingClientRect();
        const listEl = document.getElementById('cs-bus-list');
        const listRect = listEl ? listEl.getBoundingClientRect() : { left: 0, top: 0 };

        const isBookedOrSold = status.startsWith('booked_') || status.startsWith('sold_');
        const isBlocked = status === 'blocked';
        const bookingData = isBookedOrSold ? schedule?.seat_bookings?.[seatCode] : null;

        const popover = document.createElement('div');
        popover.id = 'cs-seat-popover';
        popover.style.cssText = `
            position:fixed;
            left:${rect.left + rect.width / 2}px;
            top:${rect.bottom + 6}px;
            transform:translateX(-50%);
            background:var(--bg-card,#1e1e2e);
            border:1px solid var(--border-color,rgba(255,255,255,.12));
            border-radius:10px;
            padding:12px 14px;
            min-width:200px;
            max-width:260px;
            box-shadow:0 8px 24px rgba(0,0,0,.35);
            z-index:9999;
            font-size:13px;
            color:var(--text-primary,#e2e8f0);
        `;

        let html = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <strong style="font-size:14px;">Seat ${escapeHtml(seatCode)}</strong>
            <button id="cs-popover-close" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;line-height:1;padding:0 2px;">×</button>
        </div>`;

        if (isBookedOrSold && bookingData) {
            html += `
            <div style="background:rgba(255,255,255,.05);border-radius:6px;padding:8px 10px;margin-bottom:10px;font-size:12px;line-height:1.6;">
                <div><span style="color:var(--text-muted);">Passenger:</span> <strong>${escapeHtml(bookingData.passenger_name)}</strong></div>
                <div><span style="color:var(--text-muted);">Phone:</span> ${escapeHtml(bookingData.passenger_phone)}</div>
                <div><span style="color:var(--text-muted);">PNR:</span> <span style="color:var(--primary);">${escapeHtml(bookingData.pnr || '—')}</span></div>
                <div><span style="color:var(--text-muted);">Fare:</span> ${formatBdt(bookingData.total_fare)}</div>
                <div><span style="color:var(--text-muted);">Payment:</span> ${escapeHtml(bookingData.payment_method)}</div>
                <div><span style="color:var(--text-muted);">Status:</span> ${escapeHtml(bookingData.status)}</div>
            </div>
            <button id="cs-popover-cancel" data-booking-id="${bookingData.booking_id}"
                style="width:100%;padding:7px 10px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);border-radius:6px;color:#f87171;cursor:pointer;font-size:12px;font-weight:600;margin-bottom:6px;">
                ✕ Cancel This Booking
            </button>
            <button id="cs-popover-print" data-seat="${escapeHtml(seatCode)}" data-schedule="${scheduleId}"
                style="width:100%;padding:7px 10px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.4);border-radius:6px;color:#818cf8;cursor:pointer;font-size:12px;font-weight:600;margin-bottom:6px;">
                🖨️ Print Ticket
            </button>
            <button id="cs-popover-block" data-seat="${escapeHtml(seatCode)}" data-schedule="${scheduleId}"
                style="width:100%;padding:7px 10px;background:rgba(107,114,128,.15);border:1px solid rgba(107,114,128,.4);border-radius:6px;color:#9ca3af;cursor:not-allowed;font-size:12px;opacity:.5;"
                disabled title="Cancel the booking first to block this seat">
                🔒 Block Seat (cancel first)
            </button>`;
        } else if (isBlocked) {
            html += `
            <div style="color:var(--text-muted);font-size:12px;margin-bottom:10px;">This seat is blocked — customers cannot book it.</div>
            <button id="cs-popover-unblock" data-seat="${escapeHtml(seatCode)}" data-schedule="${scheduleId}"
                style="width:100%;padding:7px 10px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.4);border-radius:6px;color:#4ade80;cursor:pointer;font-size:12px;font-weight:600;">
                🔓 Unblock Seat
            </button>`;
        } else {
            // available
            html += `
            <div style="color:var(--text-muted);font-size:12px;margin-bottom:10px;">This seat is available.</div>
            <button id="cs-popover-block" data-seat="${escapeHtml(seatCode)}" data-schedule="${scheduleId}"
                style="width:100%;padding:7px 10px;background:rgba(107,114,128,.15);border:1px solid rgba(107,114,128,.4);border-radius:6px;color:#9ca3af;cursor:pointer;font-size:12px;font-weight:600;margin-bottom:6px;">
                🔒 Block Seat
            </button>
            <button id="cs-popover-select" data-seat="${escapeHtml(seatCode)}"
                style="width:100%;padding:7px 10px;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.4);border-radius:6px;color:#4ade80;cursor:pointer;font-size:12px;font-weight:600;">
                ✓ Select for Booking
            </button>`;
        }

        html += `</div>`;
        popover.innerHTML = html;
        popover.addEventListener('click', (ev) => {
            ev.stopPropagation();
            const button = ev.target.closest('button');
            if (!button) return;
            if (button.id === 'cs-popover-close') {
                closeSeatPopover();
                return;
            }
            if (button.id === 'cs-popover-unblock' || button.id === 'cs-popover-block') {
                if (button.disabled) return;
                const seat = button.dataset.seat;
                const sid = parseInt(button.dataset.schedule, 10);
                closeSeatPopover();
                toggleSeatBlock(sid, seat);
                return;
            }
            if (button.id === 'cs-popover-cancel') {
                const bookingId = button.dataset.bookingId;
                closeSeatPopover();
                handleCancelBooking(bookingId);
            }
            if (button.id === 'cs-popover-print') {
                const seat = button.dataset.seat;
                const sid = parseInt(button.dataset.schedule, 10);
                closeSeatPopover();
                const sched = searchResults.find(s => s.id === sid);
                if (sched) printSingleTicket(sched, seat);
            }
        });
        document.body.appendChild(popover);
        activeSeatPopover = { seat: seatCode, scheduleId };

        // Keep popover within viewport
        const pop = document.getElementById('cs-seat-popover');
        if (pop) {
            const popRect = pop.getBoundingClientRect();
            if (popRect.right > window.innerWidth - 8) {
                pop.style.left = `${window.innerWidth - popRect.width - 12}px`;
                pop.style.transform = 'none';
            }
            if (popRect.bottom > window.innerHeight - 8) {
                pop.style.top = `${rect.top - popRect.height - 6}px`;
            }
        }
    }

    // ─── General helpers ───────────────────────────────────────────────────────
    const stationName = id => stations.find(s => s.id === parseInt(id, 10))?.name || '';

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

    // ─── Seat hold countdown helpers ──────────────────────────────────────────
    function formatCountdown(expiresAt) {
        if (!expiresAt) return '';
        const diff = new Date(expiresAt) - new Date();
        if (diff <= 0) return '0:00';
        const mins = Math.floor(diff / 60000);
        const secs = Math.floor((diff % 60000) / 1000);
        return `${mins}:${String(secs).padStart(2, '0')}`;
    }

    function startHoldTimerTick() {
        if (holdTimerInterval) return;
        holdTimerInterval = setInterval(() => {
            // Check for expired holds
            const now = new Date();
            const expired = Object.entries(adminSeatExpirations)
                .filter(([, exp]) => new Date(exp) <= now)
                .map(([seat]) => seat);

            if (expired.length > 0) {
                expired.forEach(seat => {
                    adminSelectedSeats = adminSelectedSeats.filter(s => s !== seat);
                    delete adminSeatExpirations[seat];
                });
                // Release on server (best-effort, fire-and-forget)
                if (expandedScheduleId) {
                    expired.forEach(seat => {
                        fetch(releaseSeatUrl, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: JSON.stringify({ schedule_id: expandedScheduleId, seat_number: seat }),
                        }).catch(() => {});
                    });
                }
                captureBookingFormState();
                refreshSeatMapOnly();
                alert(`Hold expired for seat(s): ${expired.join(', ')}. Please re-select.`);
                return;
            }

            // Update countdown badges in-place without a full re-render
            Object.entries(adminSeatExpirations).forEach(([seat, exp]) => {
                const badge = document.getElementById(`hold-timer-${CSS.escape(seat)}`);
                if (badge) badge.textContent = formatCountdown(exp);
            });
        }, 1000);
    }

    function stopHoldTimerTick() {
        if (holdTimerInterval) { clearInterval(holdTimerInterval); holdTimerInterval = null; }
    }

    // ─── Seat cell rendering ───────────────────────────────────────────────────
    function renderSeatCell(schedule, seatCode, seatMap) {
        const status    = seatMap[seatCode] || 'available';
        const isPicking = adminSelectedSeats.includes(seatCode) && status === 'available';
        const isViewing = selectedSeatBooking?.seat === seatCode;

        const baseStyle   = isPicking ? SEAT_STYLES.selected : (SEAT_STYLES[status] || SEAT_STYLES.available);
        const viewOutline = isViewing ? 'outline:3px solid #6366f1;outline-offset:2px;z-index:1;' : '';

        let title = isPicking ? 'selected' : status;
        if (status === 'blocked') title = 'Blocked — click to manage';
        else if (String(status).startsWith('booked_') || String(status).startsWith('sold_')) title = 'Click to view or cancel';

        // Build hold timer badge for selected seats
        const timerHtml = isPicking && adminSeatExpirations[seatCode]
            ? `<span id="hold-timer-${seatCode}" style="font-size:9px;line-height:1;display:block;margin-top:2px;opacity:.85;">${formatCountdown(adminSeatExpirations[seatCode])}</span>`
            : '';

        return `<div class="seat selectable"
                     style="${baseStyle}${viewOutline}flex-direction:column;align-items:center;justify-content:center;"
                     data-seat="${seatCode}"
                     data-schedule="${schedule.id}"
                     data-status="${status}"
                     title="${seatStatusLabel(title)}">${seatCode}${timerHtml}</div>`;
    }

    // ─── Grid generation ───────────────────────────────────────────────────────
    // generateDefaultGrid and DRIVER_SVG are imported from bus-layout-helper.js

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
        return ['Cash','ZiniPay'].map(m =>
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

    function syncBlockSelectedButton() {
        const count = adminSelectedSeats.length;
        document.querySelectorAll('.cs-block-selected').forEach(btn => {
            btn.disabled = count === 0;
            btn.textContent = `Block selected${count ? ` (${count})` : ''}`;
        });
    }

    function updateBookingExtras(schedule) {
        const el = document.getElementById(`cs-booking-extras-${schedule.id}`);
        if (el) el.innerHTML = renderBookingExtrasHtml(schedule);
        syncBlockSelectedButton();
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
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn-ticket-submit" id="cs-booking-submit" style="flex:1;">Submit</button>
                <button type="button" class="btn btn-secondary" id="cs-reserve-submit" style="flex:1;padding:10px;font-size:14px;font-weight:600;border-radius:8px;">Reserve Seat</button>
            </div>
            <div id="cs-booking-extras-${schedule.id}">${renderBookingExtrasHtml(schedule)}</div>
        </div></div>`;
    }

    // ─── Results rendering ─────────────────────────────────────────────────────
    function renderSeatManager(schedule) {
        const map = getSeatMap(schedule);
        const blocked = [];
        const occupied = [];
        Object.entries(map).forEach(([seat, status]) => {
            if (status === 'blocked') blocked.push(seat);
            else if (String(status).startsWith('booked_') || String(status).startsWith('sold_')) {
                const info = schedule.seat_bookings?.[seat];
                occupied.push({
                    seat,
                    label: info?.passenger_name ? `${seat} · ${info.passenger_name}` : seat,
                    status: info?.status || status,
                });
            }
        });
        blocked.sort();
        occupied.sort((a, b) => a.seat.localeCompare(b.seat));
        const chip = 'padding:4px 8px;border-radius:999px;border:1px solid var(--border-color,rgba(255,255,255,.16));background:rgba(255,255,255,.06);color:inherit;cursor:pointer;font-size:12px;';
        const blockedHtml = blocked.length
            ? blocked.map(seat => `<button type="button" class="cs-unblock-chip" data-seat="${escapeHtml(seat)}" data-schedule="${schedule.id}" title="Unblock ${escapeHtml(seat)}" style="${chip}">${escapeHtml(seat)} · Unblock</button>`).join('')
            : `<span style="color:var(--text-muted);font-size:12px;">None</span>`;
        const occupiedHtml = occupied.length
            ? occupied.map(item => `<button type="button" class="cs-occupied-chip" data-seat="${escapeHtml(item.seat)}" data-schedule="${schedule.id}" title="${escapeHtml(item.status)}" style="${chip}">${escapeHtml(item.label)}</button>`).join('')
            : `<span style="color:var(--text-muted);font-size:12px;">None</span>`;
        const count = adminSelectedSeats.length;
        return `<div class="seat-map-toolbar" style="display:flex;flex-direction:column;gap:8px;align-items:flex-start;">
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;"><strong style="font-size:12px;min-width:88px;">Blocked</strong>${blockedHtml}</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;"><strong style="font-size:12px;min-width:88px;">Sold</strong>${occupiedHtml}</div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <button type="button" class="btn btn-secondary cs-block-selected" data-schedule-id="${schedule.id}" ${count ? '' : 'disabled'} style="padding:6px 12px;font-size:12px;">Block selected${count ? ` (${count})` : ''}</button>
                <span class="seat-map-toolbar-hint">Click an available seat to book it. Unblock from the list, or open a sold seat to cancel that booking.</span>
            </div>
        </div>`;
    }

    function renderResults() {
        closeSeatPopover();
        const listEl  = document.getElementById('cs-bus-list');
        const countEl = document.getElementById('cs-results-count');
        if (!listEl) return;
        countEl.textContent = `Showing ${searchResults.length} schedule${searchResults.length === 1 ? '' : 's'}`;
        if (!searchResults.length) {
            listEl.innerHTML = `<div class="search-card" style="padding:60px;text-align:center;"><h3>No Coaches Scheduled</h3><p style="color:var(--text-secondary);margin-top:8px;">No scheduled buses match this criteria.</p></div>`;
            return;
        }
        listEl.innerHTML = searchResults.map(sched => {
            const isExpanded = expandedScheduleId === sched.id;
            const availColor = sched.available_seats_count === 0 ? 'var(--danger)' : 'var(--success)';
            const logoHtml = sched.bus.logo_url
                ? `<img src="${escapeHtml(sched.bus.logo_url)}" alt="${escapeHtml(sched.bus.operator_name)}" style="height:36px;max-width:100px;object-fit:contain;margin-bottom:4px;border-radius:4px;">`
                : '';
            return `<div class="bus-card" data-schedule-id="${sched.id}">
                <div class="bus-main-info">
                    <div class="operator-block">
                        ${logoHtml}
                        <span class="operator-name">${escapeHtml(sched.bus.operator_name)}</span>
                        <span style="font-size:11px;color:var(--text-muted);">Coach ${escapeHtml(sched.bus.coach_number)}</span>
                        <span class="coach-tag ${sched.bus.coach_type==='AC'?'ac':''}">${escapeHtml(sched.bus.coach_type)}</span>
                    </div>
                    <div class="time-block">
                        <span class="time-label">Departure</span>
                        <span class="time-value">${formatTime(sched.departure_time)}</span>
                        <span class="station-value">${escapeHtml(sched.route?.from || stationName(searchParams.from))}</span>
                    </div>
                    <div class="time-block">
                        <span class="time-label">Arrival</span>
                        <span class="time-value">${formatTime(sched.arrival_time)}</span>
                        <span class="station-value">${escapeHtml(sched.route?.to || stationName(searchParams.to))}</span>
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
                            ${renderSeatManager(sched)}
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
    function toggleSeatVisual(seatEl, isNowSelected, expiresAt = null) {
        seatEl.style.cssText = (isNowSelected ? SEAT_STYLES.selected : SEAT_STYLES.available)
            + 'flex-direction:column;align-items:center;justify-content:center;';
        seatEl.title = seatStatusLabel(isNowSelected ? 'selected' : 'available');
        const seatCode = seatEl.dataset.seat;
        // Update or remove the hold timer badge
        let badge = seatEl.querySelector(`#hold-timer-${CSS.escape(seatCode)}`);
        if (isNowSelected && expiresAt) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = `hold-timer-${seatCode}`;
                badge.style.cssText = 'font-size:9px;line-height:1;display:block;margin-top:2px;opacity:.85;';
                seatEl.appendChild(badge);
            }
            badge.textContent = formatCountdown(expiresAt);
        } else if (badge) {
            badge.remove();
        }
    }

    // ─── Event binding ─────────────────────────────────────────────────────────
    function bindCoachListEvents() {
        const listEl = document.getElementById('cs-bus-list');
        if (!listEl || coachListEventsBound) return;
        coachListEventsBound = true;

        document.addEventListener('click', (ev) => {
            const pop = document.getElementById('cs-seat-popover');
            if (!pop || pop.contains(ev.target)) return;
            closeSeatPopover();
        });

        listEl.addEventListener('click', e => {
            // Toggle map expand/collapse
            const toggleBtn = e.target.closest('.cs-toggle-map');
            if (toggleBtn) {
                captureBookingFormState();
                const id = parseInt(toggleBtn.dataset.id, 10);
                if (expandedScheduleId === id) {
                    // Release all holds for the current schedule before collapsing
                    releaseAllAdminHolds(expandedScheduleId);
                    expandedScheduleId = null; selectedSeatBooking = null; adminSelectedSeats = []; adminSeatExpirations = {};
                } else {
                    // Release any holds on the previously expanded schedule
                    if (expandedScheduleId) releaseAllAdminHolds(expandedScheduleId);
                    expandedScheduleId = id; selectedSeatBooking = null; adminSelectedSeats = []; adminSeatExpirations = {};
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
            if (e.target.id === 'cs-reserve-submit') { const s = searchResults.find(s => s.id === expandedScheduleId); if (s) handleReserveSeat(s); return; }
            if (e.target.id === 'cs-cancel-booking-btn' && selectedSeatBooking) { handleCancelBooking(selectedSeatBooking.booking_id); return; }
            if (e.target.id === 'cs-back-to-book-btn') { selectedSeatBooking = null; renderResults(); return; }

            const unblockChip = e.target.closest('.cs-unblock-chip');
            if (unblockChip) {
                e.stopPropagation();
                toggleSeatBlock(parseInt(unblockChip.dataset.schedule, 10), unblockChip.dataset.seat);
                return;
            }

            const occupiedChip = e.target.closest('.cs-occupied-chip');
            if (occupiedChip) {
                e.stopPropagation();
                const seatCode = occupiedChip.dataset.seat;
                const scheduleId = parseInt(occupiedChip.dataset.schedule, 10);
                const schedule = searchResults.find(s => s.id === scheduleId);
                const seatEl = listEl.querySelector(`.seat[data-seat="${CSS.escape(seatCode)}"][data-schedule="${scheduleId}"]`);
                if (schedule && seatEl) showSeatPopover(seatEl, seatCode, seatEl.dataset.status, scheduleId, schedule);
                return;
            }

            const blockSelectedBtn = e.target.closest('.cs-block-selected');
            if (blockSelectedBtn && !blockSelectedBtn.disabled) {
                blockSelectedSeats(parseInt(blockSelectedBtn.dataset.scheduleId, 10));
                return;
            }

            // Print sold tickets
            const printBtn = e.target.closest('.cs-print-sold');
            if (printBtn) {
                const scheduleId = parseInt(printBtn.dataset.scheduleId, 10);
                const schedule = searchResults.find(s => s.id === scheduleId);
                if (schedule) printSoldTickets(schedule);
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

            if (status === 'blocked' || String(status).startsWith('booked_') || String(status).startsWith('sold_')) {
                e.stopPropagation();
                showSeatPopover(seatEl, seatCode, status, scheduleId, schedule);
                return;
            }

            // Select / deselect available seat — mutate the DOM element directly
            // for instant green feedback, then update the fare sidebar
            if (adminSelectedSeats.includes(seatCode)) {
                adminSelectedSeats = adminSelectedSeats.filter(s => s !== seatCode);
                delete adminSeatExpirations[seatCode];
                toggleSeatVisual(seatEl, false);
                // Release hold on server (best-effort)
                if (expandedScheduleId) {
                    fetch(releaseSeatUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ schedule_id: expandedScheduleId, seat_number: seatCode }),
                    }).catch(() => {});
                }
            } else {
                // Optimistically select, then confirm with server hold
                adminSelectedSeats.push(seatCode);
                const optimisticExpiry = new Date(Date.now() + seatHoldMinutes * 60 * 1000).toISOString();
                adminSeatExpirations[seatCode] = optimisticExpiry;
                toggleSeatVisual(seatEl, true, optimisticExpiry);
                startHoldTimerTick();

                if (expandedScheduleId) {
                    fetch(holdSeatUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ schedule_id: expandedScheduleId, seat_number: seatCode }),
                    })
                    .then(res => res.json().then(data => ({ ok: res.ok, data })))
                    .then(({ ok, data }) => {
                        if (ok && data.success) {
                            // Replace optimistic expiry with server-issued one
                            adminSeatExpirations[seatCode] = data.expires_at;
                            const badge = document.getElementById(`hold-timer-${seatCode}`);
                            if (badge) badge.textContent = formatCountdown(data.expires_at);
                        } else {
                            // Roll back — seat was grabbed by someone else
                            adminSelectedSeats = adminSelectedSeats.filter(s => s !== seatCode);
                            delete adminSeatExpirations[seatCode];
                            toggleSeatVisual(seatEl, false);
                            syncBlockSelectedButton();
                            alert(data.message || 'Seat is no longer available.');
                        }
                    })
                    .catch(() => {
                        // Roll back on network error
                        adminSelectedSeats = adminSelectedSeats.filter(s => s !== seatCode);
                        delete adminSeatExpirations[seatCode];
                        toggleSeatVisual(seatEl, false);
                        syncBlockSelectedButton();
                        alert('Network error while reserving seat. Please try again.');
                    });
                }
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
    async function toggleSeatBlock(scheduleId, seat, { silent = false } = {}) {
        const url = toggleBlockUrlTemplate.replace('__ID__', String(scheduleId));
        try {
            const res  = await fetch(url, { method:'POST', headers:{ Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken }, body:JSON.stringify({ seat }) });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { alert(data.message || 'Could not update blocked seat.'); return false; }
            const sched = searchResults.find(s => s.id === scheduleId);
            if (sched && data.seat_map) {
                sched.seat_map = data.seat_map;
                sched.booked_seats = data.booked_seats || sched.booked_seats;
                sched.available_seats_count = data.available_seats_count ?? sched.available_seats_count;
                adminSelectedSeats = adminSelectedSeats.filter(s => (data.seat_map[s] || 'available') === 'available');
            }
            if (!silent) { captureBookingFormState(); renderResults(); }
            return true;
        } catch {
            alert('Network error while updating blocked seat.');
            return false;
        }
    }

    async function blockSelectedSeats(scheduleId) {
        const seats = [...adminSelectedSeats];
        if (!seats.length) return;
        for (const seat of seats) {
            const ok = await toggleSeatBlock(scheduleId, seat, { silent: true });
            if (!ok) break;
            delete adminSeatExpirations[seat];
            fetch(releaseSeatUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ schedule_id: scheduleId, seat_number: seat }),
            }).catch(() => {});
        }
        await fetchCoachServices(true);
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
                    if (still.length < adminSelectedSeats.length) {
                        // Some seats got booked by someone else — clean up expirations too
                        adminSelectedSeats.filter(seat => !still.includes(seat)).forEach(seat => {
                            delete adminSeatExpirations[seat];
                        });
                        alert('One or more selected seats were just booked. Selection updated.');
                    }
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
        const from = document.getElementById('cs-from')?.value || '',
              to   = document.getElementById('cs-to')?.value || '',
              date = document.getElementById('cs-date')?.value || '',
              coachType = document.getElementById('cs-coach-type')?.value || 'All';
        if (from && to && from === to) { alert('Departure and destination must be different.'); return; }
        // Release any active holds before changing the search
        if (expandedScheduleId) releaseAllAdminHolds(expandedScheduleId);
        searchParams = { from, to, date, coachType };
        searchDone = true; expandedScheduleId = null; selectedSeatBooking = null; adminSelectedSeats = []; adminSeatExpirations = {};
        adminPassengerPhone = ''; adminPassengerName = ''; adminPassengerEmail = ''; adminDroppingPoint = '';
        const emptyHint = document.getElementById('cs-empty-hint');
        if (emptyHint) emptyHint.style.display = 'none';
        const resultsEl = document.getElementById('cs-results');
        if (resultsEl) resultsEl.style.display = 'block';
        await fetchCoachServices(); startPolling();
    }

    async function handleBookingSubmit(schedule) {
        captureBookingFormState();
        const errorEl = document.getElementById('cs-booking-error');
        const name = adminPassengerName.trim(), phone = adminPassengerPhone.trim(), email = adminPassengerEmail.trim();
        if (!adminSelectedSeats.length) { showErr(errorEl, 'Please select at least one seat.'); return; }
        if (!name || !phone || !email || !adminBoardingPoint || !adminDroppingPoint) { showErr(errorEl, 'Please fill boarding, dropping, and passenger details.'); return; }
        const pricing = calcPricing(schedule, adminSelectedSeats.length, adminPaymentMethod);

        // Resolve status based on payment method:
        //   Cash         → SOLD    (cash payment = ticket sold)
        //   bKash/Nagad/Card → BOOKED (admin reservation, pending online payment)
        //   ZiniPay      → PENDING (until payment confirmed)
        const pm = adminPaymentMethod.toLowerCase();
        const resolvedStatus = pm === 'zinipay' ? 'PENDING'
            : (pm === 'cash' ? 'SOLD' : 'BOOKED');

        const payload = { schedule_id:schedule.id, passenger_name:name, passenger_phone:phone, passenger_email:email, passenger_gender:adminGender, boarding_point:adminBoardingPoint, dropping_point:adminDroppingPoint, seat_numbers:adminSelectedSeats.join(','), payment_method:adminPaymentMethod, total_fare:pricing.total, status:resolvedStatus };
        try {
            const res = await fetch(bookUrl, { method:'POST', headers:{ Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken }, body:JSON.stringify(payload) });
            if (res.ok) {
                const data = await res.json().catch(() => ({}));
                clearBookingFormFields(schedule);
                await fetchCoachServices(true);
                alert('Booking created successfully.' + (data.sms?.success ? ' SMS sent.' : '')); return;
            }
            const data = await res.json().catch(() => ({})); showErr(errorEl, data.message || 'Failed to create booking.');
        } catch { showErr(errorEl, 'Network error while submitting.'); }
    }

    /**
     * Reserve selected seats — always creates a BOOKED status booking
     * regardless of payment method. The seats show as Booked (M) / Booked (F)
     * on both the admin and frontend seat maps.
     */
    async function handleReserveSeat(schedule) {
        captureBookingFormState();
        const errorEl = document.getElementById('cs-booking-error');
        const name = adminPassengerName.trim(), phone = adminPassengerPhone.trim(), email = adminPassengerEmail.trim();
        if (!adminSelectedSeats.length) { showErr(errorEl, 'Please select at least one seat.'); return; }
        if (!name || !phone || !email || !adminBoardingPoint || !adminDroppingPoint) { showErr(errorEl, 'Please fill boarding, dropping, and passenger details.'); return; }
        const pricing = calcPricing(schedule, adminSelectedSeats.length, adminPaymentMethod);

        // Reserve always uses BOOKED status — the seat shows as Booked (M/F) on the map
        const payload = { schedule_id:schedule.id, passenger_name:name, passenger_phone:phone, passenger_email:email, passenger_gender:adminGender, boarding_point:adminBoardingPoint, dropping_point:adminDroppingPoint, seat_numbers:adminSelectedSeats.join(','), payment_method:adminPaymentMethod, total_fare:pricing.total, status:'BOOKED' };
        try {
            const res = await fetch(bookUrl, { method:'POST', headers:{ Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken }, body:JSON.stringify(payload) });
            if (res.ok) {
                const data = await res.json().catch(() => ({}));
                clearBookingFormFields(schedule);
                await fetchCoachServices(true);
                alert('Seat reserved successfully (Booked status). Seats are now held for this passenger.'); return;
            }
            const data = await res.json().catch(() => ({})); showErr(errorEl, data.message || 'Failed to reserve seat.');
        } catch { showErr(errorEl, 'Network error while reserving.'); }
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

    // ─── Print tickets ─────────────────────────────────────────────────────────
    function printSingleTicket(schedule, seatCode) {
        printSoldTickets(schedule, [seatCode]);
    }

    function printSoldTickets(schedule, overrideSeats = null) {
        const { siteName = 'Bus Company', siteLogoUrl = null, siteAddress = '' } = window.CoachServices;
        const seatMap = getSeatMap(schedule);

        // Use overrideSeats if provided (single-ticket print), otherwise collect all sold/booked
        const soldSeats = overrideSeats
            ? overrideSeats
            : Object.entries(seatMap)
                .filter(([, status]) => status.startsWith('sold_') || status.startsWith('booked_'))
                .map(([seat]) => seat)
                .sort();

        if (!soldSeats.length) {
            alert('No sold or booked seats found for this schedule.');
            return;
        }

        const logoHtml = siteLogoUrl
            ? `<img src="${siteLogoUrl}" alt="${siteName}" style="height:48px;object-fit:contain;">`
            : `<div style="font-size:22px;font-weight:800;color:#333;">${siteName}</div>`;

        const busLogoHtml = schedule.bus?.logo_url
            ? `<img src="${schedule.bus.logo_url}" alt="${schedule.bus.operator_name}" style="height:40px;object-fit:contain;">`
            : '';

        const formatDT = (iso) => {
            if (!iso) return '—';
            const d = new Date(iso);
            return d.toLocaleDateString('en-BD', { day:'2-digit', month:'short', year:'numeric' })
                + ' ' + d.toLocaleTimeString('en-BD', { hour:'2-digit', minute:'2-digit', hour12:true });
        };

        const formatDate = (iso) => {
            if (!iso) return '—';
            return new Date(iso).toLocaleDateString('en-BD', { day:'2-digit', month:'short', year:'numeric' });
        };

        const formatTime = (iso) => {
            if (!iso) return '—';
            return new Date(iso).toLocaleTimeString('en-BD', { hour:'2-digit', minute:'2-digit', hour12:true });
        };

        const tickets = soldSeats.map(seat => {
            const booking = schedule.seat_bookings?.[seat] || {};
            const pnr = booking.pnr || `—`;
            const passenger = booking.passenger_name || '—';
            const phone = booking.passenger_phone || '—';
            const fare = booking.total_fare ? `BDT ${Number(booking.total_fare).toLocaleString()}` : '—';
            const issuedAt = booking.issued_at || booking.created_at || '';
            const bp = schedule.boarding_points?.[0] || {};
            const dp = schedule.dropping_points?.[0] || {};

            return `
        <div class="ticket" style="
            page-break-after: always;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 0;
            max-width: 540px;
            margin: 0 auto 32px auto;
            font-family: Arial, sans-serif;
            overflow: hidden;
        ">
            <!-- Header -->
            <div style="
                background: #f97316;
                color: white;
                padding: 14px 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">
                <div style="font-size:20px;font-weight:800;letter-spacing:1px;">${siteName}</div>
                <div style="font-size:11px;font-weight:700;letter-spacing:2px;opacity:0.9;">E-TICKET</div>
            </div>

            <!-- PNR + Bus logo -->
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">BOOKING REFERENCE (PNR)</div>
                    <div style="font-size:24px;font-weight:800;color:#1f2937;letter-spacing:2px;">${escapeHtml(pnr)}</div>
                </div>
                <div style="text-align:right;">
                    ${busLogoHtml}
                    <div style="font-size:12px;font-weight:700;color:#374151;margin-top:4px;">${escapeHtml(schedule.bus?.operator_name || '—')}</div>
                    ${siteAddress ? `<div style="font-size:10px;color:#6b7280;max-width:200px;">${escapeHtml(siteAddress)}</div>` : ''}
                </div>
            </div>

            <!-- Route -->
            <div style="padding:16px 20px 8px;text-align:center;">
                <div style="font-size:18px;font-weight:700;color:#1f2937;">${escapeHtml(schedule.route?.from || '—')} → ${escapeHtml(schedule.route?.to || '—')}</div>
            </div>

            <!-- Journey Details -->
            <div style="padding:8px 20px 16px;border-bottom:1px solid #f3f4f6;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <tr>
                        <td style="padding:4px 0;color:#6b7280;">Journey Date</td>
                        <td style="padding:4px 0;font-weight:600;">${formatDate(schedule.departure_time)}</td>
                        <td style="padding:4px 0;color:#6b7280;">Departure</td>
                        <td style="padding:4px 0;font-weight:600;">${formatTime(schedule.departure_time)}</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;color:#6b7280;">Reporting</td>
                        <td style="padding:4px 0;font-weight:600;">${escapeHtml(bp.reporting_time || '—')}</td>
                        <td style="padding:4px 0;color:#6b7280;">Coach</td>
                        <td style="padding:4px 0;font-weight:600;">${escapeHtml(schedule.bus?.coach_number || '—')}</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;color:#6b7280;">Boarding</td>
                        <td colspan="3" style="padding:4px 0;font-weight:600;">${escapeHtml(bp.departure_time || '—')} — ${escapeHtml(bp.label || schedule.route?.from || '—')} Counter</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;color:#6b7280;">Dropping</td>
                        <td colspan="3" style="padding:4px 0;font-weight:600;">${escapeHtml(dp.arrival_time || '—')} — ${escapeHtml(dp.label || schedule.route?.to || '—')} Counter</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;color:#6b7280;">Seat(s)</td>
                        <td style="padding:4px 0;font-weight:700;color:#f97316;">${escapeHtml(seat)}</td>
                        <td></td><td></td>
                    </tr>
                </table>
            </div>

            <!-- Passenger details -->
            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <tr>
                        <td style="padding:4px 0;color:#6b7280;">Passenger</td>
                        <td style="padding:4px 0;font-weight:600;">${escapeHtml(passenger)}</td>
                        <td style="padding:4px 0;color:#6b7280;">Mobile</td>
                        <td style="padding:4px 0;font-weight:600;">${escapeHtml(phone)}</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;color:#6b7280;">Seat Fare</td>
                        <td style="padding:4px 0;font-weight:600;">${fare}</td>
                        <td style="padding:4px 0;color:#6b7280;">Issued</td>
                        <td style="padding:4px 0;font-size:11px;color:#6b7280;">${escapeHtml(formatDT(issuedAt))}</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;color:#6b7280;">Total Paid</td>
                        <td colspan="3" style="padding:4px 0;font-weight:800;color:#f97316;font-size:15px;">${fare}</td>
                    </tr>
                </table>
            </div>

            <!-- T&C -->
            <div style="padding:12px 20px;font-size:11px;color:#6b7280;border-bottom:1px solid #f3f4f6;">
                <strong style="display:block;margin-bottom:4px;">Terms &amp; Conditions</strong>
                Tickets are non-refundable within 3 hours of departure. Arrive 30 minutes early.
                <br><br>
                <strong style="display:block;margin-bottom:4px;">Luggage Policy</strong>
                Free 10 kg luggage per seat. Excess charged at the counter.
            </div>

            <!-- Footer -->
            <div style="padding:10px 20px;text-align:center;font-size:11px;color:#9ca3af;">
                www.${siteName.toLowerCase().replace(/\s+/g,'')} &nbsp;·&nbsp; Issued by ${siteName}
            </div>
        </div>`;
        }).join('');

        const html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Sold Tickets — ${escapeHtml(schedule.route?.from || '')} → ${escapeHtml(schedule.route?.to || '')} — ${formatDate(schedule.departure_time)}</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 20px; background: #fff; }
    @media print {
        body { margin: 0; }
        .ticket { page-break-after: always; }
        .ticket:last-child { page-break-after: avoid; }
    }
</style>
</head>
<body>
<div style="font-family:Arial,sans-serif;text-align:center;margin-bottom:24px;padding:12px;border-bottom:2px solid #f3f4f6;">
    <div style="display:flex;align-items:center;justify-content:center;gap:12px;">
        ${logoHtml}
    </div>
    <div style="font-size:14px;color:#6b7280;margin-top:8px;">Sold Tickets — ${escapeHtml(schedule.route?.from || '—')} → ${escapeHtml(schedule.route?.to || '—')} | ${formatDate(schedule.departure_time)} | ${soldSeats.length} ticket(s)</div>
</div>
${tickets}
</body>
</html>`;

        const win = window.open('', '_blank');
        if (!win) { alert('Please allow popups for this site to print tickets.'); return; }
        win.document.write(html);
        win.document.close();
        win.focus();
        setTimeout(() => win.print(), 500);
    }

    function showErr(el, msg) { if (!el) return; el.style.display = 'block'; el.textContent = msg; }

    /**
     * Clear all booking form fields — both module variables AND the HTML
     * input elements in the sidebar so the form is visually blank for the
     * next booking.
     */
    function clearBookingFormFields(schedule) {
        // Clear module variables
        adminSelectedSeats.forEach(seat => delete adminSeatExpirations[seat]);
        adminSelectedSeats  = [];
        selectedSeatBooking = null;
        adminPassengerPhone = '';
        adminPassengerName  = '';
        adminPassengerEmail = '';
        adminGender         = 'M';
        adminPaymentMethod  = 'Cash';
        const sched = searchResults.find(s => s.id === schedule.id);
        adminBoardingPoint = sched?.boarding_points?.[0]?.value || '';
        adminDroppingPoint = '';

        // Clear HTML input elements directly
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
        set('cs-booking-phone', '');
        set('cs-booking-name', '');
        set('cs-booking-email', '');
        set('cs-booking-gender', 'M');
        set('cs-boarding-point', adminBoardingPoint);
        set('cs-dropping-point', '');

        // Hide any error message
        const errorEl = document.getElementById('cs-booking-error');
        if (errorEl) { errorEl.style.display = 'none'; errorEl.textContent = ''; }

        // Reset payment toggle buttons to Cash
        document.querySelectorAll('#cs-payment-toggles .payment-toggle').forEach(btn =>
            btn.classList.toggle('active', btn.dataset.payment === 'Cash')
        );

        // Update boarding/dropping info text
        const bpInfoEl = document.getElementById('cs-boarding-info');
        const dpInfoEl = document.getElementById('cs-dropping-info');
        if (bpInfoEl) {
            const bp = sched?.boarding_points?.find(p => p.value === adminBoardingPoint);
            bpInfoEl.textContent = bp ? `Reporting: ${bp.reporting_time||'—'} · Departure: ${bp.departure_time||'—'}` : 'Select a boarding point';
        }
        if (dpInfoEl) dpInfoEl.textContent = 'Select a dropping point';
    }

    /**
     * Release all admin-held seats for the given schedule (best-effort fire-and-forget).
     */
    function releaseAllAdminHolds(scheduleId) {
        const seatsToRelease = Object.keys(adminSeatExpirations);
        seatsToRelease.forEach(seat => {
            fetch(releaseSeatUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ schedule_id: scheduleId, seat_number: seat }),
            }).catch(() => {});
        });
        adminSeatExpirations = {};
        adminSelectedSeats = [];
    }

    // ─── Polling ───────────────────────────────────────────────────────────────
    function startPolling() {
        stopPolling();
        const tab = document.getElementById('tab-content-coach-services');
        if (tab && tab.style.display !== 'none') {
            searchDone = true;
            const emptyHint = document.getElementById('cs-empty-hint');
            if (emptyHint) emptyHint.style.display = 'none';
            const resultsEl = document.getElementById('cs-results');
            if (resultsEl) resultsEl.style.display = 'block';
            fetchCoachServices(searchResults.length > 0);
        }
        pollTimer = setInterval(() => {
            const t = document.getElementById('tab-content-coach-services');
            if (t && t.style.display !== 'none' && searchDone) fetchCoachServices(true);
        }, 5_000);
    }
    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        stopHoldTimerTick();
    }

    // ─── Init ──────────────────────────────────────────────────────────────────
    document.getElementById('cs-search-btn')?.addEventListener('click', handleSearch);
    bindCoachListEvents();
    window.coachServicesModule = { startPolling, stopPolling };

    // Release all admin holds when page is closed/refreshed
    window.addEventListener('beforeunload', () => {
        if (expandedScheduleId && Object.keys(adminSeatExpirations).length > 0) {
            Object.keys(adminSeatExpirations).forEach(seat => {
                try {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', releaseSeatUrl, false); // synchronous
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken || '');
                    xhr.send(JSON.stringify({ schedule_id: expandedScheduleId, seat_number: seat }));
                } catch { /* ignore */ }
            });
        }
    });

    // Auto-initialize search parameters
    const initialFrom = document.getElementById('cs-from')?.value || '';
    const initialTo   = document.getElementById('cs-to')?.value || '';
    const initialDate = document.getElementById('cs-date')?.value || '';
    const initialCoachType = document.getElementById('cs-coach-type')?.value || 'All';
    searchParams = { from: initialFrom, to: initialTo, date: initialDate, coachType: initialCoachType };
    searchDone = true;

    // Auto-fetch if coach services tab is currently displayed
    const initialTab = document.getElementById('tab-content-coach-services');
    if (window.location.hash === '#coach-services' || (initialTab && initialTab.style.display !== 'none')) {
        startPolling();
    }
})();
