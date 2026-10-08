import { generateDefaultGrid, DRIVER_SVG } from './bus-layout-helper.js';

/**
 * buses.js
 *
 * Two independent features for the Coaches admin panel:
 *
 *  1. Edit handler — reads data attributes from the Edit button and populates
 *     the coach registration form via setCrudFormMode() from layout.js.
 *
 *  2. Seat Layout Designer — a drag-and-drop modal grid editor that lets
 *     admins visually design custom seat maps for any coach type.
 *     The final grid is serialised to JSON and stored in a hidden input,
 *     which the server saves alongside the coach record.
 *
 * Public API (called from buses.blade.php via onchange / onclick):
 *   adjustDefaultSeats(layout)          — update the seat count when the layout type changes
 *   handleEditBusClick(btn)             — populate the form from a table row's Edit button
 *   openLayoutDesigner()                — open the designer modal
 *   closeLayoutDesigner()               — close the designer modal
 *   resetToDefaultLayout()              — regenerate the default grid for current layout/seats
 *   saveLayoutDesign()                  — serialise the grid and close the modal
 *   updateSelectedCellType(type)        — change the type of the selected cell
 *   updateSelectedCellLabel(label)      — update the seat label of the selected cell
 *   addDesignerRow(deck?)               — add an empty row to the grid
 *   removeDesignerRow(deck?)            — remove the last row from the grid
 *   addDesignerColumn(deck?)            — add an empty column to the grid
 *   removeDesignerColumn(deck?)         — remove the last column from the grid
 */

// ─── Edit handler ─────────────────────────────────────────────────────────────

/**
 * When the seat layout dropdown changes, reset the hidden grid input and
 * update the total seats to a sensible default for that layout type.
 */
function adjustDefaultSeats(layout) {
    const seatsInput = document.querySelector('form#bus-form input[name="total_seats"]');
    if (!seatsInput) return;

    // Changing the layout invalidates any previously designed grid
    document.getElementById('seat-layout-grid-input').value = '';

    const defaults = { '2+2_last5': 41, '1+2': 30, 'sleeper': 30, '2+2': 40 };
    if (layout in defaults) seatsInput.value = defaults[layout];
}

/**
 * Read all coach data from the Edit button's data-* attributes and switch
 * the registration form into edit mode.
 */
function handleEditBusClick(btn) {
    setCrudFormMode('bus-form', {
        mode:        'edit',
        id:          btn.dataset.id,
        action:      btn.dataset.action,
        title:       `Edit Coach ${btn.dataset.number}`,
        submitLabel: 'Update Coach',
        fields: {
            operator_name:    btn.dataset.operator,
            coach_number:     btn.dataset.number,
            coach_type:       btn.dataset.type,
            seat_layout:      btn.dataset.layout,
            total_seats:      btn.dataset.seats,
            // Treat literal "null" strings and empty values as an empty grid
            seat_layout_grid: (btn.dataset.grid === 'null' || !btn.dataset.grid) ? '' : btn.dataset.grid,
        },
    });
}

// ─── Seat Layout Designer — state ─────────────────────────────────────────────

/**
 * The current grid being edited.
 * For standard layouts: a 2-D array of cell objects — grid[row][col].
 * For sleeper layouts: { lower: grid[][], upper: grid[][] }.
 *
 * Each cell object: { type: 'seat'|'aisle'|'empty'|'driver'|'engine', label?: string }
 */
let currentGridState = null;

/**
 * The currently selected cell's position.
 * { row: number, col: number, deck: string|null }
 */
let selectedCellCoords = null;

// ─── Seat Layout Designer — modal open/close ──────────────────────────────────

/** Open the designer modal, loading an existing grid or generating a default one. */
function openLayoutDesigner() {
    const layout     = document.querySelector('form#bus-form select[name="seat_layout"]').value;
    const totalSeats = parseInt(document.querySelector('form#bus-form input[name="total_seats"]').value, 10) || 36;
    const gridInput  = document.getElementById('seat-layout-grid-input');

    // Clear any stale cell selection from a previous session
    selectedCellCoords = null;
    document.getElementById('cell-editor-card').style.display = 'none';

    // Try to parse an already-saved grid from the hidden input
    let existingGrid = null;
    if (gridInput.value) {
        try {
            existingGrid = JSON.parse(gridInput.value);
            // Handle double-serialised strings (legacy data)
            if (typeof existingGrid === 'string') existingGrid = JSON.parse(existingGrid);
        } catch {
            console.error('Could not parse saved grid data — falling back to default.');
        }
    }

    currentGridState = (existingGrid && typeof existingGrid === 'object')
        ? existingGrid
        : generateDefaultGrid(layout, totalSeats);

    document.getElementById('layout-designer-modal').style.display = 'flex';
    renderEditorGrid();
    renderGridAdjustmentControls(currentGridState.lower !== undefined);
}

