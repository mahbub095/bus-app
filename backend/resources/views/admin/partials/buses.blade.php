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
                                        data-id="{{ $bus->id }}"
                                        data-action="{{ route('admin.buses.update', $bus->id) }}"
                                        data-operator="{{ $bus->operator_name }}"
                                        data-number="{{ $bus->coach_number }}"
                                        data-type="{{ $bus->coach_type }}"
                                        data-layout="{{ $bus->seat_layout }}"
                                        data-seats="{{ $bus->total_seats }}"
                                        data-grid="{{ json_encode($bus->seat_layout_grid) }}"
                                        onclick="handleEditBusClick(this)">
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
                <input type="number" name="total_seats" class="coupon-input" value="36" required min="10" max="100" onchange="document.getElementById('seat-layout-grid-input').value = ''">
            </div>
            <div class="input-group">
                <label>Seat Layout Grid</label>
                <button type="button" class="btn btn-secondary" onclick="openLayoutDesigner()" style="width: 100%; height: 42px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px;">
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
            <p class="modal-hint" style="margin-bottom: 12px; text-align: center;">Drag cells to swap them. Click any cell to configure its type and label. Use sidebar tools to modify columns and rows.</p>
            
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
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerRow()">➕ Add Row</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerRow()">➖ Remove Row</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerColumn()">➕ Add Col</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerColumn()">➖ Remove Col</button>
                        </div>
                    </div>
                    <div id="cell-editor-card" class="control-section cell-editor" style="display: none;">
                        <h4>Cell Configurator</h4>
                        <div class="input-group" style="margin-bottom: 10px;">
                            <label style="font-size: 11px; text-transform: uppercase; font-weight: bold; margin-bottom: 4px;">Cell Type</label>
                            <select id="designer-cell-type" class="coupon-input" onchange="updateSelectedCellType(this.value)" style="height: 36px; padding: 4px 10px;">
                                <option value="seat">Seat</option>
                                <option value="empty">Empty Spacer</option>
                                <option value="aisle">Aisle</option>
                                <option value="driver">Driver Seat</option>
                                <option value="engine">Engine block</option>
                            </select>
                        </div>
                        <div class="input-group" id="designer-cell-label-group">
                            <label style="font-size: 11px; text-transform: uppercase; font-weight: bold; margin-bottom: 4px;">Seat Label</label>
                            <input type="text" id="designer-cell-label" class="coupon-input" placeholder="e.g. A1" oninput="updateSelectedCellLabel(this.value)" style="height: 36px;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="resetToDefaultLayout()" style="margin-right: auto;">Reset to Default</button>
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

<script>
function adjustDefaultSeats(layout) {
    const seatsInput = document.querySelector('form#bus-form input[name="total_seats"]');
    if (!seatsInput) return;
    document.getElementById('seat-layout-grid-input').value = '';
    
    if (layout === '2+2_last5') {
        seatsInput.value = 41;
    } else if (layout === '1+2') {
        seatsInput.value = 30;
    } else if (layout === 'sleeper') {
        seatsInput.value = 30;
    } else if (layout === '2+2') {
        seatsInput.value = 40;
    }
}

function handleEditBusClick(btn) {
    setCrudFormMode('bus-form', {
        mode: 'edit',
        id: btn.dataset.id,
        action: btn.dataset.action,
        title: 'Edit Coach ' + btn.dataset.number,
        submitLabel: 'Update Coach',
        fields: {
            operator_name: btn.dataset.operator,
            coach_number: btn.dataset.number,
            coach_type: btn.dataset.type,
            seat_layout: btn.dataset.layout,
            total_seats: btn.dataset.seats,
            seat_layout_grid: (btn.dataset.grid === 'null' || !btn.dataset.grid) ? '' : btn.dataset.grid
        }
    });
}

let currentGridState = null;
let selectedCellCoords = null;

function openLayoutDesigner() {
    const layout = document.querySelector('form#bus-form select[name="seat_layout"]').value;
    const totalSeats = parseInt(document.querySelector('form#bus-form input[name="total_seats"]').value, 10) || 36;
    const gridInput = document.getElementById('seat-layout-grid-input');
    
    selectedCellCoords = null;
    document.getElementById('cell-editor-card').style.display = 'none';

    let gridData = null;
    if (gridInput.value) {
        try {
            gridData = JSON.parse(gridInput.value);
            if (typeof gridData === 'string') {
                gridData = JSON.parse(gridData);
            }
        } catch (e) {
            console.error("Failed to parse grid data", e);
        }
    }
    
    if (gridData && typeof gridData === 'object') {
        currentGridState = gridData;
    } else {
        currentGridState = generateDefaultGrid(layout, totalSeats);
    }
    
    document.getElementById('layout-designer-modal').style.display = 'flex';
    renderEditorGrid();
    renderGridAdjustmentsControls(currentGridState.lower !== undefined);
}

