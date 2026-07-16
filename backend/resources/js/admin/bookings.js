/**
 * bookings.js
 *
 * Live-polling log for all booking records, plus the inline edit form.
 * Polls the API every 5 seconds while the "bookings" tab is open,
 * then pauses when the tab is hidden.
 *
 * Data contract (set in bookings.blade.php before this file loads):
 *   window.BookingsLogs.logsUrl            — API endpoint returning { bookings: [...] }
 *   window.BookingsLogs.updateRouteTemplate — URL template with '__ID__' placeholder
 *   window.BookingsLogs.destroyRouteTemplate — URL template with '__ID__' placeholder
 *
 * Public API (consumed by layout.js):
 *   window.bookingsLogsModule.startPolling()
 *   window.bookingsLogsModule.stopPolling()
 *
 * Public API (called from rendered row buttons):
 *   window.editBookingFromLog(bookingId)
 */
import { escapeHtml, formatDateTime, buildUrl, statusBadgeClass } from './utils.js';

(function () {
    const { logsUrl, updateRouteTemplate, destroyRouteTemplate } = window.BookingsLogs;

    const bodyEl     = document.getElementById('bookings-log-body');
    const liveTextEl = document.getElementById('bookings-live-text');
    const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let pollTimer   = null;
    let isFetching  = false;
    // Keyed by booking ID so edit buttons can look up the full record without re-fetching
    let bookingsMap = {};

    /** Update the "live" indicator text next to the panel title. */
    function setLiveText(text) {
        if (!liveTextEl) return;
        liveTextEl.innerHTML = `<span class="live-dot"></span>${text}`;
    }

    // ─── Rendering ────────────────────────────────────────────────────────────

    /** Render all booking rows into the table body and re-bind edit buttons. */
    function renderRows(bookings) {
        bookingsMap = {};

        if (!Array.isArray(bookings) || bookings.length === 0) {
            bodyEl.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align:center; padding:30px; color:var(--text-muted)">
                        No reservation records found.
                    </td>
                </tr>`;
            return;
        }

        bodyEl.innerHTML = bookings.map(b => {
            bookingsMap[b.id] = b;

            const routeFrom  = b.schedule?.route?.from        || 'N/A';
            const routeTo    = b.schedule?.route?.to          || 'N/A';
            const busName    = b.schedule?.bus?.operator_name || 'N/A';
            const updateUrl  = buildUrl(updateRouteTemplate,  b.id);
            const destroyUrl = buildUrl(destroyRouteTemplate, b.id);

            // Only show the ZiniPay "Pay" button for pending ZiniPay bookings
            const isZinipayPending = b.status === 'PENDING' && (b.payment_method || '').toLowerCase() === 'zinipay';
            const payButton = isZinipayPending
                ? `<a href="/admin/bookings/${b.id}/pay" class="btn btn-primary btn-sm"
                       style="display:inline-flex; align-items:center; justify-content:center;
                              height:28px; padding:0 10px; text-decoration:none;
                              font-size:12px; font-weight:600;">
                       Pay
                   </a>`
                : '';

            return `
                <tr>
                    <td style="font-weight:bold; color:var(--primary);">${escapeHtml(b.pnr)}</td>
                    <td style="font-weight:600;">${escapeHtml(b.passenger_name)}</td>
                    <td>
                        <div>${escapeHtml(b.passenger_phone)}</div>
                        <div style="font-size:11px; color:var(--text-secondary)">${escapeHtml(b.passenger_email)}</div>
                    </td>
                    <td>
                        <div>${escapeHtml(routeFrom)} ➔ ${escapeHtml(routeTo)}</div>
                        <div style="font-size:11px; color:var(--text-secondary)">Bus: ${escapeHtml(busName)}</div>
                        <div style="font-size:11px; color:var(--text-secondary)">
                            ${escapeHtml(formatDateTime(b.schedule?.departure_time))}
                        </div>
                    </td>
                    <td style="font-weight:bold;">${escapeHtml(b.seat_numbers)}</td>
                    <td style="color:var(--gold); font-weight:bold;">BDT ${Number(b.total_fare || 0).toLocaleString()}</td>
                    <td><span class="badge-status ${statusBadgeClass(b.status)}">${escapeHtml(b.status)}</span></td>
                    <td>
                        <div class="action-btns">
                            ${payButton}
                            <button type="button" class="btn btn-secondary btn-sm edit-booking-btn"
                                    data-booking-id="${b.id}">
                                Edit
                            </button>
                            <form action="${escapeHtml(destroyUrl)}" method="POST"
                                  onsubmit="return confirm('Permanently delete this booking record?');"
                                  style="display:inline-block;">
                                <input type="hidden" name="_token"  value="${escapeHtml(csrfToken)}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        bindEditButtons();
    }

    /** Attach click listeners to all "Edit" buttons after a re-render. */
    function bindEditButtons() {
        document.querySelectorAll('.edit-booking-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.bookingId, 10);
                if (!Number.isNaN(id)) window.editBookingFromLog(id);
            });
        });
    }

    // ─── Edit form ────────────────────────────────────────────────────────────

    /**
     * Populate the booking form with an existing booking's data for editing.
     * Relies on setCrudFormMode() from layout.js.
     */
    window.editBookingFromLog = function (bookingId) {
        const booking = bookingsMap[bookingId];
        if (!booking) return;

        setCrudFormMode('booking-form', {
            mode:        'edit',
            id:          booking.id,
            action:      buildUrl(updateRouteTemplate, booking.id),
            title:       `Edit Booking ${booking.pnr}`,
            submitLabel: 'Update Booking',
            fields: {
                passenger_name:  booking.passenger_name,
                passenger_phone: booking.passenger_phone,
                passenger_email: booking.passenger_email,
                seat_numbers:    booking.seat_numbers,
                total_fare:      booking.total_fare,
                status:          booking.status,
                payment_method:  booking.payment_method || 'bKash',
            },
        });
    };

    // ─── Data fetching ────────────────────────────────────────────────────────

    /**
     * Fetch the latest bookings from the API and re-render the table.
     * @param {boolean} silent — when true, suppresses the "update failed" text on error
     */
    async function fetchLogs(silent = false) {
        if (!bodyEl || isFetching) return;
        isFetching = true;

        try {
            const res = await fetch(logsUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;

            const data = await res.json();
            renderRows(data.bookings || []);

            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            setLiveText(`Live — last updated ${time} (refreshes every 5s)`);
        } catch {
            if (!silent) setLiveText('Live update failed');
        } finally {
            isFetching = false;
        }
    }

    // ─── Polling control ──────────────────────────────────────────────────────

    function startPolling() {
        stopPolling();
        fetchLogs();
        pollTimer = setInterval(() => fetchLogs(true), 5_000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        setLiveText('Live paused');
    }

    window.bookingsLogsModule = { startPolling, stopPolling };
})();
