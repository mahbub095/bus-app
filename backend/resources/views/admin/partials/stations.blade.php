<div class="admin-sections-layout" style="grid-column: 1 / -1;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Active Boarding Terminals</h3>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Terminal Station Name</th>
                        <th>District Location</th>
                        <th>Created Date</th>
                        <th>Operations</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stations as $st)
                        <tr>
                            <td>#{{ $st->id }}</td>
                            <td style="font-weight: bold; color: #fff;">{{ $st->name }}</td>
                            <td>{{ $st->district ?? 'N/A' }}</td>
                            <td style="color: var(--text-secondary)">{{ $st->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="setCrudFormMode('station-form', {
                                            mode: 'edit',
                                            id: {{ $st->id }},
                                            action: '{{ route('admin.stations.update', $st->id) }}',
                                            title: 'Edit Station #{{ $st->id }}',
                                            submitLabel: 'Update Station',
                                            fields: {
                                                name: {{ json_encode($st->name) }},
                                                district: {{ json_encode($st->district ?? '') }}
                                            }
                                        })">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.stations.destroy', $st->id) }}" method="POST" onsubmit="return confirm('Delete this station?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted)">No stations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="booking-form-sidebar">
        <h3 class="booking-summary-title" id="station-form-title">Add Station Terminal</h3>
        <form class="booking-form-fields" id="station-form" action="{{ route('admin.stations.store') }}" method="POST">
            @csrf
            @method('POST')
            <input type="hidden" name="_edit_id" value="">
            <div class="input-group">
                <label>Terminal City Name (e.g. SYLHET)</label>
                <input type="text" name="name" class="coupon-input" placeholder="Name" required value="{{ old('name') }}">
            </div>
            <div class="input-group">
                <label>District Location (e.g. Sylhet)</label>
                <input type="text" name="district" class="coupon-input" placeholder="District" value="{{ old('district') }}">
            </div>
            <button class="btn btn-primary" id="station-form-submit" type="submit" style="height: 42px; margin-top: 10px;">
                Create Station Terminal
            </button>
            <button type="button" class="btn btn-secondary form-cancel-btn" id="station-form-cancel"
                onclick="resetCrudForm('station-form', '{{ route('admin.stations.store') }}', 'Add Station Terminal', 'Create Station Terminal')">
                Cancel Edit
            </button>
        </form>
    </div>

</div>
