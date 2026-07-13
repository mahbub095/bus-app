/**
 * layout.js
 *
 * Core admin shell: tab switching, flash alert dismissal, CRUD form helpers,
 * and the dark/light theme toggle.
 *
 * Data contract (set in layout.blade.php before this file loads):
 *   window.AdminLayout.serverTab  — tab name returned after a POST, e.g. 'bookings'
 *
 * Public globals (used by other admin JS files):
 *   setCrudFormMode(formId, config)
 *   resetCrudForm(formId, createAction, createTitle, createSubmitLabel)
 */

// ─── Flash alerts ─────────────────────────────────────────────────────────────

const FLASH_ALERT_DURATION_MS = 10_000;

function initFlashAlerts() {
    document.querySelectorAll('.flash-alert').forEach(el => {
        setTimeout(() => {
            el.classList.add('flash-dismissed');
            // Remove from DOM after the CSS transition finishes (max 500 ms)
            const remove = () => el.remove();
            el.addEventListener('transitionend', remove, { once: true });
            setTimeout(remove, 500);
        }, FLASH_ALERT_DURATION_MS);
    });
}

// ─── Tab switching ────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initFlashAlerts();

    const navItems          = document.querySelectorAll('.sidebar-nav-item');
    const tabContents       = document.querySelectorAll('.admin-tab-content');
    const dashboardOverview = document.getElementById('dashboard-overview');
    const adminHeader       = document.getElementById('admin-header');

    /**
     * Determine which tab should be active on page load by inspecting:
     *   1. URL hash  (#bookings, #buses, …)
     *   2. Query-string pagination params  (?bookings_page=2)
     *   3. Falls back to 'dashboard'
     */
    function resolveActiveTab() {
        const path    = window.location.pathname.replace(/\/+$/, '');
        const hashTab = window.location.hash.replace(/^#/, '').trim();

        if (hashTab === 'dashboard') return 'dashboard';
        if (hashTab && document.getElementById(`tab-content-${hashTab}`)) return hashTab;

        const query = new URLSearchParams(window.location.search);
        const knownTabs = [
            'stations', 'buses', 'routes', 'schedules', 'promotions', 'users',
            'coach-services', 'bookings', 'cancel-requests', 'reports',
            'site-settings', 'gateways', 'profile',
        ];
        for (const tab of knownTabs) {
            if (query.has(`${tab}_page`)) return tab;
        }

        if (path === '/admin' && !hashTab) return 'dashboard';
        return 'dashboard';
    }

    /** Return the currently-active tab name, reading the sidebar's active item. */
    function getCurrentTab() {
        const activeNav = document.querySelector('.sidebar-nav-item.active');
        const navTab    = activeNav?.getAttribute('data-tab');
        if (navTab === 'dashboard') return 'dashboard';
        if (navTab && document.getElementById(`tab-content-${navTab}`)) return navTab;
        return resolveActiveTab();
    }

    /**
     * Fix any server-rendered pagination links so they include the tab hash,
     * keeping the user on the correct tab after a page change.
     */
    function syncPaginationLinks() {
        tabContents.forEach(section => {
            const tabName = section.id.replace('tab-content-', '');
            section.querySelectorAll('.custom-pagination a.page-link').forEach(link => {
                const href = link.getAttribute('href');
                if (href) {
                    const [base] = href.split('#');
                    link.setAttribute('href', `${base}#${tabName}`);
                }
            });
        });
    }

    /**
     * Show the requested tab panel and notify live-polling modules.
     * @param {string}  tabName
     * @param {boolean} updateHash — whether to push the tab name to the URL hash
     */
    function switchTab(tabName, updateHash = false) {
        // Remove a server-injected preload style tag if present
        document.getElementById('tab-preload-style')?.remove();

        // Update sidebar active state
        navItems.forEach(item => {
            item.classList.toggle('active', item.getAttribute('data-tab') === tabName);
        });

        // Hide all tab panels, then show the target one
        tabContents.forEach(c => { c.style.display = 'none'; });

        if (tabName !== 'dashboard') {
            const panel = document.getElementById(`tab-content-${tabName}`);
            if (panel) panel.style.display = 'grid';
        }

        // The dashboard overview lives outside the tab-content sections
        const isDashboard = tabName === 'dashboard';
        dashboardOverview?.style.setProperty('display', isDashboard ? 'block' : 'none');
        adminHeader?.style.setProperty('display',       isDashboard ? 'block' : 'none');

        if (updateHash) window.location.hash = tabName;

        // Start or stop live-polling modules depending on which tab is active
        window.coachServicesModule?.[ tabName === 'coach-services' ? 'startPolling' : 'stopPolling']();
        window.bookingsLogsModule?.[ tabName === 'bookings'        ? 'startPolling' : 'stopPolling']();
        window.cancelRequestsLogsModule?.[ tabName === 'cancel-requests' ? 'startPolling' : 'stopPolling']();
    }

    // Wire sidebar nav clicks
    navItems.forEach(item => {
        item.addEventListener('click', event => {
            const tabName = item.getAttribute('data-tab');
            if (!tabName) return;

            // If there are active pagination query params, strip them before navigating
            const query = new URLSearchParams(window.location.search);
            let hadPageParams = false;
            for (const key of [...query.keys()]) {
                if (key === 'page' || key.endsWith('_page')) {
                    query.delete(key);
                    hadPageParams = true;
                }
            }

            if (hadPageParams) {
                event.preventDefault();
                const qs  = query.toString();
                window.location.href = window.location.pathname + (qs ? `?${qs}` : '') + `#${tabName}`;
                return;
            }

            event.preventDefault();
            switchTab(tabName, true);
        });
    });

    // Determine the initial tab (server redirect takes priority over URL)
    let activeTab       = resolveActiveTab();
    const serverTab     = window.AdminLayout?.serverTab;
    if (serverTab === 'dashboard' || (serverTab && document.getElementById(`tab-content-${serverTab}`))) {
        activeTab = serverTab;
    }

    syncPaginationLinks();
    switchTab(activeTab);

    // Before any POST form submits, inject a hidden admin_tab field so the
    // server knows which tab to redirect back to after processing.
    document.querySelector('.admin-main')?.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;

        let input = form.querySelector('input[name="admin_tab"]');
        if (!input) {
            input = Object.assign(document.createElement('input'), { type: 'hidden', name: 'admin_tab' });
            form.appendChild(input);
        }
        input.value = getCurrentTab();
    });
});

