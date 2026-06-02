<div class="admin-sections-layout" style="grid-column: 1 / -1;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Configured Transport Routes</h3>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Departure Origin</th>
                        <th>Arrival Destination</th>
                        <th>Est. Distance</th>
                        <th>Est. Duration</th>
                        <th>Operations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($routes as $r)
                        <tr>
                            <td>#{{ $r->id }}</td>
                            <td style="font-weight: 600;">{{ $r->from }}</td>
                            <td style="font-weight: 600;">{{ $r->to }}</td>
                            <td>{{ $r->distance ?? 'N/A' }}</td>
                            <td style="color: var(--gold)">{{ $r->duration ?? 'N/A' }}</td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="setCrudFormMode('route-form', {
                                            mode: 'edit',
                                            id: {{ $r->id }},
                                            action: '{{ route('admin.routes.update', $r->id) }}',
                                            title: 'Edit Route #{{ $r->id }}',
                                            submitLabel: 'Update Route',
                                            fields: {
                                                departure_station_id: {{ json_encode($r->departure_station_id) }},
                                                arrival_station_id: {{ json_encode($r->arrival_station_id) }},
                                                distance: {{ json_encode($r->distance ?? '') }},
                                                duration: {{ json_encode($r->duration ?? '') }}
                                            }
                                        })">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.routes.destroy', $r->id) }}" method="POST" onsubmit="return confirm('Delete this route? Related schedules will also be removed.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted)">No routes found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="booking-form-sidebar">
        <h3 class="booking-summary-title" id="route-form-title">Configure Route Line</h3>
        <form class="booking-form-fields" id="route-form" action="{{ route('admin.routes.store') }}" method="POST">
            @csrf
            @method('POST')
            <input type="hidden" name="_edit_id" value="">
            <div class="input-group">
                <label>Departure Station Origin</label>
                <select name="departure_station_id" class="coupon-input" required>
                    <option value="">Select departure...</option>
                    @foreach($stations as $st)
                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label>Arrival Station Destination</label>
                <select name="arrival_station_id" class="coupon-input" required>
                    <option value="">Select arrival...</option>
                    @foreach($stations as $st)
                        <option value="{{ $st->id }}">{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="input-group">
                <label>Distance Estimation (e.g. 250 km)</label>
                <input type="text" name="distance" class="coupon-input" placeholder="Distance" value="{{ old('distance') }}">
            </div>
            <div class="input-group">
                <label>Duration Estimation (e.g. 5.5 Hours)</label>
                <input type="text" name="duration" class="coupon-input" placeholder="Duration" value="{{ old('duration') }}">
            </div>
            <button class="btn btn-primary" id="route-form-submit" type="submit" style="height: 42px; margin-top: 10px;">
                Configure Route
            </button>
            <button type="button" class="btn btn-secondary form-cancel-btn" id="route-form-cancel"
                onclick="resetCrudForm('route-form', '{{ route('admin.routes.store') }}', 'Configure Route Line', 'Configure Route')">
                Cancel Edit
            </button>
        </form>
    </div>

</div>
