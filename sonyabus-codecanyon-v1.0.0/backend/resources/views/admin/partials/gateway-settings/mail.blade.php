<div class="settings-section">
    <div class="settings-section-header">
        <span class="settings-section-icon">✉️</span>
        <div>
            <h3 class="settings-section-title">Mail Dispatcher (SMTP)</h3>
            <p class="settings-section-desc">
                Configure outgoing email for password resets and receipts. Saved to site settings and .env.
            </p>
        </div>
    </div>

    <form action="{{ route('admin.gateway-settings.update-mail') }}" method="POST">
        @csrf
        <input type="hidden" name="admin_tab" value="gateways">

        <div class="settings-fields-grid gateway-fields-3">
            <div class="input-group">
                <label for="mail_mailer">Mail Driver / Mailer</label>
                @php $mailer = old('mail_mailer', $mail['mail_mailer'] ?? 'smtp'); @endphp
                <select name="mail_mailer" id="mail_mailer" class="coupon-input" required>
                    <option value="smtp" @selected($mailer === 'smtp')>SMTP Server</option>
                    <option value="log" @selected($mailer === 'log')>Log (Dev Environment)</option>
                </select>
            </div>

            <div class="input-group">
                <label for="mail_host">SMTP Server Host</label>
                <input type="text" name="mail_host" id="mail_host" class="coupon-input"
                       value="{{ old('mail_host', $mail['mail_host'] ?? 'smtp-relay.brevo.com') }}"
                       placeholder="smtp.mailgun.org">
            </div>

            <div class="input-group">
                <label for="mail_port">SMTP Port</label>
                <input type="number" name="mail_port" id="mail_port" class="coupon-input"
                       value="{{ old('mail_port', $mail['mail_port'] ?? '587') }}" placeholder="587">
            </div>
        </div>

        <div class="settings-fields-grid gateway-fields-3 mt-16">
            <div class="input-group">
                <label for="mail_username">SMTP Authentication Username</label>
                <input type="text" name="mail_username" id="mail_username" class="coupon-input"
                       value="{{ old('mail_username', $mail['mail_username'] ?? '') }}" placeholder="smtp_username">
            </div>

            <div class="input-group">
                <label for="mail_password">SMTP Authentication Password</label>
                <input type="password" name="mail_password" id="mail_password" class="coupon-input"
                       value="{{ old('mail_password', $mail['mail_password'] ?? '') }}" placeholder="smtp_password">
            </div>

            <div class="input-group">
                <label for="mail_encryption">Transport Encryption</label>
                @php $encryption = old('mail_encryption', $mail['mail_encryption'] ?? 'tls'); @endphp
                <select name="mail_encryption" id="mail_encryption" class="coupon-input">
                    <option value="tls" @selected($encryption === 'tls')>TLS / STARTTLS</option>
                    <option value="ssl" @selected($encryption === 'ssl')>SSL (Implicit)</option>
                    <option value="none" @selected($encryption === 'none')>None (Clear Text)</option>
                </select>
            </div>
        </div>

        <div class="settings-fields-grid gateway-fields-2 mt-16">
            <div class="input-group">
                <label for="mail_from_address">Global From Email Address</label>
                <input type="email" name="mail_from_address" id="mail_from_address" class="coupon-input"
                       value="{{ old('mail_from_address', $mail['mail_from_address'] ?? 'noreply@sonyabus.com') }}" required>
            </div>

            <div class="input-group">
                <label for="mail_from_name">Global Sender Name</label>
                <input type="text" name="mail_from_name" id="mail_from_name" class="coupon-input"
                       value="{{ old('mail_from_name', $mail['mail_from_name'] ?? 'SonyaBus') }}" required>
            </div>
        </div>

        <div class="gateway-form-actions gateway-form-actions-end">
            <button type="submit" class="btn btn-primary gateway-save-btn">
                💾 Save SMTP Settings
            </button>
        </div>
    </form>
</div>
