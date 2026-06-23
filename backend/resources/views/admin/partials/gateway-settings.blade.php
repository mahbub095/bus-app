{{-- Integrations & Gateways Admin Panel --}}
<div class="admin-panel">
    <div class="admin-panel-title">
        <span>🔌 Integrations & Gateways Management</span>
    </div>

    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 24px; line-height: 1.6;">
        Configure third-party gateways for SMS notifications, email dispatches, and online ticket payments.
        Always test connectivity after saving changes.
    </p>

    {{-- Section 1: SMS Gateway Configuration --}}
    <div class="settings-section" style="margin-bottom: 24px;">
        <div class="settings-section-header" style="margin-bottom: 16px;">
            <span class="settings-section-icon" style="font-size: 20px; margin-right: 8px;">💬</span>
            <div>
                <h3 class="settings-section-title" style="font-size: 16px; font-weight: 700; color: #fff;">SMS Gateway Integration</h3>
                <p class="settings-section-desc" style="font-size: 12px; color: var(--text-secondary);">Manage your passenger SMS verification services. Supports SMS.NET.BD, BulkSMSBD, and custom HTTP APIs.</p>
            </div>
        </div>

        <form action="{{ route('admin.gateway-settings.update-sms') }}" method="POST">
            @csrf
            <input type="hidden" name="admin_tab" value="gateways">

            <div class="settings-fields-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div class="input-group">
                    <label for="gateway_name">Gateway Provider Name</label>
                    <input type="text" name="gateway_name" id="gateway_name" class="coupon-input"
                           value="{{ $smsConfig->gateway_name ?? 'SMS.NET.BD' }}" required>
                </div>

                <div class="input-group">
                    <label for="gateway_driver">Provider API Driver</label>
                    <select name="gateway_driver" id="gateway_driver" class="coupon-input" required>
                        <option value="smsnetbd" {{ ($smsConfig->gateway_driver ?? '') === 'smsnetbd' ? 'selected' : '' }}>SMS.NET.BD (Optimized)</option>
                        <option value="bulksmsbd" {{ ($smsConfig->gateway_driver ?? '') === 'bulksmsbd' ? 'selected' : '' }}>BulkSMSBD</option>
                        <option value="custom" {{ ($smsConfig->gateway_driver ?? '') === 'custom' ? 'selected' : '' }}>Custom HTTP POST Form</option>
                        <option value="get_query" {{ ($smsConfig->gateway_driver ?? '') === 'get_query' ? 'selected' : '' }}>Custom HTTP GET Query</option>
                    </select>
                </div>

                <div class="input-group">
                    <label for="api_url">Gateway API URL</label>
                    <input type="url" name="api_url" id="api_url" class="coupon-input"
                           value="{{ $smsConfig->api_url ?? 'https://api.sms.net.bd/sendsms' }}" required>
                </div>
            </div>

            <div class="settings-fields-grid" style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 16px; margin-top: 16px;">
                <div class="input-group">
                    <label for="api_key">API Authentication Key</label>
                    <input type="password" name="api_key" id="api_key" class="coupon-input"
                           value="{{ $smsConfig->api_key ?? '' }}" placeholder="Enter gateway API token / secret key" required>
                </div>

                <div class="input-group">
                    <label for="sender_id">Sender ID / Mask (Optional)</label>
                    <input type="text" name="sender_id" id="sender_id" class="coupon-input"
                           value="{{ $smsConfig->sender_id ?? '' }}" placeholder="e.g. BrandName">
                </div>
            </div>

            <div class="input-group" style="margin-top: 16px;">
                <label for="message_template">Booking SMS Message Template</label>
                <textarea name="message_template" id="message_template" class="coupon-input" rows="3"
                          placeholder="Template text for passenger ticket confirmations..."
                >{{ $smsConfig->message_template ?? 'SonyaBus ticket confirmed. PNR {PNR}, Seats {SEATS}, Fare BDT {FARE}. Status: {STATUS}' }}</textarea>
                <p style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                    Available variables: <code style="color: var(--primary);">{PNR}</code>, <code style="color: var(--primary);">{SEATS}</code>, <code style="color: var(--primary);">{FARE}</code>, <code style="color: var(--primary);">{STATUS}</code>
                </p>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label class="toggle-switch" style="position: relative; display: inline-block; width: 40px; height: 20px; margin: 0;">
                        <input type="checkbox" name="is_active" value="true"
                               {{ ($smsConfig->is_active ?? false) ? 'checked' : '' }}
                               id="sms_active_toggle" style="opacity: 0; width: 0; height: 0;">
                        <span class="toggle-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #2D2D44; transition: .4s; border-radius: 20px;"></span>
                    </label>
                    <div>
                        <span class="toggle-label" id="sms_status_label" style="font-size: 12px; font-weight: 600;">
                            {{ ($smsConfig->is_active ?? false) ? '🟢 SMS Service is ACTIVE' : '🔴 SMS Service is INACTIVE' }}
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 8px 20px; font-size: 13px;">
                    💾 Save SMS Settings
                </button>
            </div>
        </form>
    </div>

    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 30px 0;">

    {{-- Section 2: SMTP Mailer Configuration --}}
    <div class="settings-section" style="margin-bottom: 24px;">
        <div class="settings-section-header" style="margin-bottom: 16px;">
            <span class="settings-section-icon" style="font-size: 20px; margin-right: 8px;">✉️</span>
            <div>
                <h3 class="settings-section-title" style="font-size: 16px; font-weight: 700; color: #fff;">Mail Dispatcher (SMTP)</h3>
                <p class="settings-section-desc" style="font-size: 12px; color: var(--text-secondary);">Configure outgoing email notifications for password resets and receipts. Saved to site settings.</p>
            </div>
        </div>

        <form action="{{ route('admin.gateway-settings.update-mail') }}" method="POST">
            @csrf
            <input type="hidden" name="admin_tab" value="gateways">

            <div class="settings-fields-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div class="input-group">
                    <label for="mail_mailer">Mail Driver / Mailer</label>
                    <select name="mail_mailer" id="mail_mailer" class="coupon-input" required>
                        <option value="smtp" {{ ($siteSettings['mail_mailer'] ?? 'smtp') === 'smtp' ? 'selected' : '' }}>SMTP Server</option>
                        <option value="log" {{ ($siteSettings['mail_mailer'] ?? 'smtp') === 'log' ? 'selected' : '' }}>Log (Dev Environment)</option>
                    </select>
                </div>

                <div class="input-group">
                    <label for="mail_host">SMTP Server Host</label>
                    <input type="text" name="mail_host" id="mail_host" class="coupon-input"
                           value="{{ $siteSettings['mail_host'] ?? 'smtp-relay.brevo.com' }}" placeholder="smtp.mailgun.org">
                </div>

                <div class="input-group">
                    <label for="mail_port">SMTP Port</label>
                    <input type="number" name="mail_port" id="mail_port" class="coupon-input"
                           value="{{ $siteSettings['mail_port'] ?? '587' }}" placeholder="587">
                </div>
            </div>

            <div class="settings-fields-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 16px;">
                <div class="input-group">
                    <label for="mail_username">SMTP Authentication Username</label>
                    <input type="text" name="mail_username" id="mail_username" class="coupon-input"
                           value="{{ $siteSettings['mail_username'] ?? '' }}" placeholder="smtp_username">
                </div>

                <div class="input-group">
                    <label for="mail_password">SMTP Authentication Password</label>
                    <input type="password" name="mail_password" id="mail_password" class="coupon-input"
                           value="{{ $siteSettings['mail_password'] ?? '' }}" placeholder="smtp_password">
                </div>

                <div class="input-group">
                    <label for="mail_encryption">Transport Encryption</label>
                    <select name="mail_encryption" id="mail_encryption" class="coupon-input">
                        <option value="tls" {{ ($siteSettings['mail_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS / STARTTLS</option>
                        <option value="ssl" {{ ($siteSettings['mail_encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL (Implicit)</option>
                        <option value="none" {{ ($siteSettings['mail_encryption'] ?? '') === 'none' ? 'selected' : '' }}>None (Clear Text)</option>
                    </select>
                </div>
            </div>

            <div class="settings-fields-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 16px;">
                <div class="input-group">
                    <label for="mail_from_address">Global From Email Address</label>
                    <input type="email" name="mail_from_address" id="mail_from_address" class="coupon-input"
                           value="{{ $siteSettings['mail_from_address'] ?? 'noreply@sonyabus.com' }}" required>
                </div>

                <div class="input-group">
                    <label for="mail_from_name">Global Sender Name</label>
                    <input type="text" name="mail_from_name" id="mail_from_name" class="coupon-input"
                           value="{{ $siteSettings['mail_from_name'] ?? 'SonyaBus' }}" required>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px; font-size: 13px;">
                    💾 Save SMTP Settings
                </button>
            </div>
        </form>
    </div>

    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 30px 0;">

    {{-- Section 3: ZiniPay Payment Gateway --}}
    <div class="settings-section" style="margin-bottom: 24px;">
        <div class="settings-section-header" style="margin-bottom: 16px;">
            <span class="settings-section-icon" style="font-size: 20px; margin-right: 8px;">💳</span>
            <div>
                <h3 class="settings-section-title" style="font-size: 16px; font-weight: 700; color: #fff;">ZiniPay Payment Gateway</h3>
                <p class="settings-section-desc" style="font-size: 12px; color: var(--text-secondary);">Configure customer online payments. Keys are stored in site settings.</p>
            </div>
        </div>

        <form action="{{ route('admin.gateway-settings.update-zinipay') }}" method="POST">
            @csrf
            <input type="hidden" name="admin_tab" value="gateways">

            <div class="settings-fields-grid" style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 16px;">
                <div class="input-group">
                    <label for="zinipay_api_key">ZiniPay API Secret Key</label>
                    <input type="password" name="zinipay_api_key" id="zinipay_api_key" class="coupon-input"
                           value="{{ $siteSettings['zinipay_api_key'] ?? '' }}" placeholder="zini_sec_..." required>
                </div>

                <div class="input-group">
                    <label for="zinipay_base_url">ZiniPay Base Endpoint URL</label>
                    <input type="url" name="zinipay_base_url" id="zinipay_base_url" class="coupon-input"
                           value="{{ $siteSettings['zinipay_base_url'] ?? 'https://api.zinipay.com' }}" required>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 8px 20px; font-size: 13px;">
                    💾 Save ZiniPay Settings
                </button>
            </div>
        </form>
    </div>

    {{-- Section 4: Testing & Diagnostics --}}
    <div class="settings-section" style="margin-top: 40px; border: 1px solid rgba(99, 102, 241, 0.3); background-color: rgba(99, 102, 241, 0.02); padding: 20px; border-radius: 8px;">
        <div class="settings-section-header" style="border-bottom: 1px solid rgba(99, 102, 241, 0.2); margin-bottom: 16px; padding-bottom: 12px; display: flex; align-items: center;">
            <span class="settings-section-icon" style="background-color: rgba(99, 102, 241, 0.12); color: var(--primary); font-size: 20px; padding: 6px; border-radius: 6px; margin-right: 8px;">🧪</span>
            <div>
                <h3 class="settings-section-title" style="color: #A5B4FC; font-size: 16px; font-weight: 700; margin: 0;">Gateway Diagnostics & Testing</h3>
                <p class="settings-section-desc" style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Validate active configuration drivers by sending test payloads. Ensure changes are saved before testing.</p>
            </div>
        </div>

        <div class="settings-fields-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            {{-- Test SMS Panel --}}
            <div style="background-color: rgba(0, 0, 0, 0.2); border: 1px solid var(--border-color); padding: 18px; border-radius: 8px;">
                <h4 style="font-size: 13px; text-transform: uppercase; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <span>💬</span> Send Test SMS
                </h4>
                <form action="{{ route('admin.gateway-settings.test-sms') }}" method="POST">
                    @csrf
                    <div class="input-group" style="margin-bottom: 12px;">
                        <label for="test_phone" style="font-size: 10px;">Recipient Mobile Number (BD format)</label>
                        <input type="text" name="test_phone" id="test_phone" class="coupon-input"
                               placeholder="e.g. 01712345678" style="padding: 8px 10px;" required>
                    </div>
                    <div class="input-group" style="margin-bottom: 14px;">
                        <label for="test_message" style="font-size: 10px;">Test Message (Optional)</label>
                        <input type="text" name="test_message" id="test_message" class="coupon-input"
                               placeholder="SonyaBus diagnostics test message" style="padding: 8px 10px;">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%; height: 36px;">
                        🚀 Dispatch Test SMS
                    </button>
                </form>
            </div>

            {{-- Test Email Panel --}}
            <div style="background-color: rgba(0, 0, 0, 0.2); border: 1px solid var(--border-color); padding: 18px; border-radius: 8px;">
                <h4 style="font-size: 13px; text-transform: uppercase; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <span>✉️</span> Send Test Email
                </h4>
                <form action="{{ route('admin.gateway-settings.test-email') }}" method="POST">
                    @csrf
                    <div class="input-group" style="margin-bottom: 40px;"> {{-- padded to align layout --}}
                        <label for="test_email" style="font-size: 10px;">Recipient Email Address</label>
                        <input type="email" name="test_email" id="test_email" class="coupon-input"
                               placeholder="e.g. admin@example.com" style="padding: 8px 10px;" required>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%; height: 36px;">
                        🚀 Dispatch Test Email
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* Styling rules for toggle switch */
    .toggle-switch input:checked + .toggle-slider {
        background-color: var(--success) !important;
    }
    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 14px;
        width: 14px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const smsToggle = document.getElementById('sms_active_toggle');
        const smsLabel = document.getElementById('sms_status_label');
        if (smsToggle && smsLabel) {
            smsToggle.addEventListener('change', function() {
                smsLabel.textContent = this.checked
                    ? '🟢 SMS Service is ACTIVE'
                    : '🔴 SMS Service is INACTIVE';
            });
        }
    });
</script>
