<script>
    (function () {
        const storageKey = 'sonyabus_admin_theme';
        const saved = localStorage.getItem(storageKey);
        const theme = saved === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', theme);

        try {
            const knownTabs = ['stations', 'buses', 'routes', 'schedules', 'promotions', 'users', 'coach-services', 'bookings', 'cancel-requests', 'reports', 'site-settings', 'gateways', 'profile'];
            const hash = window.location.hash.replace(/^#/, '').trim();
            const search = window.location.search;
            let activeTab = null;

            if (hash && knownTabs.includes(hash)) {
                activeTab = hash;
            } else if (search) {
                for (const tab of knownTabs) {
                    if (search.includes(tab + '_page=')) {
                        activeTab = tab;
                        break;
                    }
                }
            }

            if (activeTab && activeTab !== 'dashboard') {
                document.write('<style id="tab-preload-style">#dashboard-overview, #admin-header { display: none !important; } #tab-content-' + activeTab + ' { display: grid !important; }</style>');
            }
        } catch (e) {}
    })();
</script>
