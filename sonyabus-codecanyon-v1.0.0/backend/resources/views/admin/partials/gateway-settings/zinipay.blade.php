<div class="settings-section">
    <div class="settings-section-header">
        <span class="settings-section-icon">💳</span>
        <div>
            <h3 class="settings-section-title">ZiniPay Payment Gateway</h3>
            <p class="settings-section-desc">
                Configure customer online payments. Keys are stored in site settings and .env.
            </p>
        </div>
    </div>

    <form action="{{ route('admin.gateway-settings.update-zinipay') }}" method="POST">
        @csrf
        <input type="hidden" name="admin_tab" value="gateways">

        <div class="settings-fields-grid gateway-fields-2-wide">
            <div class="input-group">
                <label for="zinipay_api_key">ZiniPay API Secret Key</label>
                <input type="password" name="zinipay_api_key" id="zinipay_api_key" class="coupon-input"
                       value="{{ old('zinipay_api_key', $mail['zinipay_api_key'] ?? '') }}"
                       placeholder="zini_sec_..." required>
            </div>

            <div class="input-group">
                <label for="zinipay_base_url">ZiniPay Base Endpoint URL</label>
                <input type="url" name="zinipay_base_url" id="zinipay_base_url" class="coupon-input"
                       value="{{ old('zinipay_base_url', $mail['zinipay_base_url'] ?? 'https://api.zinipay.com') }}" required>
            </div>
        </div>

        <div class="gateway-form-actions gateway-form-actions-end">
            <button type="submit" class="btn btn-primary gateway-save-btn">
                💾 Save ZiniPay Settings
            </button>
        </div>
    </form>
</div>
