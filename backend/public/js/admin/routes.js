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

        (boarding.length ? boarding : [{}]).forEach(row => window.addBoardingRow(row));
        (dropping.length ? dropping : [{}]).forEach(row => window.addDroppingRow(row));
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

    window.loadRoutePointsForm([], []);
})();
