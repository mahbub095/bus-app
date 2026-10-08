/**
 * site-settings.js
 *
 * Site Settings panel: updates the maintenance mode status label in real time
 * as the admin toggles the checkbox, before the form is saved.
 */

document.addEventListener('DOMContentLoaded', () => {
    const toggle      = document.getElementById('maintenance_toggle');
    const statusLabel = document.getElementById('maintenance_status');

    if (!toggle || !statusLabel) return;

    toggle.addEventListener('change', () => {
        statusLabel.textContent = toggle.checked
            ? '🔴 Maintenance Mode is ACTIVE'
            : '🟢 Website is LIVE';
    });
});