function closeLayoutDesigner() {
    document.getElementById('layout-designer-modal').style.display = 'none';
}

function generateDefaultGrid(layout, totalSeats) {
    const rowLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
    
    if (layout === '2+2_last5') {
        const grid = [];
        grid.push([
            { type: 'engine', label: 'Engine' },
            { type: 'empty' },
            { type: 'aisle' },
            { type: 'empty' },
            { type: 'driver', label: 'Driver' }
        ]);

        let remainingSeats = totalSeats - 5;
        let normalRows = Math.ceil(remainingSeats / 4);
        
        let seatsCount = 0;
        let rIndex = 0;
        for (let r = 0; r < normalRows; r++) {
            const rowLetter = rowLetters[rIndex++];
            const row = [];
            row.push({ type: 'seat', label: rowLetter + '1' });
            row.push({ type: 'seat', label: rowLetter + '2' });
            row.push({ type: 'aisle' });
            row.push({ type: 'seat', label: rowLetter + '3' });
            row.push({ type: 'seat', label: rowLetter + '4' });
            grid.push(row);
        }

        const lastRowLetter = rowLetters[rIndex++];
        const lastRow = [];
        for (let num = 1; num <= 5; num++) {
            lastRow.push({ type: 'seat', label: lastRowLetter + num });
        }
        grid.push(lastRow);
        
        return grid;
    } else if (layout === '1+2') {
        const grid = [];
        grid.push([
            { type: 'engine', label: 'Engine' },
            { type: 'aisle' },
            { type: 'empty' },
            { type: 'driver', label: 'Driver' }
        ]);

        let normalRows = Math.ceil(totalSeats / 3);
        let rIndex = 0;
        for (let r = 0; r < normalRows; r++) {
            const rowLetter = rowLetters[rIndex++];
            const row = [];
            row.push({ type: 'seat', label: rowLetter + '1' });
            row.push({ type: 'aisle' });
            row.push({ type: 'seat', label: rowLetter + '2' });
            row.push({ type: 'seat', label: rowLetter + '3' });
            grid.push(row);
        }
        return grid;
    } else if (layout === 'sleeper') {
        const lowerCount = Math.ceil(totalSeats / 2);
        const upperCount = totalSeats - lowerCount;
        
        const lowerGrid = [];
        lowerGrid.push([
            { type: 'engine', label: 'Engine' },
            { type: 'aisle' },
            { type: 'empty' },
            { type: 'driver', label: 'Driver' }
        ]);
        let remainingSeats = lowerCount - 4;
        let lowerRows = Math.ceil(remainingSeats / 3);
        if (lowerRows < 0) lowerRows = 0;
        let rIndex = 0;
        for (let r = 0; r < lowerRows; r++) {
            const rowLetter = rowLetters[rIndex++];
            const row = [];
            row.push({ type: 'seat', label: 'L-' + rowLetter + '1' });
            row.push({ type: 'aisle' });
            row.push({ type: 'seat', label: 'L-' + rowLetter + '2' });
            row.push({ type: 'seat', label: 'L-' + rowLetter + '3' });
            lowerGrid.push(row);
        }
        const lastRowLetter = rowLetters[rIndex++];
        const lastRow = [];
        for (let num = 1; num <= 4; num++) {
            lastRow.push({ type: 'seat', label: 'L-' + lastRowLetter + num });
        }
        lowerGrid.push(lastRow);

        const upperGrid = [];
        upperGrid.push([
            { type: 'empty' },
            { type: 'aisle' },
            { type: 'empty' },
            { type: 'empty' }
        ]);
        let remainingSeatsU = upperCount - 4;
        let upperRows = Math.ceil(remainingSeatsU / 3);
        if (upperRows < 0) upperRows = 0;
        rIndex = 0;
        for (let r = 0; r < upperRows; r++) {
            const rowLetter = rowLetters[rIndex++];
            const row = [];
            row.push({ type: 'seat', label: 'U-' + rowLetter + '1' });
            row.push({ type: 'aisle' });
            row.push({ type: 'seat', label: 'U-' + rowLetter + '2' });
            row.push({ type: 'seat', label: 'U-' + rowLetter + '3' });
            upperGrid.push(row);
        }
        const lastRowLetterU = rowLetters[rIndex++];
        const lastRowU = [];
        for (let num = 1; num <= 4; num++) {
            lastRowU.push({ type: 'seat', label: 'U-' + lastRowLetterU + num });
        }
        upperGrid.push(lastRowU);

        return { lower: lowerGrid, upper: upperGrid };
    }
    
    const grid = [];
    grid.push([
        { type: 'engine', label: 'Engine' },
        { type: 'empty' },
        { type: 'aisle' },
        { type: 'empty' },
        { type: 'driver', label: 'Driver' }
    ]);
    let normalRows = Math.ceil(totalSeats / 4);
    let rIndex = 0;
    for (let r = 0; r < normalRows; r++) {
        const rowLetter = rowLetters[rIndex++];
        const row = [];
        row.push({ type: 'seat', label: rowLetter + '1' });
        row.push({ type: 'seat', label: rowLetter + '2' });
        row.push({ type: 'aisle' });
        row.push({ type: 'seat', label: rowLetter + '3' });
        row.push({ type: 'seat', label: rowLetter + '4' });
        grid.push(row);
    }
    return grid;
}

