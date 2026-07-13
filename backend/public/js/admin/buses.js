// ─── Edit bus handler ────────────────────────────────────────────────────────

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

// ─── Seat Layout Designer ─────────────────────────────────────────────────────

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
            console.error('Failed to parse grid data', e);
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
    const rowLetters = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];

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
        let lowerRows = Math.max(0, Math.ceil(remainingSeats / 3));
        let rIndex = 0;
        for (let r = 0; r < lowerRows; r++) {
            const rowLetter = rowLetters[rIndex++];
            lowerGrid.push([
                { type: 'seat', label: 'L-' + rowLetter + '1' },
                { type: 'aisle' },
                { type: 'seat', label: 'L-' + rowLetter + '2' },
                { type: 'seat', label: 'L-' + rowLetter + '3' },
            ]);
        }
        const lastRowLetter = rowLetters[rIndex++];
        lowerGrid.push([1, 2, 3, 4].map(num => ({ type: 'seat', label: 'L-' + lastRowLetter + num })));

        const upperGrid = [];
        upperGrid.push([{ type: 'empty' }, { type: 'aisle' }, { type: 'empty' }, { type: 'empty' }]);
        let remainingSeatsU = upperCount - 4;
        let upperRows = Math.max(0, Math.ceil(remainingSeatsU / 3));
        rIndex = 0;
        for (let r = 0; r < upperRows; r++) {
            const rowLetter = rowLetters[rIndex++];
            upperGrid.push([
                { type: 'seat', label: 'U-' + rowLetter + '1' },
                { type: 'aisle' },
                { type: 'seat', label: 'U-' + rowLetter + '2' },
                { type: 'seat', label: 'U-' + rowLetter + '3' },
            ]);
        }
        const lastRowLetterU = rowLetters[rIndex++];
        upperGrid.push([1, 2, 3, 4].map(num => ({ type: 'seat', label: 'U-' + lastRowLetterU + num })));

        return { lower: lowerGrid, upper: upperGrid };
    }

    // Default 2+2
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
        grid.push([
            { type: 'seat', label: rowLetter + '1' },
            { type: 'seat', label: rowLetter + '2' },
            { type: 'aisle' },
            { type: 'seat', label: rowLetter + '3' },
            { type: 'seat', label: rowLetter + '4' },
        ]);
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
    gridDiv.style.cssText = 'display:flex;flex-direction:column;gap:8px;';

    grid.forEach((row, rIdx) => {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'seat-row';
        rowDiv.style.cssText = 'display:flex;gap:8px;justify-content:space-between;';

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
                    </svg>`;
            } else if (cell.type === 'engine') {
                cellDiv.classList.add('cell-engine');
                cellDiv.textContent = 'Engine';
            } else if (cell.type === 'aisle') {
                cellDiv.classList.add('cell-aisle');
            } else {
                cellDiv.classList.add('cell-empty');
            }

            cellDiv.draggable = true;
            cellDiv.dataset.row = rIdx;
            cellDiv.dataset.col = cIdx;
            if (deckKey) cellDiv.dataset.deck = deckKey;

            if (
                selectedCellCoords &&
                selectedCellCoords.row === rIdx &&
                selectedCellCoords.col === cIdx &&
                selectedCellCoords.deck === deckKey
            ) {
                cellDiv.classList.add('active-selection');
            }

            cellDiv.addEventListener('dragstart', handleDragStart);
            cellDiv.addEventListener('dragover', handleDragOver);
            cellDiv.addEventListener('dragleave', handleDragLeave);
            cellDiv.addEventListener('drop', handleDrop);
            cellDiv.addEventListener('click', () => handleCellClick(rIdx, cIdx, deckKey));

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
    e.preventDefault();
    this.classList.add('drag-over');
    return false;
}

function handleDragLeave() {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    e.stopPropagation();
    this.classList.remove('drag-over');

    const src = JSON.parse(e.dataTransfer.getData('text/plain'));
    const destRow = parseInt(this.dataset.row, 10);
    const destCol = parseInt(this.dataset.col, 10);
    const destDeck = this.dataset.deck || null;

    swapCells(src.row, src.col, src.deck, destRow, destCol, destDeck);

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
    document.querySelector(selector)?.classList.add('active-selection');

    const cell = deck ? currentGridState[deck][row][col] : currentGridState[row][col];

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
            currentGridState[deck].push(Array.from({ length: colCount }, () => ({ type: 'empty' })));
        } else {
            currentGridState.lower.push(Array.from({ length: currentGridState.lower[0].length }, () => ({ type: 'empty' })));
            currentGridState.upper.push(Array.from({ length: currentGridState.upper[0].length }, () => ({ type: 'empty' })));
        }
    } else {
        currentGridState.push(Array.from({ length: currentGridState[0].length }, () => ({ type: 'empty' })));
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
            if (currentGridState[deck][0].length > 2) currentGridState[deck].forEach(row => row.pop());
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
            </div>`;
    } else {
        container.innerHTML = `
            <h4>Grid Adjustments</h4>
            <div class="grid-actions" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerRow()">➕ Add Row</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerRow()">➖ Remove Row</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addDesignerColumn()">➕ Add Col</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="removeDesignerColumn()">➖ Remove Col</button>
            </div>`;
    }
}

function saveLayoutDesign() {
    const gridInput = document.getElementById('seat-layout-grid-input');
    gridInput.value = JSON.stringify(currentGridState);

    const isSleeper = currentGridState.lower !== undefined;
    const countSeats = (grid) => grid.reduce((sum, row) =>
        sum + row.filter(cell => cell.type === 'seat' && cell.label).length, 0);

    const seatCount = isSleeper
        ? countSeats(currentGridState.lower) + countSeats(currentGridState.upper)
        : countSeats(currentGridState);

    const seatsInput = document.querySelector('form#bus-form input[name="total_seats"]');
    if (seatsInput) seatsInput.value = seatCount;

    closeLayoutDesigner();
}
