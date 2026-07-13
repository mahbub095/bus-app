// Admin layout: tab switching, CRUD form helpers, theme toggle
// Requires window.AdminLayout.serverTab to be set before this script loads.

const FLASH_ALERT_MS = 10000;

function initFlashAlerts() {
    document.querySelectorAll('.flash-alert').forEach(el => {
        setTimeout(() => {
            el.classList.add('flash-dismissed');
            const removeEl = () => el.remove();
            el.addEventListener('transitionend', removeEl, { once: true });
            setTimeout(removeEl, 500);
        }, FLASH_ALERT_MS);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initFlashAlerts();

    const navItems         = document.querySelectorAll('.sidebar-nav-item');
    const contents         = document.querySelectorAll('.admin-tab-content');
    const dashboardOverview = document.getElementById('dashboard-overview');
    const adminHeader       = document.getElementById('admin-header');

    function resolveAdminTab() {
        const path    = window.location.pathname.replace(/\/+$/, '');
        const hashTab = window.location.hash.replace(/^#/, '').trim();

        if (hashTab === 'dashboard') return 'dashboard';
        if (hashTab && document.getElementById(`tab-content-${hashTab}`)) return hashTab;

        const searchParams = new URLSearchParams(window.location.search);
        const knownTabs = ['stations','buses','routes','schedules','promotions','users','coach-services','bookings','cancel-requests','reports','site-settings','gateways','profile'];
        for (const tab of knownTabs) {
            if (searchParams.has(`${tab}_page`)) return tab;
        }

        if (path === '/admin' && !hashTab) return 'dashboard';
        return 'dashboard';
    }

    function syncPaginationLinks() {
        contents.forEach(section => {
            const tabName = section.id.replace('tab-content-', '');
            section.querySelectorAll('.custom-pagination a.page-link').forEach(link => {
                let href = link.getAttribute('href');
                if (href) {
                    const [baseUrlAndQuery] = href.split('#');
                    link.setAttribute('href', baseUrlAndQuery + '#' + tabName);
                }
            });
        });
    }

    function getCurrentAdminTab() {
        const navActive = document.querySelector('.sidebar-nav-item.active');
        const navTab    = navActive?.getAttribute('data-tab');
        if (navTab === 'dashboard') return 'dashboard';
        if (navTab && document.getElementById(`tab-content-${navTab}`)) return navTab;
        return resolveAdminTab();
    }

    let activeTab = resolveAdminTab();
    const serverTab = window.AdminLayout?.serverTab;
    if (serverTab === 'dashboard' || (serverTab && document.getElementById(`tab-content-${serverTab}`))) {
        activeTab = serverTab;
    }

    const switchTab = (tabName, updateHash = false) => {
        const preloadStyle = document.getElementById('tab-preload-style');
        if (preloadStyle) preloadStyle.remove();

        navItems.forEach(item => {
            item.classList.toggle('active', item.getAttribute('data-tab') === tabName);
        });

        contents.forEach(c => { c.style.display = 'none'; });

        if (tabName !== 'dashboard') {
            const panel = document.getElementById(`tab-content-${tabName}`);
            if (panel) panel.style.display = 'grid';
        }

        if (tabName === 'dashboard') {
            dashboardOverview?.style.setProperty('display', 'block');
            adminHeader?.style.setProperty('display', 'block');
        } else {
            dashboardOverview?.style.setProperty('display', 'none');
            adminHeader?.style.setProperty('display', 'none');
        }

        if (updateHash) window.location.hash = tabName;

        if (window.coachServicesModule) {
            tabName === 'coach-services'
                ? window.coachServicesModule.startPolling()
                : window.coachServicesModule.stopPolling();
        }
        if (window.bookingsLogsModule) {
            tabName === 'bookings'
                ? window.bookingsLogsModule.startPolling()
                : window.bookingsLogsModule.stopPolling();
        }
        if (window.cancelRequestsLogsModule) {
            tabName === 'cancel-requests'
                ? window.cancelRequestsLogsModule.startPolling()
                : window.cancelRequestsLogsModule.stopPolling();
        }
    };

    navItems.forEach(item => {
        item.addEventListener('click', event => {
            const tabName = item.getAttribute('data-tab');
            if (!tabName) return;

            const searchParams = new URLSearchParams(window.location.search);
            let hasPageParams  = false;
            for (const key of Array.from(searchParams.keys())) {
                if (key === 'page' || key.endsWith('_page')) {
                    searchParams.delete(key);
                    hasPageParams = true;
                }
            }

            if (hasPageParams) {
                event.preventDefault();
                const newSearch = searchParams.toString();
                window.location.href = window.location.pathname + (newSearch ? '?' + newSearch : '') + '#' + tabName;
                return;
            }

            event.preventDefault();
            switchTab(tabName, true);
        });
    });

    syncPaginationLinks();
    switchTab(activeTab);

    document.querySelector('.admin-main')?.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;

        const tab = getCurrentAdminTab();
        let input = form.querySelector('input[name="admin_tab"]');
        if (!input) {
            input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'admin_tab';
            form.appendChild(input);
        }
        input.value = tab;
    });
});

// ─── CRUD form helpers (global) ───────────────────────────────────────────────

function setCrudFormMode(formId, config) {
    const form      = document.getElementById(formId);
    if (!form) return;

    const titleEl    = document.getElementById(formId + '-title');
    const submitBtn  = document.getElementById(formId + '-submit');
    const cancelBtn  = document.getElementById(formId + '-cancel');
    const idInput    = form.querySelector('[name="_edit_id"]');
    const methodInput = form.querySelector('[name="_method"]');

    if (config.mode === 'edit') {
        form.action = config.action;
        if (methodInput) methodInput.value = 'PUT';
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
        if (methodInput) methodInput.value = 'POST';
        if (titleEl)     titleEl.textContent  = config.createTitle;
        if (submitBtn)   submitBtn.textContent = config.createSubmitLabel;
        if (cancelBtn)   cancelBtn.classList.remove('visible');
        if (idInput)     idInput.value = '';
    }

    if (formId === 'booking-form') {
        const scheduleGroup  = document.getElementById('booking-schedule-group');
        const scheduleSelect = scheduleGroup?.querySelector('select[name="schedule_id"]');
        if (scheduleGroup) scheduleGroup.style.display = config.mode === 'edit' ? 'none' : 'flex';
        if (scheduleSelect) {
            scheduleSelect.required = config.mode !== 'edit';
            scheduleSelect.disabled = config.mode === 'edit';
        }
    }

    if (formId === 'route-form' && typeof window.loadRoutePointsForm === 'function') {
        window.loadRoutePointsForm(
            config.mode === 'edit' ? (config.boarding_points || []) : [],
            config.mode === 'edit' ? (config.dropping_points || []) : []
        );
    }
}

function resetCrudForm(formId, createAction, createTitle, createSubmitLabel) {
    setCrudFormMode(formId, {
        mode: 'create',
        createAction,
        createTitle,
        createSubmitLabel
    });
}

// ─── Theme toggle (global) ────────────────────────────────────────────────────

(function initAdminThemeToggle() {
    const storageKey = 'sonyabus_admin_theme';
    const toggleBtn  = document.getElementById('admin-theme-toggle');

    toggleBtn?.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next    = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem(storageKey, next);
        window.dispatchEvent(new CustomEvent('admin-theme-change', { detail: { theme: next } }));
    });
})();