// ─── CRUD form helpers ────────────────────────────────────────────────────────

/**
 * Switch a form between 'create' and 'edit' modes.
 *
 * config for 'edit' mode:
 *   { mode, action, title, submitLabel, id, fields: { [name]: value }, … }
 *
 * config for 'create' mode:
 *   { mode, createAction, createTitle, createSubmitLabel }
 *
 * Special handling is applied automatically for 'booking-form' (schedule
 * selector visibility) and 'route-form' (boarding/dropping points table).
 */
function setCrudFormMode(formId, config) {
    const form = document.getElementById(formId);
    if (!form) return;

    const titleEl     = document.getElementById(`${formId}-title`);
    const submitBtn   = document.getElementById(`${formId}-submit`);
    const cancelBtn   = document.getElementById(`${formId}-cancel`);
    const idInput     = form.querySelector('[name="_edit_id"]');
    const methodInput = form.querySelector('[name="_method"]');

    if (config.mode === 'edit') {
        form.action = config.action;
        if (methodInput) methodInput.value  = 'PUT';
        if (titleEl)     titleEl.textContent  = config.title;
        if (submitBtn)   submitBtn.textContent = config.submitLabel;
        if (cancelBtn)   cancelBtn.classList.add('visible');
        if (idInput)     idInput.value = config.id;

        Object.entries(config.fields).forEach(([name, value]) => {
            const field = form.querySelector(`[name="${name}"]`);
            if (field) field.value = value ?? '';
        });
    } else {
        form.reset();
        form.action = config.createAction;
        if (methodInput) methodInput.value  = 'POST';
        if (titleEl)     titleEl.textContent  = config.createTitle;
        if (submitBtn)   submitBtn.textContent = config.createSubmitLabel;
        if (cancelBtn)   cancelBtn.classList.remove('visible');
        if (idInput)     idInput.value = '';
    }

    // Booking form: hide the schedule selector when editing (schedule is immutable)
    if (formId === 'booking-form') {
        const scheduleGroup  = document.getElementById('booking-schedule-group');
        const scheduleSelect = scheduleGroup?.querySelector('select[name="schedule_id"]');
        const isEdit         = config.mode === 'edit';

        if (scheduleGroup)  scheduleGroup.style.display = isEdit ? 'none' : 'flex';
        if (scheduleSelect) {
            scheduleSelect.required = !isEdit;
            scheduleSelect.disabled = isEdit;
        }
    }

    // Route form: repopulate the boarding/dropping points table
    if (formId === 'route-form' && typeof window.loadRoutePointsForm === 'function') {
        window.loadRoutePointsForm(
            config.mode === 'edit' ? (config.boarding_points || []) : [],
            config.mode === 'edit' ? (config.dropping_points || []) : [],
        );
    }
}

/** Convenience wrapper — resets a form back to create mode. */
function resetCrudForm(formId, createAction, createTitle, createSubmitLabel) {
    setCrudFormMode(formId, { mode: 'create', createAction, createTitle, createSubmitLabel });
}

// ─── Theme toggle ─────────────────────────────────────────────────────────────

(function initAdminThemeToggle() {
    const STORAGE_KEY = 'sonyabus_admin_theme';
    const toggleBtn   = document.getElementById('admin-theme-toggle');

    toggleBtn?.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next    = current === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem(STORAGE_KEY, next);
        window.dispatchEvent(new CustomEvent('admin-theme-change', { detail: { theme: next } }));
    });
})();
