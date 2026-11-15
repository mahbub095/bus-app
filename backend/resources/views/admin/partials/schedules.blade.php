<div class="admin-sections-layout" style="grid-column: 1 / -1;">

    @php($scheduleSearch = trim((string) request()->query('schedules_search', '')))

    <div class="admin-panel">
        <h3 class="admin-panel-title">Timetables</h3>

        {{-- Filter bar: search timetables by operator company or coach number --}}
        <form method="GET" action="{{ url('/admin') }}"
              style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin-bottom:16px; padding:12px 16px; background:rgba(99,102,241,0.05); border-radius:var(--border-radius-sm);">
            <div class="input-group" style="flex:1 1 260px; min-width:240px;">
                <label style="font-size:12px; font-weight:600; margin-bottom:4px;">Search (Operator / Coach No.)</label>
                <input type="search" name="schedules_search" id="schedules-search" class="coupon-input"
                       style="height:38px;"
                       value="{{ $scheduleSearch }}"
                       placeholder="Operator name or coach registration no."
                       autocomplete="off" spellcheck="false">
            </div>
            <button type="submit" class="btn btn-primary" style="height:38px; padding:0 18px;">Search</button>
            <a href="{{ url('/admin') }}#schedules" class="btn btn-secondary"
               style="height:38px; padding:0 16px; display:inline-flex; align-items:center;">Reset</a>
        </form>

        @if($scheduleSearch !== '')
            <p style="font-size:12px; color:var(--text-secondary); margin:-4px 0 12px;">
                {{ $schedules->total() }} schedule{{ $schedules->total() === 1 ? '' : 's' }} matching
                <strong>&ldquo;{{ $scheduleSearch }}&rdquo;</strong>
            </p>
        @endif
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Operator & Coach</th>
                        <th>Route</th>
                        <th>Departure Date & Time</th>
                        <th>Fare Price BDT</th>
                        <th>Operations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $sch)
                        <tr>
                            <td>#{{ $sch->id }}</td>
                            <td>
                                <div>{{ $sch->bus->operator_name }}</div>
                                <div style="font-size: 11px; color: var(--text-secondary)">{{ $sch->bus->coach_number }}
                                    ({{ $sch->bus->coach_type }})</div>
                            </td>
                            <td>{{ $sch->route->departureStation->name }} ➔ {{ $sch->route->arrivalStation->name }}</td>
                            <td>
                                <div>{{ $sch->departure_time->format('M d, Y') }}</div>
                                <div style="font-size: 11px; color: var(--primary); font-weight: bold;">
                                    {{ $sch->departure_time->format('h:i A') }}
                                </div>
                            </td>
                            <td style="color: var(--gold); font-weight: bold;">BDT {{ number_format($sch->fare) }}</td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="setCrudFormMode('schedule-form', {
                                                mode: 'edit',
                                                id: {{ $sch->id }},
                                                action: '{{ route('admin.schedules.update', $sch->id) }}',
                                                title: 'Edit Schedule #{{ $sch->id }}',
                                                submitLabel: 'Update Schedule',
                                                fields: {
                                                    bus_id: {{ json_encode($sch->bus_id) }},
                                                    route_id: {{ json_encode($sch->route_id) }},
                                                    departure_time: {{ json_encode($sch->departure_time->format('Y-m-d\TH:i')) }},
                                                    arrival_time: {{ json_encode($sch->arrival_time->format('Y-m-d\TH:i')) }},
                                                    fare: {{ json_encode($sch->fare) }}
                                                }
                                            })">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.schedules.destroy', $sch->id) }}" method="POST"
                                        onsubmit="return confirm('Delete this schedule? Related bookings will also be removed.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted)">
                                @if($scheduleSearch !== '')
                                    No schedule matches &ldquo;{{ $scheduleSearch }}&rdquo;.
                                @else
                                    No schedules found.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $schedules->links('admin.partials.pagination') }}
    </div>

    <div class="booking-form-sidebar">
        <h3 class="booking-summary-title" id="schedule-form-title">Schedule Coach Run</h3>
        <form class="booking-form-fields" id="schedule-form" action="{{ route('admin.schedules.store') }}"
            method="POST">
            @csrf
            @method('POST')
            <input type="hidden" name="_edit_id" value="">
            <div class="input-group">
                <label>Select Bus Coach</label>
                <select name="bus_id" class="coupon-input" required>
                    <option value="">Select bus coach...</option>
                    @foreach($buses as $bus)
                        <option value="{{ $bus->id }}">{{ $bus->operator_name }} ({{ $bus->coach_number }} -
                            {{ $bus->coach_type }})</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label>Select Route Connection</label>
                <select name="route_id" class="coupon-input" required>
                    <option value="">Select route...</option>
                    @foreach($routes as $r)
                        <option value="{{ $r->id }}">{{ $r->from }} ➔ {{ $r->to }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label>Departure Date & Time</label>
                <input type="datetime-local" name="departure_time" class="coupon-input" required>
            </div>
            <div class="input-group">
                <label>Arrival Date & Time</label>
                <input type="datetime-local" name="arrival_time" class="coupon-input" required>
            </div>
            <div class="input-group">
                <label>Fare Price BDT</label>
                <input type="number" name="fare" class="coupon-input" placeholder="900" required min="0">
            </div>
            <button class="btn btn-primary" id="schedule-form-submit" type="submit"
                style="height: 42px; margin-top: 10px;">
                Schedule Coach Run
            </button>
            <button type="button" class="btn btn-secondary form-cancel-btn" id="schedule-form-cancel"
                onclick="resetCrudForm('schedule-form', '{{ route('admin.schedules.store') }}', 'Schedule Coach Run', 'Schedule Coach Run')">
                Cancel Edit
            </button>
        </form>
    </div>

</div>