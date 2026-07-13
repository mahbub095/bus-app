<div class="coach-services-panel" style="grid-column: 1 / -1;">

    <!-- Search Form -->
    <div class="search-card" style="margin-bottom: 30px;">
        <h3 class="admin-panel-title" style="margin-bottom: 20px;">Search Coach Services</h3>
        <form class="search-form" id="coach-search-form" onsubmit="return false;">
            <div class="input-group">
                <label>From Station</label>
                <select id="cs-from" class="coupon-input" required>
                    <option value="">Select departure...</option>
                    @foreach($allStations as $st)
                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label>To Station</label>
                <select id="cs-to" class="coupon-input" required>
                    <option value="">Select destination...</option>
                    @foreach($allStations as $st)
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
    window.CoachServices = {
        stations: @json($allStations->map(fn($s) => ['id' => $s->id, 'name' => $s->name])),
        searchUrl: @json(route('admin.coach-services.search')),
        cancelUrlTemplate: @json(route('admin.bookings.cancel.api', ['id' => '__ID__'])),
        toggleBlockUrlTemplate: @json(route('admin.schedules.seats.toggle-block', ['id' => '__ID__'])),
        bookUrl: @json(route('admin.bookings.store')),
    };
</script>
<script src="{{ asset('js/admin/coach-services.js') }}"></script>
