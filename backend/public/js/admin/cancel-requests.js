(function () {
    const logsUrl = window.CancelRequests.logsUrl;
    const approveCancelRouteTemplate = window.CancelRequests.approveCancelRouteTemplate;
    const bodyEl = document.getElementById('cancel-requests-log-body');
    const liveTextEl = document.getElementById('cancel-requests-live-text');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let timer = null;
    let isFetching = false;

    function formatDateTime(iso) {
        if (!iso) return 'N/A';
        return new Date(iso).toLocaleString([], {
            weekday: 'short',
            month: 'short',
            day: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function getRoute(template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function renderRows(cancelRequests) {
        if (!Array.isArray(cancelRequests) || cancelRequests.length === 0) {
            bodyEl.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted)">
                        No pending cancellation requests.
                    </td>
                </tr>`;
            return;
        }

        bodyEl.innerHTML = cancelRequests.map((b) => {
            const routeFrom = b.schedule?.route?.from || 'N/A';
            const routeTo = b.schedule?.route?.to || 'N/A';
            const busName = b.schedule?.bus?.operator_name || 'N/A';
            const approveCancelUrl = getRoute(approveCancelRouteTemplate, b.id);

            return `
                <tr>
                    <td style="font-weight: bold; color: var(--primary);">${escapeHtml(b.pnr)}</td>
                    <td style="font-weight: 600;">${escapeHtml(b.passenger_name)}</td>
                    <td>
                        <div>${escapeHtml(b.passenger_phone)}</div>
                        <div style="font-size: 11px; color: var(--text-secondary)">${escapeHtml(b.passenger_email)}</div>
                    </td>
                    <td>
                        <div>${escapeHtml(routeFrom)} ➔ ${escapeHtml(routeTo)}</div>
                        <div style="font-size: 11px; color: var(--text-secondary)">
                            ${escapeHtml(formatDateTime(b.schedule?.departure_time))}
                        </div>
                    </td>
                    <td>${escapeHtml(busName)}</td>
                    <td style="font-weight: bold;">${escapeHtml(b.seat_numbers)}</td>
                    <td style="color: var(--gold); font-weight: bold;">BDT ${Number(b.total_fare || 0).toLocaleString()}</td>
                    <td style="font-size: 12px; color: var(--text-secondary);">
                        ${escapeHtml(formatDateTime(b.updated_at))}
                    </td>
                    <td>
                        <form action="${escapeHtml(approveCancelUrl)}" method="POST" onsubmit="return confirm('Approve this cancellation request?');">
                            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                            <button class="btn btn-danger btn-sm" type="submit">Approve Cancel</button>
                        </form>
                    </td>
                </tr>`;
        }).join('');
    }

    function setLiveText(text) {
        if (!liveTextEl) return;
        liveTextEl.innerHTML = `<span class="live-dot"></span>${text}`;
    }

    async function fetchLogs(silent = false) {
        if (!bodyEl || isFetching) return;
        isFetching = true;
        try {
            const res = await fetch(logsUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            renderRows(data.cancel_requests || []);
            const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            setLiveText(`Live — last updated ${now} (refreshes every 5s)`);
        } catch (err) {
            if (!silent) setLiveText('Live update failed');
        } finally {
            isFetching = false;
        }
    }

    function startPolling() {
        stopPolling();
        fetchLogs();
        timer = setInterval(() => fetchLogs(true), 5000);
    }

    function stopPolling() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        setLiveText('Live paused');
    }

    window.cancelRequestsLogsModule = { startPolling, stopPolling };
})();
