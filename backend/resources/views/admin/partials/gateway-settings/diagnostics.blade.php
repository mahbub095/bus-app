<div class="settings-section gateway-diagnostics">
    <div class="settings-section-header">
        <span class="settings-section-icon gateway-diagnostics-icon">🧪</span>
        <div>
            <h3 class="settings-section-title gateway-diagnostics-title">Gateway Diagnostics & Testing</h3>
            <p class="settings-section-desc">
                Send test payloads to verify saved configuration. Save your changes before testing.
            </p>
        </div>
    </div>

    <div class="settings-fields-grid gateway-diagnostics-grid">
        <div class="gateway-test-card">
            <h4 class="gateway-test-title">💬 Send Test SMS</h4>
            <form action="{{ route('admin.gateway-settings.test-sms') }}" method="POST">
                @csrf
                <div class="input-group">
                    <label for="test_phone">Recipient Mobile Number (BD format)</label>
                    <input type="text" name="test_phone" id="test_phone" class="coupon-input"
                           value="{{ old('test_phone') }}" placeholder="e.g. 01712345678" required>
                </div>
                <div class="input-group">
                    <label for="test_message">Test Message (Optional)</label>
                    <input type="text" name="test_message" id="test_message" class="coupon-input"
                           value="{{ old('test_message') }}" placeholder="SonyaBus diagnostics test message">
                </div>
                <button type="submit" class="btn btn-secondary btn-sm gateway-test-btn">
                    🚀 Dispatch Test SMS
                </button>
            </form>
        </div>

        <div class="gateway-test-card">
            <h4 class="gateway-test-title">✉️ Send Test Email</h4>
            <form action="{{ route('admin.gateway-settings.test-email') }}" method="POST">
                @csrf
                <div class="input-group">
                    <label for="test_email">Recipient Email Address</label>
                    <input type="email" name="test_email" id="test_email" class="coupon-input"
                           value="{{ old('test_email') }}" placeholder="e.g. admin@example.com" required>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm gateway-test-btn">
                    🚀 Dispatch Test Email
                </button>
            </form>
        </div>
    </div>
</div>
