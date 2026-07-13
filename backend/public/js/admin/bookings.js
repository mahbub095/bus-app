(function () {
    const logsUrl = window.BookingsLogs.logsUrl;
    const updateRouteTemplate = window.BookingsLogs.updateRouteTemplate;
    const destroyRouteTemplate = window.BookingsLogs.destroyRouteTemplate;
    const bodyEl = document.getElementById('bookings-log-body');
    const liveTextEl = document.getElementById('bookings-live-text');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let timer = null;
    let isFetching = false;
    let bookingsMap = {};

    function formatDateTime(iso) {
        if (!iso) return 'N/A';
        return new Date(iso).toLocaleString([], {
            weekday: 'short',
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function getRoute(template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function renderRows(bookings) {
        bookingsMap = {};

        if (!Array.isArray(bookings) || bookings.length === 0) {
            bodyEl.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted)">
                        No reservation records found.
                    </td>
                </tr>`;
            return;
        }

        bodyEl.innerHTML = bookings.map((b) => {
            bookingsMap[b.id] = b;
            const statusClass = ['PAID', 'SOLD', 'BOOKED'].includes(b.status) ? 'paid' : (b.status === 'PENDING' || b.status === 'CANCEL_REQUESTED' ? 'pending' : 'cancelled');
            const routeFrom = b.schedule?.route?.from || 'N/A';
            const routeTo = b.schedule?.route?.to || 'N/A';
            const busName = b.schedule?.bus?.operator_name || 'N/A';
            const updateUrl = getRoute(updateRouteTemplate, b.id);
            const destroyUrl = getRoute(destroyRouteTemplate, b.id);

            const isZinipay = (b.payment_method || '').toLowerCase() === 'zinipay';
            const payButtonHtml = (b.status === 'PENDING' && isZinipay) ? `
                <a href="/admin/bookings/${b.id}/pay" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; justify-content: center; height: 28px; line-height: 28px; padding: 0 10px; text-decoration: none; font-size: 12px; font-weight: 600;">
                    Pay
                </a>
            ` : '';

            return `
                <tr>
                    <td style="font-weight: bold; color: var(--primary);">${escapeHtml(b.pnr)}</td>
                    <td style="font-weight: 600;">${escapeHtml(b.passenger_name)}</td>
                    <td>
                        <div>${escapeHtml(b.passenger_phone)}</div>
                        <div style="font-size: 11px; color: var(--text-secondary)">${escapeHtml(b.passenger_email)}</div>
                    </td>
                    <td>
                        <div>${escapeHtml(routeFrom)} ➔ ${escapeHtml(routeTo)}</div>
                        <div style="font-size: 11px; color: var(--text-secondary)">
                            Bus: ${escapeHtml(busName)}
                        </div>
                        <div style="font-size: 11px; color: var(--text-secondary)">
                            ${escapeHtml(formatDateTime(b.schedule?.departure_time))}
                        </div>
                    </td>
                    <td style="font-weight: bold;">${escapeHtml(b.seat_numbers)}</td>
                    <td style="color: var(--gold); font-weight: bold;">BDT ${Number(b.total_fare || 0).toLocaleString()}</td>
                    <td>
                        <span class="badge-status ${statusClass}">${escapeHtml(b.status)}</span>
                    </td>
                    <td>
                        <div class="action-btns">
                            ${payButtonHtml}
                            <button type="button" class="btn btn-secondary btn-sm edit-booking-btn" data-booking-id="${b.id}">
                                Edit
                            </button>
                            <form action="${escapeHtml(destroyUrl)}" method="POST" onsubmit="return confirm('Permanently delete this booking record?');" style="display:inline-block;">
                                <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>`;
        }).join('');
        bindEditButtons();
    }

    function bindEditButtons() {
        document.querySelectorAll('.edit-booking-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.bookingId, 10);
                if (!Number.isNaN(id)) {
                    editBookingFromLog(id);
                }
            });
        });
    }

    window.editBookingFromLog = function (bookingId) {
        const booking = bookingsMap[bookingId];
        if (!booking) return;

        setCrudFormMode('booking-form', {
            mode: 'edit',
            id: booking.id,
            action: getRoute(updateRouteTemplate, booking.id),
            title: `Edit Booking ${booking.pnr}`,
            submitLabel: 'Update Booking',
            fields: {
                passenger_name: booking.passenger_name,
                passenger_phone: booking.passenger_phone,
                passenger_email: booking.passenger_email,
                seat_numbers: booking.seat_numbers,
                total_fare: booking.total_fare,
                status: booking.status,
                payment_method: booking.payment_method || 'bKash'
            }
        });
    };

    function setLiveText(text) {
        if (!liveTextEl) return;
        liveTextEl.innerHTML = `<span class="live-dot"></span>${text}`;
    }

    async function fetchLogs(silent = false) {
        if (!bodyEl || isFetching) return;
        isFetching = true;
        try {
            const res = await fetch(logsUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            renderRows(data.bookings || []);
            const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            setLiveText(`Live — last updated ${now} (refreshes every 5s)`);
        } catch (err) {
            if (!silent) setLiveText('Live update failed');
        } finally {
            isFetching = false;
        }
    }

    function startPolling() {
        stopPolling();
        fetchLogs();
        timer = setInterval(() => fetchLogs(true), 5000);
    }

    function stopPolling() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        setLiveText('Live paused');
    }

    window.bookingsLogsModule = { startPolling, stopPolling };
})();
