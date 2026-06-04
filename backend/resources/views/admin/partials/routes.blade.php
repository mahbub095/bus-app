<div class="admin-sections-layout routes-admin-layout" style="grid-column: 1 / -1;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Configured Transport Routes</h3>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Departure Origin</th>
                        <th>Arrival Destination</th>
                        <th>Boarding</th>
                        <th>Dropping</th>
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
                            <td>{{ count($r->boarding_points ?? []) }} point(s)</td>
                            <td>{{ count($r->dropping_points ?? []) }} point(s)</td>
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
                                            },
                                            boarding_points: {{ json_encode($r->boarding_points ?? []) }},
                                            dropping_points: {{ json_encode($r->dropping_points ?? []) }}
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
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted)">No routes found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="booking-form-sidebar route-form-sidebar">
        <h3 class="booking-summary-title" id="route-form-title">Configure Route Line</h3>
        <form class="booking-form-fields" id="route-form" action="{{ route('admin.routes.store') }}" method="POST" onsubmit="return serializeRoutePoints()">
            @csrf
            @method('POST')
            <input type="hidden" name="_edit_id" value="">
            <input type="hidden" name="boarding_points_json" id="boarding_points_json" value="">
            <input type="hidden" name="dropping_points_json" id="dropping_points_json" value="">

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

            <div class="route-points-section">
                <h4 class="route-points-heading">Boarding Point *</h4>
                <div class="table-wrapper">
                    <table class="admin-table route-points-table" id="boarding-points-table">
                        <thead>
                            <tr>
                                <th>Boarding Point</th>
                                <th>Reporting Time</th>
                                <th>Departure Time</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="boarding-points-body"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addBoardingRow()">+ Add Boarding Point</button>
            </div>

            <div class="route-points-section">
                <h4 class="route-points-heading">Dropping Point *</h4>
                <div class="table-wrapper">
                    <table class="admin-table route-points-table" id="dropping-points-table">
                        <thead>
                            <tr>
                                <th>Dropping Point</th>
                                <th>Estimated Arrival Time</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="dropping-points-body"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addDroppingRow()">+ Add Dropping Point</button>
            </div>

            <button class="btn btn-primary" id="route-form-submit" type="submit" style="height: 42px; margin-top: 10px;">
                Configure Route
            </button>
            <button type="button" class="btn btn-secondary form-cancel-btn" id="route-form-cancel"
                onclick="resetCrudForm('route-form', '{{ route('admin.routes.store') }}', 'Configure Route Line', 'Configure Route'); loadRoutePointsForm([], []);">
                Cancel Edit
            </button>
        </form>
    </div>

</div>

<script>
(function () {
    function boardingRowHtml(data = {}) {
        return `<tr>
            <td><input type="text" class="coupon-input bp-name" placeholder="e.g. Gabtoli" value="${escapeAttr(data.name || '')}"></td>
            <td><input type="text" class="coupon-input bp-reporting" placeholder="06:30 AM" value="${escapeAttr(data.reporting_time || '')}"></td>
            <td><input type="text" class="coupon-input bp-departure" placeholder="07:00 AM" value="${escapeAttr(data.departure_time || '')}"></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">×</button></td>
        </tr>`;
    }

    function droppingRowHtml(data = {}) {
        return `<tr>
            <td><input type="text" class="coupon-input dp-name" placeholder="e.g. Dampara Bus Terminal" value="${escapeAttr(data.name || '')}"></td>
            <td><input type="text" class="coupon-input dp-arrival" placeholder="02:30 PM" value="${escapeAttr(data.arrival_time || '')}"></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">×</button></td>
        </tr>`;
    }

    function escapeAttr(str) {
        return String(str ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    window.addBoardingRow = function (data = {}) {
        document.getElementById('boarding-points-body').insertAdjacentHTML('beforeend', boardingRowHtml(data));
    };

    window.addDroppingRow = function (data = {}) {
        document.getElementById('dropping-points-body').insertAdjacentHTML('beforeend', droppingRowHtml(data));
    };

    window.loadRoutePointsForm = function (boarding = [], dropping = []) {
        const boardingBody = document.getElementById('boarding-points-body');
        const droppingBody = document.getElementById('dropping-points-body');
        if (!boardingBody || !droppingBody) return;

        boardingBody.innerHTML = '';
        droppingBody.innerHTML = '';

        (boarding.length ? boarding : [{}]).forEach(row => addBoardingRow(row));
        (dropping.length ? dropping : [{}]).forEach(row => addDroppingRow(row));
    };

    window.serializeRoutePoints = function () {
        const boarding = [];
        document.querySelectorAll('#boarding-points-body tr').forEach(tr => {
            const name = tr.querySelector('.bp-name')?.value.trim();
            if (!name) return;
            boarding.push({
                name,
                reporting_time: tr.querySelector('.bp-reporting')?.value.trim() || '',
                departure_time: tr.querySelector('.bp-departure')?.value.trim() || '',
            });
        });

        const dropping = [];
        document.querySelectorAll('#dropping-points-body tr').forEach(tr => {
            const name = tr.querySelector('.dp-name')?.value.trim();
            if (!name) return;
            dropping.push({
                name,
                arrival_time: tr.querySelector('.dp-arrival')?.value.trim() || '',
            });
        });

        document.getElementById('boarding_points_json').value = JSON.stringify(boarding);
        document.getElementById('dropping_points_json').value = JSON.stringify(dropping);
        return true;
    };

    loadRoutePointsForm([], []);
})();
</script>