function resetToDefaultLayout() {
    const layout = document.querySelector('form#bus-form select[name="seat_layout"]').value;
    const totalSeats = parseInt(document.querySelector('form#bus-form input[name="total_seats"]').value, 10) || 36;
    currentGridState = generateDefaultGrid(layout, totalSeats);
    selectedCellCoords = null;
    document.getElementById('cell-editor-card').style.display = 'none';
    renderEditorGrid();
}

function renderEditorGrid() {
    const area = document.getElementById('designer-work-area');
    area.innerHTML = '';
    
    const isSleeper = currentGridState.lower !== undefined;
    
    if (isSleeper) {
        area.appendChild(createDeckDesignerGrid(currentGridState.lower, 'lower', 'Lower Deck'));
        area.appendChild(createDeckDesignerGrid(currentGridState.upper, 'upper', 'Upper Deck'));
    } else {
        area.appendChild(createDeckDesignerGrid(currentGridState, null, 'Bus Layout Grid'));
    }
}

function createDeckDesignerGrid(grid, deckKey, title) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `<h4 class="deck-title">${title}</h4>`;
    
    const gridDiv = document.createElement('div');
    gridDiv.className = 'designer-grid';
    gridDiv.style.display = 'flex';
    gridDiv.style.flexDirection = 'column';
    gridDiv.style.gap = '8px';
    
    grid.forEach((row, rIdx) => {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'seat-row';
        rowDiv.style.display = 'flex';
        rowDiv.style.gap = '8px';
        rowDiv.style.justifyContent = 'space-between';
        
        row.forEach((cell, cIdx) => {
            const cellDiv = document.createElement('div');
            
            cellDiv.className = 'designer-cell';
            if (cell.type === 'seat') {
                cellDiv.classList.add('cell-seat');
                cellDiv.textContent = cell.label;
            } else if (cell.type === 'driver') {
                cellDiv.classList.add('cell-driver');
                cellDiv.innerHTML = `
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                        <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2.5"/>
                        <circle cx="12" cy="12" r="3.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                        <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>
                        <line x1="12" y1="8.5" x2="12" y2="2.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="9" y1="14" x2="4" y2="18.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="15" y1="14" x2="20" y2="18.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                `;
            } else if (cell.type === 'engine') {
                cellDiv.classList.add('cell-engine');
                cellDiv.textContent = 'Engine';
            } else if (cell.type === 'aisle') {
                cellDiv.classList.add('cell-aisle');
            } else {
                cellDiv.classList.add('cell-empty');
                cellDiv.textContent = '';
            }
            
            cellDiv.draggable = true;
            cellDiv.dataset.row = rIdx;
            cellDiv.dataset.col = cIdx;
            if (deckKey) cellDiv.dataset.deck = deckKey;
            
            // Check active selection highlight
            if (selectedCellCoords && 
                selectedCellCoords.row === rIdx && 
                selectedCellCoords.col === cIdx && 
                selectedCellCoords.deck === deckKey) {
                cellDiv.classList.add('active-selection');
            }
            
            cellDiv.addEventListener('dragstart', handleDragStart);
            cellDiv.addEventListener('dragover', handleDragOver);
            cellDiv.addEventListener('dragleave', handleDragLeave);
            cellDiv.addEventListener('drop', handleDrop);
            
            // Selection click
            cellDiv.addEventListener('click', function(e) {
                handleCellClick(rIdx, cIdx, deckKey);
            });
            
            rowDiv.appendChild(cellDiv);
        });
        gridDiv.appendChild(rowDiv);
    });
    
    wrapper.appendChild(gridDiv);
    return wrapper;
}

