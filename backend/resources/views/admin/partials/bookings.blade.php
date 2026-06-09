<div class="admin-sections-layout" style="grid-column: 1 / -1;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">
            Ticket Reservation Logs
            <span id="bookings-live-text" class="live-status" style="font-size: 11px;">
                <span class="live-dot"></span>
                Live disabled
            </span>
        </h3>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>PNR Code</th>
                        <th>Passenger Name</th>
                        <th>Contact Information</th>
                        <th>Journey Details</th>
                        <th>Seats</th>
                        <th>Fare Paid</th>
                        <th>Status</th>
                        <th>Operations</th>
                    </tr>
                </thead>
                <tbody id="bookings-log-body">
                    @forelse($recentBookings as $b)
                        <tr>
                            <td style="font-weight: bold; color: var(--primary)">
                                SE{{ str_pad($b->id, 5, '0', STR_PAD_LEFT) }}
                            </td>
                            <td style="font-weight: 600;">{{ $b->passenger_name }}</td>
                            <td>
                                <div>{{ $b->passenger_phone }}</div>
                                <div style="font-size: 11px; color: var(--text-secondary)">{{ $b->passenger_email }}</div>
                            </td>
                            <td>
                                @if($b->schedule && $b->schedule->route)
                                    <div>{{ $b->schedule->route->departureStation->name }} ➔ {{ $b->schedule->route->arrivalStation->name }}</div>
                                    <div style="font-size: 11px; color: var(--text-secondary)">
                                        Bus: {{ $b->schedule->bus?->operator_name ?? 'N/A' }}
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-secondary)">
                                        {{ $b->schedule->departure_time->format('D, M d, Y @ h:i A') }}
                                    </div>
                                @else
                                    <span style="color: var(--text-muted)">N/A</span>
                                @endif
                            </td>
                            <td style="font-weight: bold;">{{ $b->seat_numbers }}</td>
                            <td style="color: var(--gold); font-weight: bold;">BDT {{ number_format($b->total_fare) }}</td>
                            <td>
                                <span class="badge-status {{ $b->status === 'PAID' ? 'paid' : ($b->status === 'CANCEL_REQUESTED' ? 'pending' : 'cancelled') }}">
                                    {{ $b->status }}
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="setCrudFormMode('booking-form', {
                                            mode: 'edit',
                                            id: {{ $b->id }},
                                            action: '{{ route('admin.bookings.update', $b->id) }}',
                                            title: 'Edit Booking #SE{{ str_pad($b->id, 5, '0', STR_PAD_LEFT) }}',
                                            submitLabel: 'Update Booking',
                                            fields: {
                                                passenger_name: {{ json_encode($b->passenger_name) }},
                                                passenger_phone: {{ json_encode($b->passenger_phone) }},
                                                passenger_email: {{ json_encode($b->passenger_email) }},
                                                seat_numbers: {{ json_encode($b->seat_numbers) }},
                                                total_fare: {{ json_encode($b->total_fare) }},
                                                status: {{ json_encode($b->status) }},
                                                payment_method: {{ json_encode($b->payment_method) }}
                                            }
                                        })">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.bookings.destroy', $b->id) }}" method="POST" onsubmit="return confirm('Permanently delete this booking record?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted)">
                                No reservation records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="booking-form-sidebar">
        <h3 class="booking-summary-title" id="booking-form-title">Create Booking</h3>
        <form class="booking-form-fields" id="booking-form" action="{{ route('admin.bookings.store') }}" method="POST">
            @csrf
            <input type="hidden" name="_method" id="booking-form-method" value="POST">
            <input type="hidden" name="_edit_id" value="">

            <div class="input-group" id="booking-schedule-group">
                <label>Select Schedule</label>
                <select name="schedule_id" class="coupon-input" required>
                    <option value="">Select schedule...</option>
                    @foreach($schedules as $sch)
                        <option value="{{ $sch->id }}">
                            {{ $sch->route->departureStation->name }} ➔ {{ $sch->route->arrivalStation->name }}
                            — {{ $sch->departure_time->format('M d, h:i A') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label>Passenger Name</label>
                <input type="text" name="passenger_name" class="coupon-input" placeholder="Full name" required value="{{ old('passenger_name') }}">
            </div>
            <div class="input-group">
                <label>Phone Number</label>
                <input type="text" name="passenger_phone" class="coupon-input" placeholder="01XXXXXXXXX" required value="{{ old('passenger_phone') }}">
            </div>
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="passenger_email" class="coupon-input" placeholder="email@example.com" required value="{{ old('passenger_email') }}">
            </div>
            <div class="input-group">
                <label>Seat Numbers (comma-separated)</label>
                <input type="text" name="seat_numbers" class="coupon-input" placeholder="A1,A2" required value="{{ old('seat_numbers') }}">
            </div>
            <div class="input-group">
                <label>Total Fare BDT</label>
                <input type="number" name="total_fare" class="coupon-input" placeholder="900" required min="0" step="0.01" value="{{ old('total_fare') }}">
            </div>
            <div class="input-group">
                <label>Payment Method</label>
                <select name="payment_method" class="coupon-input" required>
                    <option value="bKash">bKash</option>
                    <option value="Nagad">Nagad</option>
                    <option value="Card">Card</option>
                    <option value="Cash">Cash</option>
                    <option value="ZiniPay">ZiniPay</option>
                </select>
            </div>
            <div class="input-group">
                <label>Status</label>
                <select name="status" class="coupon-input" required>
                    <option value="PENDING">PENDING</option>
                    <option value="PAID">PAID</option>
                    <option value="CANCEL_REQUESTED">CANCEL_REQUESTED</option>
                    <option value="CANCELLED">CANCELLED</option>
                </select>
            </div>
            <button class="btn btn-primary" id="booking-form-submit" type="submit" style="height: 42px; margin-top: 10px;">
                Create Booking
            </button>
            <button type="button" class="btn btn-secondary form-cancel-btn" id="booking-form-cancel"
                onclick="resetCrudForm('booking-form', '{{ route('admin.bookings.store') }}', 'Create Booking', 'Create Booking')">
                Cancel Edit
            </button>
        </form>
    </div>

</div>

<script>
(function () {
    const logsUrl = @json(route('admin.bookings.logs.api'));
    const bodyEl = document.getElementById('bookings-log-body');
    const liveTextEl = document.getElementById('bookings-live-text');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const updateRouteTemplate = @json(route('admin.bookings.update', ['id' => '__ID__']));
    const destroyRouteTemplate = @json(route('admin.bookings.destroy', ['id' => '__ID__']));
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
            const statusClass = b.status === 'PAID' ? 'paid' : (b.status === 'CANCEL_REQUESTED' ? 'pending' : 'cancelled');
            const routeFrom = b.schedule?.route?.from || 'N/A';
            const routeTo = b.schedule?.route?.to || 'N/A';
            const busName = b.schedule?.bus?.operator_name || 'N/A';
            const updateUrl = getRoute(updateRouteTemplate, b.id);
            const destroyUrl = getRoute(destroyRouteTemplate, b.id);

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
                            <button type="button" class="btn btn-secondary btn-sm edit-booking-btn" data-booking-id="${b.id}">
                                Edit
                            </button>
                            <form action="${escapeHtml(destroyUrl)}" method="POST" onsubmit="return confirm('Permanently delete this booking record?');">
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
    }

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
</script>