/** Close the designer modal without saving. */
function closeLayoutDesigner() {
    document.getElementById('layout-designer-modal').style.display = 'none';
}

// ─── Seat Layout Designer — grid generation ───────────────────────────────────
// generateDefaultGrid is imported from bus-layout-helper.js

/** Rebuild the grid from scratch using the current layout/seat count values. */
function resetToDefaultLayout() {
    const layout     = document.querySelector('form#bus-form select[name="seat_layout"]').value;
    const totalSeats = parseInt(document.querySelector('form#bus-form input[name="total_seats"]').value, 10) || 36;

    currentGridState   = generateDefaultGrid(layout, totalSeats);
    selectedCellCoords = null;
    document.getElementById('cell-editor-card').style.display = 'none';
    renderEditorGrid();
}

// ─── Seat Layout Designer — rendering ────────────────────────────────────────

/** Re-render the entire designer grid area from currentGridState. */
function renderEditorGrid() {
    const area      = document.getElementById('designer-work-area');
    area.innerHTML  = '';

    const isSleeper = currentGridState.lower !== undefined;

    if (isSleeper) {
        area.appendChild(buildDeckElement(currentGridState.lower, 'lower', 'Lower Deck'));
        area.appendChild(buildDeckElement(currentGridState.upper, 'upper', 'Upper Deck'));
    } else {
        area.appendChild(buildDeckElement(currentGridState, null, 'Bus Layout Grid'));
    }
}

/**
 * Build a DOM element representing one deck (or the whole bus for non-sleeper).
 * @param {Array}        grid
 * @param {string|null}  deckKey  — 'lower', 'upper', or null
 * @param {string}       title
 */
function buildDeckElement(grid, deckKey, title) {
    const wrapper       = document.createElement('div');
    wrapper.innerHTML   = `<h4 class="deck-title">${title}</h4>`;

    const gridEl        = document.createElement('div');
    gridEl.className    = 'designer-grid';
    gridEl.style.cssText = 'display:flex; flex-direction:column; gap:8px;';

    grid.forEach((row, rIdx) => {
        const rowEl        = document.createElement('div');
        rowEl.className    = 'seat-row';
        rowEl.style.cssText = 'display:flex; gap:8px; justify-content:space-between;';

        row.forEach((cell, cIdx) => {
            rowEl.appendChild(buildCellElement(cell, rIdx, cIdx, deckKey));
        });

        gridEl.appendChild(rowEl);
    });

    wrapper.appendChild(gridEl);
    return wrapper;
}

/**
 * Build a single designer cell DOM element with correct CSS class, content,
 * drag-and-drop handlers, and click selection.
 */
function buildCellElement(cell, rIdx, cIdx, deckKey) {
    const el        = document.createElement('div');
    el.className    = 'designer-cell';
    el.draggable    = true;
    el.dataset.row  = rIdx;
    el.dataset.col  = cIdx;
    if (deckKey) el.dataset.deck = deckKey;

    // Apply type-specific class and content
    switch (cell.type) {
        case 'seat':
            el.classList.add('cell-seat');
            el.textContent = cell.label;
            break;
        case 'driver':
            el.classList.add('cell-driver');
            el.innerHTML = DRIVER_SVG;
            break;
        case 'engine':
            el.classList.add('cell-engine');
            el.textContent = 'Engine';
            break;
        case 'aisle':
            el.classList.add('cell-aisle');
            break;
        default:
            el.classList.add('cell-empty');
    }

    // Highlight if this cell is currently selected
    if (
        selectedCellCoords &&
        selectedCellCoords.row === rIdx &&
        selectedCellCoords.col === cIdx &&
        selectedCellCoords.deck === deckKey
    ) {
        el.classList.add('active-selection');
    }

    el.addEventListener('dragstart',  handleDragStart);
    el.addEventListener('dragover',   handleDragOver);
    el.addEventListener('dragleave',  handleDragLeave);
    el.addEventListener('drop',       handleDrop);
    el.addEventListener('click',      () => handleCellClick(rIdx, cIdx, deckKey));

    return el;
}

// DRIVER_SVG is imported from bus-layout-helper.js

// ─── Seat Layout Designer — drag and drop ─────────────────────────────────────

function handleDragStart(e) {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', JSON.stringify({
        row:  parseInt(this.dataset.row,  10),
        col:  parseInt(this.dataset.col,  10),
        deck: this.dataset.deck || null,
    }));
}

function handleDragOver(e) {
    e.preventDefault();
    this.classList.add('drag-over');
}

