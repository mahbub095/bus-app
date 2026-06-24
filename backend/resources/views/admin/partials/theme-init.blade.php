<script>
    (function () {
        const storageKey = 'sonyabus_admin_theme';
        const saved = localStorage.getItem(storageKey);
        const theme = saved === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
