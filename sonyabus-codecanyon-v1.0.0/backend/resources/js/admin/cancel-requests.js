/**
 * cancel-requests.js
 *
 * Live-polling log for pending cancellation requests.
 * Polls the API every 5 seconds while the "cancel-requests" tab is open,
 * then pauses when the tab is hidden.
 *
 * Data contract (set in cancel-requests.blade.php before this file loads):
 *   window.CancelRequests.logsUrl                  — API endpoint returning { cancel_requests: [...] }
 *   window.CancelRequests.approveCancelRouteTemplate — URL template with '__ID__' placeholder
 *
 * Public API (consumed by layout.js):
 *   window.cancelRequestsLogsModule.startPolling()
 *   window.cancelRequestsLogsModule.stopPolling()
 */

import { escapeHtml, formatDateTime, buildUrl } from './utils.js';

(function () {
    const { logsUrl, approveCancelRouteTemplate } = window.CancelRequests;

    const bodyEl     = document.getElementById('cancel-requests-log-body');
    const liveTextEl = document.getElementById('cancel-requests-live-text');
    const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let pollTimer  = null;
    let isFetching = false;

    /** Update the "live" indicator text next to the panel title. */
    function setLiveText(text) {
        if (!liveTextEl) return;
        liveTextEl.innerHTML = `<span class="live-dot"></span>${text}`;
    }

    // ─── Rendering ────────────────────────────────────────────────────────────

    /** Render all cancellation request rows into the table body. */
    function renderRows(cancelRequests) {
        if (!Array.isArray(cancelRequests) || cancelRequests.length === 0) {
            bodyEl.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted)">
                        No pending cancellation requests.
                    </td>
                </tr>`;
            return;
        }

        bodyEl.innerHTML = cancelRequests.map(b => {
            const routeFrom       = b.schedule?.route?.from        || 'N/A';
            const routeTo         = b.schedule?.route?.to          || 'N/A';
            const busName         = b.schedule?.bus?.operator_name || 'N/A';
            const approveCancelUrl = buildUrl(approveCancelRouteTemplate, b.id);

            return `
                <tr>
                    <td style="font-weight:bold; color:var(--primary);">${escapeHtml(b.pnr)}</td>
                    <td style="font-weight:600;">${escapeHtml(b.passenger_name)}</td>
                    <td>
                        <div>${escapeHtml(b.passenger_phone)}</div>
                        <div style="font-size:11px; color:var(--text-secondary)">${escapeHtml(b.passenger_email)}</div>
                    </td>
                    <td>
                        <div>${escapeHtml(routeFrom)} ➔ ${escapeHtml(routeTo)}</div>
                        <div style="font-size:11px; color:var(--text-secondary)">
                            ${escapeHtml(formatDateTime(b.schedule?.departure_time))}
                        </div>
                    </td>
                    <td>${escapeHtml(busName)}</td>
                    <td style="font-weight:bold;">${escapeHtml(b.seat_numbers)}</td>
                    <td style="color:var(--gold); font-weight:bold;">BDT ${Number(b.total_fare || 0).toLocaleString()}</td>
                    <td style="font-size:12px; color:var(--text-secondary);">${escapeHtml(formatDateTime(b.updated_at))}</td>
                    <td>
                        <form action="${escapeHtml(approveCancelUrl)}" method="POST"
                              onsubmit="return confirm('Approve this cancellation request?');">
                            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                            <button class="btn btn-danger btn-sm" type="submit">Approve Cancel</button>
                        </form>
                    </td>
                </tr>`;
        }).join('');
    }

    // ─── Data fetching ────────────────────────────────────────────────────────

    /**
     * Fetch the latest cancellation requests from the API and re-render the table.
     * @param {boolean} silent — when true, suppresses the "update failed" text on error
     */
    async function fetchLogs(silent = false) {
        if (!bodyEl || isFetching) return;
        isFetching = true;

        try {
            const res = await fetch(logsUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;

            const data = await res.json();
            renderRows(data.cancel_requests || []);

            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            setLiveText(`Live — last updated ${time} (refreshes every 5s)`);
        } catch {
            if (!silent) setLiveText('Live update failed');
        } finally {
            isFetching = false;
        }
    }

    // ─── Polling control ──────────────────────────────────────────────────────

    function startPolling() {
        stopPolling();
        fetchLogs();
        pollTimer = setInterval(() => fetchLogs(true), 5_000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        setLiveText('Live paused');
    }

    window.cancelRequestsLogsModule = { startPolling, stopPolling };
})();