function handleDragStart(e) {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', JSON.stringify({
        row: parseInt(this.dataset.row, 10),
        col: parseInt(this.dataset.col, 10),
        deck: this.dataset.deck || null
    }));
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    this.classList.add('drag-over');
    return false;
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    this.classList.remove('drag-over');
    
    const src = JSON.parse(e.dataTransfer.getData('text/plain'));
    const destRow = parseInt(this.dataset.row, 10);
    const destCol = parseInt(this.dataset.col, 10);
    const destDeck = this.dataset.deck || null;
    
    swapCells(src.row, src.col, src.deck, destRow, destCol, destDeck);
    
    // Maintain selection coords after swap
    if (selectedCellCoords && 
        selectedCellCoords.row === src.row && 
        selectedCellCoords.col === src.col && 
        selectedCellCoords.deck === src.deck) {
        selectedCellCoords = { row: destRow, col: destCol, deck: destDeck };
    } else if (selectedCellCoords && 
        selectedCellCoords.row === destRow && 
        selectedCellCoords.col === destCol && 
        selectedCellCoords.deck === destDeck) {
        selectedCellCoords = { row: src.row, col: src.col, deck: src.deck };
    }
    
    renderEditorGrid();
    return false;
}

function swapCells(srcRow, srcCol, srcDeck, destRow, destCol, destDeck) {
    if (srcDeck) {
        const temp = currentGridState[srcDeck][srcRow][srcCol];
        currentGridState[srcDeck][srcRow][srcCol] = currentGridState[destDeck][destRow][destCol];
        currentGridState[destDeck][destRow][destCol] = temp;
    } else {
        const temp = currentGridState[srcRow][srcCol];
        currentGridState[srcRow][srcCol] = currentGridState[destRow][destCol];
        currentGridState[destRow][destCol] = temp;
    }
}

function handleCellClick(row, col, deck) {
    selectedCellCoords = { row, col, deck };
    
    document.querySelectorAll('.designer-cell').forEach(c => c.classList.remove('active-selection'));
    
    const selector = deck 
        ? `.designer-cell[data-row="${row}"][data-col="${col}"][data-deck="${deck}"]`
        : `.designer-cell[data-row="${row}"][data-col="${col}"]`;
    const cellEl = document.querySelector(selector);
    if (cellEl) cellEl.classList.add('active-selection');
    
    const cell = deck 
        ? currentGridState[deck][row][col]
        : currentGridState[row][col];
        
    document.getElementById('designer-cell-type').value = cell.type;
    const labelGroup = document.getElementById('designer-cell-label-group');
    const labelInput = document.getElementById('designer-cell-label');
    
    if (cell.type === 'seat') {
        labelGroup.style.display = 'block';
        labelInput.value = cell.label || '';
    } else {
        labelGroup.style.display = 'none';
        labelInput.value = '';
    }
    
    document.getElementById('cell-editor-card').style.display = 'block';
}

function updateSelectedCellType(type) {
    if (!selectedCellCoords) return;
    const { row, col, deck } = selectedCellCoords;
    const cell = deck ? currentGridState[deck][row][col] : currentGridState[row][col];
    
    cell.type = type;
    if (type === 'seat') {
        cell.label = cell.label || 'New';
        document.getElementById('designer-cell-label-group').style.display = 'block';
        document.getElementById('designer-cell-label').value = cell.label;
    } else {
        delete cell.label;
        document.getElementById('designer-cell-label-group').style.display = 'none';
    }
    
    renderEditorGrid();
}

function updateSelectedCellLabel(label) {
    if (!selectedCellCoords) return;
    const { row, col, deck } = selectedCellCoords;
    const cell = deck ? currentGridState[deck][row][col] : currentGridState[row][col];
    
    cell.label = label;
    
    const selector = deck 
        ? `.designer-cell[data-row="${row}"][data-col="${col}"][data-deck="${deck}"]`
        : `.designer-cell[data-row="${row}"][data-col="${col}"]`;
    const cellEl = document.querySelector(selector);
    if (cellEl && cell.type === 'seat') {
        cellEl.textContent = label;
    }
}

