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
                        <th>Seat Layout</th>
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
                            <td>
                                @if($bus->seat_layout === '1+2')
                                    1+2 (AC Executive)
                                @elseif($bus->seat_layout === 'sleeper')
                                    Sleeper (Multi-Deck)
                                @elseif($bus->seat_layout === '2+2_last5')
                                    Non-AC (5 in Back Row)
                                @else
                                    2+2 (Standard)
                                @endif
                            </td>
                            <td>{{ $bus->total_seats }} Seats</td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-secondary btn-sm edit-bus-btn"
                                        data-id="{{ $bus->id }}" data-action="{{ route('admin.buses.update', $bus->id) }}"
                                        data-operator="{{ $bus->operator_name }}" data-number="{{ $bus->coach_number }}"
                                        data-type="{{ $bus->coach_type }}" data-layout="{{ $bus->seat_layout }}"
                                        data-seats="{{ $bus->total_seats }}"
                                        data-grid="{{ json_encode($bus->seat_layout_grid) }}"
                                        onclick="handleEditBusClick(this)">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.buses.destroy', $bus->id) }}" method="POST"
                                        onsubmit="return confirm('Delete this coach? Related schedules will also be removed.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted)">No coaches
                                found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $buses->links('admin.partials.pagination') }}
    </div>

    <div class="booking-form-sidebar">
        <h3 class="booking-summary-title" id="bus-form-title">Register New Bus Coach</h3>
        <form class="booking-form-fields" id="bus-form" action="{{ route('admin.buses.store') }}" method="POST">
            @csrf
            @method('POST')
            <input type="hidden" name="_edit_id" value="">
            <div class="input-group">
                <label>Operator Company (e.g. Sonya Enterprise)</label>
                <input type="text" name="operator_name" class="coupon-input" placeholder="Operator Name" required
                    value="{{ old('operator_name') }}">
            </div>
            <div class="input-group">
                <label>Coach Registration No. (e.g. SE-4122)</label>
                <input type="text" name="coach_number" class="coupon-input" placeholder="Coach Number" required
                    value="{{ old('coach_number') }}">
            </div>
            <div class="input-group">
                <label>Comfort Class</label>
                <select name="coach_type" class="coupon-input">
                    <option value="AC">AC (Air Conditioned)</option>
                    <option value="Non AC">Non AC (Economy Class)</option>
                </select>
            </div>
            <div class="input-group">
                <label>Seat Layout Pattern</label>
                <select name="seat_layout" class="coupon-input" onchange="adjustDefaultSeats(this.value)">
                    <option value="2+2_last5">Non-AC Coach (40-48 seats, 5 seats in back row)</option>
                    <option value="1+2">AC Coach (1+2 layout)</option>
                    <option value="sleeper">Sleeper / E-Class Coach (Double Deck)</option>
                    <option value="2+2">Standard 2+2 (Economy)</option>
                </select>
            </div>
            <div class="input-group">
                <label>Total Seats Capacity</label>
                <input type="number" name="total_seats" class="coupon-input" value="36" required min="10" max="100"
                    onchange="document.getElementById('seat-layout-grid-input').value = ''">
            </div>
            <div class="input-group">
                <label>Seat Layout Grid</label>
                <button type="button" class="btn btn-secondary" onclick="openLayoutDesigner()"
                    style="width: 100%; height: 42px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span>🎨 Customize Layout (Drag & Drop)</span>
                </button>
                <input type="hidden" name="seat_layout_grid" id="seat-layout-grid-input">
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

