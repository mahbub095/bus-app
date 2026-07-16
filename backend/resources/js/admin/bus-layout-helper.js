/**
 * bus-layout-helper.js
 *
 * Logic for default bus layouts and common SVGs.
 */

export const DRIVER_SVG = `
    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"
         xmlns="http://www.w3.org/2000/svg" style="display:block;">
        <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2.5"/>
        <circle cx="12" cy="12" r="3.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
        <circle cx="12" cy="12" r="1"   fill="currentColor" stroke="none"/>
        <line x1="12" y1="8.5" x2="12" y2="2.5"
              stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <line x1="9"  y1="14"  x2="4"  y2="18.5"
              stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        <line x1="15" y1="14"  x2="20" y2="18.5"
              stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>`;

/**
 * Generate a default grid for a given layout type and seat count.
 * Returns either a 2-D array (standard) or { lower, upper } (sleeper).
 */
export function generateDefaultGrid(layout, totalSeats) {
    const ROW_LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

    if (layout === '2+2_last5') {
        const grid = [[
            { type: 'engine', label: 'Engine' },
            { type: 'empty' },
            { type: 'aisle' },
            { type: 'empty' },
            { type: 'driver', label: 'Driver' },
        ]];

        // Regular 4-abreast rows fill seats minus the 5-seat back row
        const normalRowCount = Math.ceil((totalSeats - 5) / 4);
        for (let r = 0; r < normalRowCount; r++) {
            const L = ROW_LETTERS[r];
            grid.push([
                { type: 'seat', label: `${L}1` },
                { type: 'seat', label: `${L}2` },
                { type: 'aisle' },
                { type: 'seat', label: `${L}3` },
                { type: 'seat', label: `${L}4` },
            ]);
        }

        // 5-seat back row
        const lastLetter = ROW_LETTERS[normalRowCount];
        grid.push([1, 2, 3, 4, 5].map(n => ({ type: 'seat', label: `${lastLetter}${n}` })));
        return grid;
    }

    if (layout === '1+2') {
        const grid = [[
            { type: 'engine', label: 'Engine' },
            { type: 'aisle' },
            { type: 'empty' },
            { type: 'driver', label: 'Driver' },
        ]];

        const rowCount = Math.ceil(totalSeats / 3);
        for (let r = 0; r < rowCount; r++) {
            const L = ROW_LETTERS[r];
            grid.push([
                { type: 'seat', label: `${L}1` },
                { type: 'aisle' },
                { type: 'seat', label: `${L}2` },
                { type: 'seat', label: `${L}3` },
            ]);
        }
        return grid;
    }

    if (layout === 'sleeper') {
        const lowerCount = Math.ceil(totalSeats / 2);
        const upperCount = totalSeats - lowerCount;

        function buildSleeperDeck(count, prefix, hasDriver) {
            const deck = [hasDriver
                ? [{ type: 'engine', label: 'Engine' }, { type: 'aisle' }, { type: 'empty' }, { type: 'driver', label: 'Driver' }]
                : [{ type: 'empty' }, { type: 'aisle' }, { type: 'empty' }, { type: 'empty' }],
            ];

            const normalRows = Math.max(0, Math.ceil((count - 4) / 3));
            for (let r = 0; r < normalRows; r++) {
                const L = ROW_LETTERS[r];
                deck.push([
                    { type: 'seat', label: `${prefix}${L}1` },
                    { type: 'aisle' },
                    { type: 'seat', label: `${prefix}${L}2` },
                    { type: 'seat', label: `${prefix}${L}3` },
                ]);
            }

            const lastLetter = ROW_LETTERS[normalRows];
            deck.push([1, 2, 3, 4].map(n => ({ type: 'seat', label: `${prefix}${lastLetter}${n}` })));
            return deck;
        }

        return {
            lower: buildSleeperDeck(lowerCount, 'L-', true),
            upper: buildSleeperDeck(upperCount, 'U-', false),
        };
    }

    // Default: standard 2+2
    const grid = [[
        { type: 'engine', label: 'Engine' },
        { type: 'empty' },
        { type: 'aisle' },
        { type: 'empty' },
        { type: 'driver', label: 'Driver' },
    ]];

    const rowCount = Math.ceil(totalSeats / 4);
    for (let r = 0; r < rowCount; r++) {
        const L = ROW_LETTERS[r];
        grid.push([
            { type: 'seat', label: `${L}1` },
            { type: 'seat', label: `${L}2` },
            { type: 'aisle' },
            { type: 'seat', label: `${L}3` },
            { type: 'seat', label: `${L}4` },
        ]);
    }
    return grid;
}