function handleDragLeave() {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    e.stopPropagation();
    this.classList.remove('drag-over');

    const src      = JSON.parse(e.dataTransfer.getData('text/plain'));
    const destRow  = parseInt(this.dataset.row,  10);
    const destCol  = parseInt(this.dataset.col,  10);
    const destDeck = this.dataset.deck || null;

    swapCells(src.row, src.col, src.deck, destRow, destCol, destDeck);

    // Keep the selection tracking in sync after a swap
    if (selectedCellCoords) {
        const sel = selectedCellCoords;
        if (sel.row === src.row && sel.col === src.col && sel.deck === src.deck) {
            selectedCellCoords = { row: destRow, col: destCol, deck: destDeck };
        } else if (sel.row === destRow && sel.col === destCol && sel.deck === destDeck) {
            selectedCellCoords = { row: src.row, col: src.col, deck: src.deck };
        }
    }

    renderEditorGrid();
}

/** Swap two cells in currentGridState (mutates in place). */
function swapCells(srcRow, srcCol, srcDeck, destRow, destCol, destDeck) {
    if (srcDeck) {
        const temp = currentGridState[srcDeck][srcRow][srcCol];
        currentGridState[srcDeck][srcRow][srcCol]   = currentGridState[destDeck][destRow][destCol];
        currentGridState[destDeck][destRow][destCol] = temp;
    } else {
        const temp = currentGridState[srcRow][srcCol];
        currentGridState[srcRow][srcCol]   = currentGridState[destRow][destCol];
        currentGridState[destRow][destCol] = temp;
    }
}

// ─── Seat Layout Designer — cell selection ────────────────────────────────────

/** Select a cell and open the cell configurator panel. */
function handleCellClick(row, col, deck) {
    selectedCellCoords = { row, col, deck };

    // Update the visual highlight
    document.querySelectorAll('.designer-cell').forEach(c => c.classList.remove('active-selection'));
    const selector = deck
        ? `.designer-cell[data-row="${row}"][data-col="${col}"][data-deck="${deck}"]`
        : `.designer-cell[data-row="${row}"][data-col="${col}"]`;
    document.querySelector(selector)?.classList.add('active-selection');

    // Populate the cell editor panel
    const cell       = deck ? currentGridState[deck][row][col] : currentGridState[row][col];
    const labelGroup = document.getElementById('designer-cell-label-group');
    const labelInput = document.getElementById('designer-cell-label');

    document.getElementById('designer-cell-type').value = cell.type;

    if (cell.type === 'seat') {
        labelGroup.style.display = 'block';
        labelInput.value         = cell.label || '';
    } else {
        labelGroup.style.display = 'none';
        labelInput.value         = '';
    }

    document.getElementById('cell-editor-card').style.display = 'block';
}

/** Change the type of the currently selected cell and re-render. */
function updateSelectedCellType(type) {
    if (!selectedCellCoords) return;
    const { row, col, deck } = selectedCellCoords;
    const cell = deck ? currentGridState[deck][row][col] : currentGridState[row][col];

    cell.type = type;

    if (type === 'seat') {
        cell.label = cell.label || 'New';
        document.getElementById('designer-cell-label-group').style.display = 'block';
        document.getElementById('designer-cell-label').value               = cell.label;
    } else {
        delete cell.label;
        document.getElementById('designer-cell-label-group').style.display = 'none';
    }

    renderEditorGrid();
}

/**
 * Update the seat label of the currently selected cell.
 * Updates the DOM in place rather than re-rendering the whole grid to avoid
 * disrupting any focus/selection state.
 */
function updateSelectedCellLabel(label) {
    if (!selectedCellCoords) return;
    const { row, col, deck } = selectedCellCoords;
    const cell = deck ? currentGridState[deck][row][col] : currentGridState[row][col];
    cell.label = label;

    const selector = deck
        ? `.designer-cell[data-row="${row}"][data-col="${col}"][data-deck="${deck}"]`
        : `.designer-cell[data-row="${row}"][data-col="${col}"]`;
    const cellEl = document.querySelector(selector);
    if (cellEl && cell.type === 'seat') cellEl.textContent = label;
}

// ─── Seat Layout Designer — grid resize ──────────────────────────────────────

function addDesignerRow(deck = null) {
    const isSleeper = currentGridState.lower !== undefined;

    if (isSleeper) {
        const targets = deck ? [deck] : ['lower', 'upper'];
        targets.forEach(d => {
            const colCount = currentGridState[d][0].length;
            currentGridState[d].push(Array.from({ length: colCount }, () => ({ type: 'empty' })));
        });
    } else {
        const colCount = currentGridState[0].length;
        currentGridState.push(Array.from({ length: colCount }, () => ({ type: 'empty' })));
    }

    renderEditorGrid();
}

