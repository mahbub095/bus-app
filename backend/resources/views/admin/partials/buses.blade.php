<div class="admin-sections-layout" style="grid-column: 1 / -1;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Fleet Coaches</h3>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Coach Registration No.</th>
                        <th>Operator Company Name</th>
                        <th>Coach Type</th>
                        <th>Total Seat Capacity</th>
                        <th>Operations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($buses as $bus)
                        <tr>
                            <td style="font-weight: bold; color: var(--primary)">{{ $bus->coach_number }}</td>
                            <td style="font-weight: 600;">{{ $bus->operator_name }}</td>
                            <td>
                                <span class="coach-tag {{ $bus->coach_type === 'AC' ? 'ac' : '' }}">
                                    {{ $bus->coach_type }}
                                </span>
                            </td>
                            <td>{{ $bus->total_seats }} Seats</td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="setCrudFormMode('bus-form', {
                                            mode: 'edit',
                                            id: {{ $bus->id }},
                                            action: '{{ route('admin.buses.update', $bus->id) }}',
                                            title: 'Edit Coach {{ $bus->coach_number }}',
                                            submitLabel: 'Update Coach',
                                            fields: {
                                                operator_name: {{ json_encode($bus->operator_name) }},
                                                coach_number: {{ json_encode($bus->coach_number) }},
                                                coach_type: {{ json_encode($bus->coach_type) }},
                                                total_seats: {{ json_encode($bus->total_seats) }}
                                            }
                                        })">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.buses.destroy', $bus->id) }}" method="POST" onsubmit="return confirm('Delete this coach? Related schedules will also be removed.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted)">No coaches found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="booking-form-sidebar">
        <h3 class="booking-summary-title" id="bus-form-title">Register New Bus Coach</h3>
        <form class="booking-form-fields" id="bus-form" action="{{ route('admin.buses.store') }}" method="POST">
            @csrf
            @method('POST')
            <input type="hidden" name="_edit_id" value="">
            <div class="input-group">
                <label>Operator Company (e.g. Sonya Enterprise)</label>
                <input type="text" name="operator_name" class="coupon-input" placeholder="Operator Name" required value="{{ old('operator_name') }}">
            </div>
            <div class="input-group">
                <label>Coach Registration No. (e.g. SE-4122)</label>
                <input type="text" name="coach_number" class="coupon-input" placeholder="Coach Number" required value="{{ old('coach_number') }}">
            </div>
            <div class="input-group">
                <label>Comfort Class</label>
                <select name="coach_type" class="coupon-input">
                    <option value="AC">AC (Air Conditioned)</option>
                    <option value="Non AC">Non AC (Economy Class)</option>
                </select>
            </div>
            <div class="input-group">
                <label>Total Seats Capacity</label>
                <input type="number" name="total_seats" class="coupon-input" value="36" required min="10" max="100">
            </div>
            <button class="btn btn-primary" id="bus-form-submit" type="submit" style="height: 42px; margin-top: 10px;">
                Register Coach
            </button>
            <button type="button" class="btn btn-secondary form-cancel-btn" id="bus-form-cancel"
                onclick="resetCrudForm('bus-form', '{{ route('admin.buses.store') }}', 'Register New Bus Coach', 'Register Coach')">
                Cancel Edit
            </button>
        </form>
    </div>

</div>
