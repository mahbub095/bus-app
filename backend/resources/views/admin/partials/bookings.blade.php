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
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted)">
                            Open this tab to load live booking logs…
                        </td>
                    </tr>
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
                    <option value="SOLD">SOLD</option>
                    <option value="BOOKED">BOOKED</option>
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
    window.BookingsLogs = {
        logsUrl: @json(route('admin.bookings.logs.api')),
        updateRouteTemplate: @json(route('admin.bookings.update', ['id' => '__ID__'])),
        destroyRouteTemplate: @json(route('admin.bookings.destroy', ['id' => '__ID__'])),
    };
</script>
@vite('resources/js/admin/bookings.js')
