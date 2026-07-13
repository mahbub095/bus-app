document.addEventListener('DOMContentLoaded', function () {
    const maintenanceToggle = document.getElementById('maintenance_toggle');
    const maintenanceStatus = document.getElementById('maintenance_status');

    if (maintenanceToggle && maintenanceStatus) {
        maintenanceToggle.addEventListener('change', function () {
            maintenanceStatus.textContent = this.checked
                ? '🔴 Maintenance Mode is ACTIVE'
                : '🟢 Website is LIVE';
        });
    }
});
