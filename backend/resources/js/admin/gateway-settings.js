/**
 * gateway-settings.js
 *
 * Handles all Integrations & Gateways forms via AJAX so that
 * .env writes (which restart php artisan serve) never cause a
 * "site can't be reached" error in the browser.
 *
 * Features:
 *  - AJAX submit for SMS / Mail / ZiniPay save forms
 *  - AJAX submit for Test SMS / Test Email buttons
 *  - Loading overlay with spinner on every submit
 *  - After .env write: polls until server comes back, then
 *    shows success toast and re-enables the form
 *  - SMS active/inactive label toggle
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── SMS active label ──────────────────────────────────────────────────────
    const toggle      = document.getElementById('sms_active_toggle');
    const statusLabel = document.getElementById('sms_status_label');
    if (toggle && statusLabel) {
        toggle.addEventListener('change', () => {
            statusLabel.textContent = toggle.checked
                ? '🟢 SMS Service is ACTIVE'
                : '🔴 SMS Service is INACTIVE';
        });
    }

    // ── Toast notification ────────────────────────────────────────────────────
    function showToast(message, type = 'success') {
        const existing = document.getElementById('gw-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'gw-toast';
        const bg = type === 'success'
            ? 'rgba(16,185,129,0.95)'
            : type === 'info'
                ? 'rgba(99,102,241,0.95)'
                : 'rgba(239,68,68,0.95)';

        Object.assign(toast.style, {
            position: 'fixed', bottom: '28px', right: '28px', zIndex: '9999',
            background: bg, color: '#fff', padding: '14px 22px',
            borderRadius: '10px', fontSize: '14px', fontWeight: '600',
            boxShadow: '0 8px 24px rgba(0,0,0,0.25)',
            maxWidth: '380px', lineHeight: '1.5',
            transition: 'opacity 0.4s ease', opacity: '1',
        });
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 450);
        }, 5000);
    }

    // ── Loading overlay ───────────────────────────────────────────────────────
    function showLoader(message = 'Saving settings…') {
        const existing = document.getElementById('gw-loader');
        if (existing) { existing.querySelector('#gw-loader-msg').textContent = message; return; }

        const overlay = document.createElement('div');
        overlay.id = 'gw-loader';
        Object.assign(overlay.style, {
            position: 'fixed', inset: '0', zIndex: '9998',
            background: 'rgba(0,0,0,0.45)', backdropFilter: 'blur(3px)',
            display: 'flex', flexDirection: 'column',
            alignItems: 'center', justifyContent: 'center', gap: '18px',
        });
        overlay.innerHTML = `
            <div style="
                width:52px;height:52px;border:4px solid rgba(255,255,255,0.25);
                border-top-color:#fff;border-radius:50%;
                animation:gw-spin 0.75s linear infinite;">
            </div>
            <div id="gw-loader-msg" style="color:#fff;font-size:15px;font-weight:600;letter-spacing:0.3px;">
                ${message}
            </div>`;
        document.body.appendChild(overlay);

        if (!document.getElementById('gw-spin-style')) {
            const style = document.createElement('style');
            style.id = 'gw-spin-style';
            style.textContent = '@keyframes gw-spin{to{transform:rotate(360deg)}}';
            document.head.appendChild(style);
        }
    }

    function updateLoader(message) {
        const el = document.getElementById('gw-loader-msg');
        if (el) el.textContent = message;
    }

    function hideLoader() {
        document.getElementById('gw-loader')?.remove();
    }

    // ── Wait for server to come back (after .env write restarts serve) ────────
    async function waitForServer(maxWaitMs = 15000, intervalMs = 800) {
        const start = Date.now();
        while (Date.now() - start < maxWaitMs) {
            await new Promise(r => setTimeout(r, intervalMs));
            try {
                const res = await fetch(window.location.href, {
                    method: 'HEAD', cache: 'no-store',
                    signal: AbortSignal.timeout(2000),
                });
                if (res.ok || res.status === 302 || res.status === 200) return true;
            } catch {
                // server still restarting — keep polling
            }
        }
        return false;
    }

    // ── AJAX form submit helper ───────────────────────────────────────────────
    async function submitForm(form, {
        loaderMsg = 'Saving settings…',
        restartMsg = 'Applying settings, server restarting…',
        successMsg = null,
        isEnvWrite = true,   // .env forms need server-restart handling
    } = {}) {
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        showLoader(loaderMsg);

        const data = new FormData(form);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            ?? data.get('_token');

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: data,
            });

            // Server restarted mid-request — wait and reload
            if (!res.ok && (res.status === 0 || res.status === 503 || res.status >= 500)) {
                updateLoader(restartMsg);
                const back = await waitForServer();
                hideLoader();
                if (back) {
                    showToast(successMsg ?? 'Settings saved. Page reloading…', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast('Server is taking longer than expected. Please reload the page.', 'error');
                    if (submitBtn) submitBtn.disabled = false;
                }
                return;
            }

            const json = await res.json().catch(() => ({}));

            if (res.ok && json.success) {
                if (isEnvWrite) {
                    // .env was written — server may restart; poll until back
                    updateLoader(restartMsg);
                    await waitForServer(12000, 600);
                }
                hideLoader();
                showToast(successMsg ?? json.message ?? 'Settings saved successfully.', 'success');
                if (submitBtn) submitBtn.disabled = false;
            } else {
                hideLoader();
                const errMsg = json.message
                    ?? (json.errors ? Object.values(json.errors).flat().join(' ') : null)
                    ?? 'Failed to save settings.';
                showToast(errMsg, 'error');
                if (submitBtn) submitBtn.disabled = false;
            }
        } catch (err) {
            // Network error — server likely restarted
            updateLoader(restartMsg);
            const back = await waitForServer();
            hideLoader();
            if (back) {
                showToast(successMsg ?? 'Settings saved. Page reloading…', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast('Connection lost. Please reload the page manually.', 'error');
                if (submitBtn) submitBtn.disabled = false;
            }
        }
    }

    // ── Bind all gateway save forms ───────────────────────────────────────────
    const formConfig = [
        {
            selector: 'form[action*="gateway-settings/sms"]',
            loaderMsg: 'Saving SMS gateway settings…',
            successMsg: 'SMS gateway settings saved successfully.',
        },
        {
            selector: 'form[action*="gateway-settings/mail"]',
            loaderMsg: 'Saving SMTP mail settings…',
            restartMsg: 'Applying mail settings, please wait…',
            successMsg: 'Mail SMTP settings saved. Ready to send emails.',
        },
        {
            selector: 'form[action*="gateway-settings/zinipay"]',
            loaderMsg: 'Saving ZiniPay settings…',
            successMsg: 'ZiniPay gateway settings saved successfully.',
        },
    ];

    formConfig.forEach(({ selector, loaderMsg, restartMsg, successMsg }) => {
        const form = document.querySelector(selector);
        if (!form) return;

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            submitForm(form, { loaderMsg, restartMsg, successMsg });
        });
    });

    // ── Test SMS / Test Email buttons ─────────────────────────────────────────
    async function runTest(actionUrl, payload, loaderMsg) {
        showLoader(loaderMsg);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const data = new FormData();
        data.append('_token', csrfToken);
        Object.entries(payload).forEach(([k, v]) => data.append(k, v));

        try {
            const res = await fetch(actionUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: data,
            });
            const json = await res.json().catch(() => ({}));
            hideLoader();
            showToast(json.message ?? (res.ok ? 'Test sent.' : 'Test failed.'), res.ok ? 'success' : 'error');
        } catch {
            hideLoader();
            showToast('Connection error during test.', 'error');
        }
    }

    // Test SMS button
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-test-sms]');
        if (!btn) return;
        const phone = document.getElementById('test_phone')?.value?.trim();
        if (!phone) { showToast('Please enter a phone number to test.', 'error'); return; }
        const url = btn.dataset.testSms;
        runTest(url, { test_phone: phone }, 'Sending test SMS…');
    });

    // Test Email button
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-test-email]');
        if (!btn) return;
        const email = document.getElementById('test_email')?.value?.trim();
        if (!email) { showToast('Please enter an email address to test.', 'error'); return; }
        const url = btn.dataset.testEmail;
        runTest(url, { test_email: email }, 'Sending test email…');
    });
});