function removeDesignerRow(deck = null) {
    const isSleeper = currentGridState.lower !== undefined;

    if (isSleeper) {
        if (deck) {
            if (currentGridState[deck].length > 1) currentGridState[deck].pop();
        } else {
            if (currentGridState.lower.length > 2) {
                currentGridState.lower.pop();
                currentGridState.upper.pop();
            }
        }
    } else {
        if (currentGridState.length > 2) currentGridState.pop();
    }

    selectedCellCoords = null;
    document.getElementById('cell-editor-card').style.display = 'none';
    renderEditorGrid();
}

function addDesignerColumn(deck = null) {
    const isSleeper = currentGridState.lower !== undefined;

    if (isSleeper) {
        const targets = deck ? [deck] : ['lower', 'upper'];
        targets.forEach(d => currentGridState[d].forEach(row => row.push({ type: 'empty' })));
    } else {
        currentGridState.forEach(row => row.push({ type: 'empty' }));
    }

    renderEditorGrid();
}

function removeDesignerColumn(deck = null) {
    const isSleeper = currentGridState.lower !== undefined;

    if (isSleeper) {
        if (deck) {
            if (currentGridState[deck][0].length > 2)
                currentGridState[deck].forEach(row => row.pop());
        } else {
            if (currentGridState.lower[0].length > 2) {
                currentGridState.lower.forEach(row => row.pop());
                currentGridState.upper.forEach(row => row.pop());
            }
        }
    } else {
        if (currentGridState[0].length > 2) currentGridState.forEach(row => row.pop());
    }

    selectedCellCoords = null;
    document.getElementById('cell-editor-card').style.display = 'none';
    renderEditorGrid();
}

/** Re-render the Add/Remove Row/Column buttons, with separate controls for sleeper decks. */
function renderGridAdjustmentControls(isSleeper) {
    const container = document.getElementById('grid-adjustments-container');
    if (!container) return;

    if (isSleeper) {
        container.innerHTML = `
            <h4>Grid Adjustments</h4>
            ${['lower', 'upper'].map(deck => `
                <div style="margin-bottom:12px;">
                    <div style="font-size:11px; font-weight:bold; color:var(--primary);
                                text-transform:uppercase; margin-bottom:6px;">
                        ${deck === 'lower' ? 'Lower' : 'Upper'} Deck
                    </div>
                    <div class="grid-actions" style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerRow('${deck}')">➕ Add Row</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerRow('${deck}')">➖ Remove Row</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerColumn('${deck}')">➕ Add Col</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerColumn('${deck}')">➖ Remove Col</button>
                    </div>
                </div>`).join('')}`;
    } else {
        container.innerHTML = `
            <h4>Grid Adjustments</h4>
            <div class="grid-actions" style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerRow()">➕ Add Row</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerRow()">➖ Remove Row</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerColumn()">➕ Add Col</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerColumn()">➖ Remove Col</button>
            </div>`;
    }
}

// ─── Seat Layout Designer — save ─────────────────────────────────────────────

/**
 * Serialise currentGridState to JSON, write it into the hidden form input,
 * update the seat count to match the actual number of labelled seat cells,
 * and close the modal.
 */
function saveLayoutDesign() {
    document.getElementById('seat-layout-grid-input').value = JSON.stringify(currentGridState);

    const isSleeper  = currentGridState.lower !== undefined;
    const countSeats = grid => grid.flat().filter(c => c.type === 'seat' && c.label).length;
    const seatCount  = isSleeper
        ? countSeats(currentGridState.lower) + countSeats(currentGridState.upper)
        : countSeats(currentGridState);

    const seatsInput = document.querySelector('form#bus-form input[name="total_seats"]');
    if (seatsInput) seatsInput.value = seatCount;

    closeLayoutDesigner();
}

// ─── Global exports (required for inline onclick handlers in blade templates) ──
window.adjustDefaultSeats      = adjustDefaultSeats;
window.handleEditBusClick      = handleEditBusClick;
window.openLayoutDesigner      = openLayoutDesigner;
window.closeLayoutDesigner     = closeLayoutDesigner;
window.resetToDefaultLayout    = resetToDefaultLayout;
window.saveLayoutDesign        = saveLayoutDesign;
window.updateSelectedCellType  = updateSelectedCellType;
window.updateSelectedCellLabel = updateSelectedCellLabel;
window.addDesignerRow          = addDesignerRow;
window.removeDesignerRow       = removeDesignerRow;
window.addDesignerColumn       = addDesignerColumn;
window.removeDesignerColumn    = removeDesignerColumn;