function addDesignerRow(deck = null) {
    const isSleeper = currentGridState.lower !== undefined;
    if (isSleeper) {
        if (deck) {
            const colCount = currentGridState[deck][0].length;
            const newRow = Array.from({ length: colCount }, () => ({ type: 'empty' }));
            currentGridState[deck].push(newRow);
        } else {
            const colCountL = currentGridState.lower[0].length;
            const colCountU = currentGridState.upper[0].length;
            currentGridState.lower.push(Array.from({ length: colCountL }, () => ({ type: 'empty' })));
            currentGridState.upper.push(Array.from({ length: colCountU }, () => ({ type: 'empty' })));
        }
    } else {
        const colCount = currentGridState[0].length;
        const newRow = Array.from({ length: colCount }, () => ({ type: 'empty' }));
        currentGridState.push(newRow);
    }
    renderEditorGrid();
}

function removeDesignerRow(deck = null) {
    const isSleeper = currentGridState.lower !== undefined;
    if (isSleeper) {
        if (deck) {
            if (currentGridState[deck].length > 1) {
                currentGridState[deck].pop();
            }
        } else {
            if (currentGridState.lower.length > 2) {
                currentGridState.lower.pop();
                currentGridState.upper.pop();
            }
        }
    } else {
        if (currentGridState.length > 2) {
            currentGridState.pop();
        }
    }
    selectedCellCoords = null;
    document.getElementById('cell-editor-card').style.display = 'none';
    renderEditorGrid();
}

function addDesignerColumn(deck = null) {
    const isSleeper = currentGridState.lower !== undefined;
    if (isSleeper) {
        if (deck) {
            currentGridState[deck].forEach(row => row.push({ type: 'empty' }));
        } else {
            currentGridState.lower.forEach(row => row.push({ type: 'empty' }));
            currentGridState.upper.forEach(row => row.push({ type: 'empty' }));
        }
    } else {
        currentGridState.forEach(row => row.push({ type: 'empty' }));
    }
    renderEditorGrid();
}

function removeDesignerColumn(deck = null) {
    const isSleeper = currentGridState.lower !== undefined;
    if (isSleeper) {
        if (deck) {
            if (currentGridState[deck][0].length > 1) {
                currentGridState[deck].forEach(row => row.pop());
            }
        } else {
            if (currentGridState.lower[0].length > 2) {
                currentGridState.lower.forEach(row => row.pop());
                currentGridState.upper.forEach(row => row.pop());
            }
        }
    } else {
        if (currentGridState[0].length > 2) {
            currentGridState.forEach(row => row.pop());
        }
    }
    selectedCellCoords = null;
    document.getElementById('cell-editor-card').style.display = 'none';
    renderEditorGrid();
}

function renderGridAdjustmentsControls(isSleeper) {
    const container = document.getElementById('grid-adjustments-container');
    if (!container) return;
    
    if (isSleeper) {
        container.innerHTML = `
            <h4>Grid Adjustments</h4>
            <div style="margin-bottom: 12px;">
                <div style="font-size: 11px; font-weight: bold; color: var(--primary); text-transform: uppercase; margin-bottom: 6px;">Lower Deck</div>
                <div class="grid-actions" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerRow('lower')">➕ Add Row</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerRow('lower')">➖ Remove Row</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerColumn('lower')">➕ Add Col</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerColumn('lower')">➖ Remove Col</button>
                </div>
            </div>
            <div>
                <div style="font-size: 11px; font-weight: bold; color: var(--primary); text-transform: uppercase; margin-bottom: 6px;">Upper Deck</div>
                <div class="grid-actions" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerRow('upper')">➕ Add Row</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerRow('upper')">➖ Remove Row</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerColumn('upper')">➕ Add Col</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerColumn('upper')">➖ Remove Col</button>
                </div>
            </div>
        `;
    } else {
        container.innerHTML = `
            <h4>Grid Adjustments</h4>
            <div class="grid-actions" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerRow()">➕ Add Row</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerRow()">➖ Remove Row</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerColumn()">➕ Add Col</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerColumn()">➖ Remove Col</button>
            </div>
        `;
    }
}

function saveLayoutDesign() {
    const gridInput = document.getElementById('seat-layout-grid-input');
    gridInput.value = JSON.stringify(currentGridState);
    
    let seatCount = 0;
    const isSleeper = currentGridState.lower !== undefined;
    
    const countSeats = (grid) => {
        let count = 0;
        grid.forEach(row => {
            row.forEach(cell => {
                if (cell.type === 'seat' && cell.label) {
                    count++;
                }
            });
        });
        return count;
    };
    
    if (isSleeper) {
        seatCount = countSeats(currentGridState.lower) + countSeats(currentGridState.upper);
    } else {
        seatCount = countSeats(currentGridState);
    }
    
    const seatsInput = document.querySelector('form#bus-form input[name="total_seats"]');
    if (seatsInput) {
        seatsInput.value = seatCount;
    }
    
    closeLayoutDesigner();
}
</script>