<!-- Seat Layout Designer Modal -->
<div id="layout-designer-modal" class="modal-overlay" style="display: none;">
    <div class="layout-designer-content">
        <div class="modal-header">
            <h3>🎨 Dynamic Coach Seat Layout Designer</h3>
            <button type="button" class="close-btn" onclick="closeLayoutDesigner()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px; overflow: hidden; display: flex; flex-direction: column;">
            <p class="modal-hint" style="margin-bottom: 12px; text-align: center;">Drag cells to swap them. Click any
                cell to configure its type and label. Use sidebar tools to modify columns and rows.</p>

            <div class="designer-container">
                <div class="designer-canvas">
                    <div id="designer-work-area" class="designer-work-area">
                        <!-- Grids will be generated here -->
                    </div>
                </div>
                <div class="designer-controls">
                    <div class="control-section" id="grid-adjustments-container">
                        <h4>Grid Adjustments</h4>
                        <div class="grid-actions">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerRow()">➕ Add
                                Row</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerRow()">➖
                                Remove Row</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerColumn()">➕ Add
                                Col</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerColumn()">➖
                                Remove Col</button>
                        </div>
                    </div>
                    <div id="cell-editor-card" class="control-section cell-editor" style="display: none;">
                        <h4>Cell Configurator</h4>
                        <div class="input-group" style="margin-bottom: 10px;">
                            <label
                                style="font-size: 11px; text-transform: uppercase; font-weight: bold; margin-bottom: 4px;">Cell
                                Type</label>
                            <select id="designer-cell-type" class="coupon-input"
                                onchange="updateSelectedCellType(this.value)" style="height: 36px; padding: 4px 10px;">
                                <option value="seat">Seat</option>
                                <option value="empty">Empty Spacer</option>
                                <option value="aisle">Aisle</option>
                                <option value="driver">Driver Seat</option>
                                <option value="engine">Engine block</option>
                            </select>
                        </div>
                        <div class="input-group" id="designer-cell-label-group">
                            <label
                                style="font-size: 11px; text-transform: uppercase; font-weight: bold; margin-bottom: 4px;">Seat
                                Label</label>
                            <input type="text" id="designer-cell-label" class="coupon-input" placeholder="e.g. A1"
                                oninput="updateSelectedCellLabel(this.value)" style="height: 36px;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="resetToDefaultLayout()"
                style="margin-right: auto;">Reset to Default</button>
            <button type="button" class="btn btn-primary" onclick="saveLayoutDesign()">Save Layout Design</button>
        </div>
    </div>
</div>

<style>
    /* Layout Designer Modal styling */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: var(--bg-overlay);
        backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }

    .layout-designer-content {
        background-color: var(--bg-card);
        border: 2px solid var(--border-seat);
        border-radius: 20px;
        width: 90%;
        max-width: 950px;
        max-height: 95vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: var(--bg-card);
    }

    .modal-header h3 {
        color: var(--text-primary);
        font-family: var(--font-display);
        font-size: 18px;
    }

    .close-btn {
        background: none;
        border: none;
        color: var(--text-secondary);
        font-size: 32px;
        cursor: pointer;
        line-height: 1;
        transition: var(--transition);
    }

    .close-btn:hover {
        color: var(--danger);
    }

    .modal-body {
        flex: 1;
        overflow: hidden;
        background-color: var(--bg-card);
    }

    .modal-hint {
        color: var(--text-secondary);
        font-size: 13px;
    }

    .designer-container {
        display: flex;
        gap: 20px;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    .designer-canvas {
        flex: 1;
        overflow-y: auto;
        background-color: var(--designer-canvas-bg);
        border: 1px solid var(--border-seat);
        border-radius: 12px;
        padding: 20px;
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .designer-controls {
        width: 240px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        flex-shrink: 0;
    }

    .control-section {
        background-color: var(--designer-control-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 14px;
    }

    .control-section h4 {
        color: var(--text-primary);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 6px;
    }

    .grid-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .modal-footer {
        padding: 16px 20px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background-color: var(--bg-card);
    }

    .deck-title {
        color: var(--text-primary);
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 12px;
    }

    /* Designer blueprint grid styling */
    .designer-grid {
        background-color: var(--designer-grid-bg);
        border: 2px solid var(--border-seat);
        border-radius: 20px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .designer-cell {
        width: 44px;
        height: 44px;
        border: 1px dashed var(--designer-cell-border);
        background-color: var(--designer-cell-slot-bg);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        user-select: none;
        position: relative;
    }

    .designer-cell.cell-seat {
        background-color: #FFFFFF;
        border: 1px solid #D1D5DB;
        color: #374151;
    }

    .designer-cell.cell-driver {
        background-color: var(--success);
        border: 1px solid var(--success);
        color: var(--text-inverse);
    }

    .designer-cell.cell-engine {
        background-color: #64748B;
        border: 1px solid #475569;
        color: #F8FAFC;
    }

    .designer-cell.cell-aisle {
        border: none;
        background-color: transparent;
        color: var(--designer-aisle-color);
    }

    .designer-cell.cell-aisle::after {
        content: "↕";
    }

    .designer-cell.cell-empty {
        border: 1px dashed var(--designer-cell-border);
        background-color: transparent;
        color: var(--designer-empty-color);
    }

    .designer-cell.drag-over {
        background-color: var(--sidebar-active-bg);
        border-color: var(--primary);
    }

    .designer-cell.active-selection {
        outline: 3px solid var(--primary);
        outline-offset: 1px;
        z-index: 2;
    }
</style>

@vite('resources/js/admin/buses.js')