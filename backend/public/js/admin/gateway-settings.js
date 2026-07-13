document.addEventListener('DOMContentLoaded', function () {
    const smsToggle = document.getElementById('sms_active_toggle');
    const smsLabel = document.getElementById('sms_status_label');

    if (!smsToggle || !smsLabel) return;

    smsToggle.addEventListener('change', function () {
        smsLabel.textContent = this.checked
            ? '🟢 SMS Service is ACTIVE'
            : '🔴 SMS Service is INACTIVE';
    });
});
