/**
 * gateway-settings.js
 *
 * Integrations & Gateways panel: updates the SMS service status label in
 * real time as the admin toggles the active/inactive switch.
 */

document.addEventListener('DOMContentLoaded', () => {
    const toggle      = document.getElementById('sms_active_toggle');
    const statusLabel = document.getElementById('sms_status_label');

    if (!toggle || !statusLabel) return;

    toggle.addEventListener('change', () => {
        statusLabel.textContent = toggle.checked
            ? '🟢 SMS Service is ACTIVE'
            : '🔴 SMS Service is INACTIVE';
    });
});
