/**
 * routes.js
 *
 * Dynamic boarding and dropping points table for the route create/edit form.
 * Rows are added/removed client-side and serialised to JSON hidden inputs
 * before the form submits.
 *
 * Public API (called from routes.blade.php):
 *   window.addBoardingRow(data?)       — append a boarding point row
 *   window.addDroppingRow(data?)       — append a dropping point row
 *   window.loadRoutePointsForm(boarding, dropping) — replace both tables at once
 *   window.serializeRoutePoints()      — pack table values into hidden JSON inputs
 */

(function () {

    // ─── Row HTML builders ────────────────────────────────────────────────────

    /**
     * Build the HTML for a single boarding point table row.
     * @param {{ name?, reporting_time?, departure_time? }} data
     */
    function boardingRowHtml(data = {}) {
        return `
            <tr>
                <td><input type="text" class="coupon-input bp-name"
                           placeholder="e.g. Gabtoli"
                           value="${escapeAttr(data.name || '')}"></td>
                <td><input type="text" class="coupon-input bp-reporting"
                           placeholder="06:30 AM"
                           value="${escapeAttr(data.reporting_time || '')}"></td>
                <td><input type="text" class="coupon-input bp-departure"
                           placeholder="07:00 AM"
                           value="${escapeAttr(data.departure_time || '')}"></td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm"
                            onclick="this.closest('tr').remove()">×</button>
                </td>
            </tr>`;
    }

    /**
     * Build the HTML for a single dropping point table row.
     * @param {{ name?, arrival_time? }} data
     */
    function droppingRowHtml(data = {}) {
        return `
            <tr>
                <td><input type="text" class="coupon-input dp-name"
                           placeholder="e.g. Dampara Bus Terminal"
                           value="${escapeAttr(data.name || '')}"></td>
                <td><input type="text" class="coupon-input dp-arrival"
                           placeholder="02:30 PM"
                           value="${escapeAttr(data.arrival_time || '')}"></td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm"
                            onclick="this.closest('tr').remove()">×</button>
                </td>
            </tr>`;
    }

    /** Escape a string for use in an HTML attribute value. */
    function escapeAttr(str) {
        return String(str ?? '')
            .replace(/&/g,  '&amp;')
            .replace(/"/g,  '&quot;')
            .replace(/</g,  '&lt;');
    }

    // ─── Public API ───────────────────────────────────────────────────────────

    /** Append a new boarding point row to the table body. */
    window.addBoardingRow = function (data = {}) {
        document.getElementById('boarding-points-body')
            .insertAdjacentHTML('beforeend', boardingRowHtml(data));
    };

    /** Append a new dropping point row to the table body. */
    window.addDroppingRow = function (data = {}) {
        document.getElementById('dropping-points-body')
            .insertAdjacentHTML('beforeend', droppingRowHtml(data));
    };

    /**
     * Populate both boarding and dropping tables from arrays of data objects.
     * Guarantees at least one empty row in each table (for new routes).
     * @param {Array} boarding
     * @param {Array} dropping
     */
    window.loadRoutePointsForm = function (boarding = [], dropping = []) {
        const boardingBody = document.getElementById('boarding-points-body');
        const droppingBody = document.getElementById('dropping-points-body');
        if (!boardingBody || !droppingBody) return;

        boardingBody.innerHTML = '';
        droppingBody.innerHTML = '';

        (boarding.length ? boarding : [{}]).forEach(row => window.addBoardingRow(row));
        (dropping.length ? dropping : [{}]).forEach(row => window.addDroppingRow(row));
    };

    /**
     * Read all table row inputs, pack them into JSON, and write the values
     * into the hidden form fields so the server receives structured data.
     * Called via onsubmit on the route form.
     * @returns {boolean} — always true to allow form submission to proceed
     */
    window.serializeRoutePoints = function () {
        const boarding = [];
        document.querySelectorAll('#boarding-points-body tr').forEach(tr => {
            const name = tr.querySelector('.bp-name')?.value.trim();
            if (!name) return; // skip blank rows
            boarding.push({
                name,
                reporting_time: tr.querySelector('.bp-reporting')?.value.trim() || '',
                departure_time: tr.querySelector('.bp-departure')?.value.trim() || '',
            });
        });

        const dropping = [];
        document.querySelectorAll('#dropping-points-body tr').forEach(tr => {
            const name = tr.querySelector('.dp-name')?.value.trim();
            if (!name) return; // skip blank rows
            dropping.push({
                name,
                arrival_time: tr.querySelector('.dp-arrival')?.value.trim() || '',
            });
        });

        document.getElementById('boarding_points_json').value = JSON.stringify(boarding);
        document.getElementById('dropping_points_json').value = JSON.stringify(dropping);
        return true;
    };

    // Initialise with one empty row in each table on first load
    window.loadRoutePointsForm([], []);
})();
