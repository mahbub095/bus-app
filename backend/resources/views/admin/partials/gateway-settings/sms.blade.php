<div class="settings-section">
    <div class="settings-section-header">
        <span class="settings-section-icon">💬</span>
        <div>
            <h3 class="settings-section-title">SMS Gateway Integration</h3>
            <p class="settings-section-desc">
                Manage passenger SMS notifications. Supports SMS.NET.BD, BulkSMSBD, and custom HTTP APIs.
            </p>
        </div>
    </div>

    <form action="{{ route('admin.gateway-settings.update-sms') }}" method="POST">
        @csrf
        <input type="hidden" name="admin_tab" value="gateways">

        <div class="settings-fields-grid gateway-fields-3">
            <div class="input-group">
                <label for="gateway_name">Gateway Provider Name</label>
                <input type="text" name="gateway_name" id="gateway_name" class="coupon-input"
                       value="{{ old('gateway_name', $sms?->gateway_name ?? 'SMS.NET.BD') }}" required>
            </div>

            <div class="input-group">
                <label for="gateway_driver">Provider API Driver</label>
                @php $driver = old('gateway_driver', $sms?->gateway_driver ?? 'smsnetbd'); @endphp
                <select name="gateway_driver" id="gateway_driver" class="coupon-input" required>
                    <option value="smsnetbd" @selected($driver === 'smsnetbd')>SMS.NET.BD (Optimized)</option>
                    <option value="bulksmsbd" @selected($driver === 'bulksmsbd')>BulkSMSBD</option>
                    <option value="custom" @selected($driver === 'custom')>Custom HTTP POST Form</option>
                    <option value="get_query" @selected($driver === 'get_query')>Custom HTTP GET Query</option>
                </select>
            </div>

            <div class="input-group">
                <label for="api_url">Gateway API URL</label>
                <input type="url" name="api_url" id="api_url" class="coupon-input"
                       value="{{ old('api_url', $sms?->api_url ?? 'https://api.sms.net.bd/sendsms') }}" required>
            </div>
        </div>

        <div class="settings-fields-grid gateway-fields-2-wide mt-16">
            <div class="input-group">
                <label for="api_key">API Authentication Key</label>
                <input type="password" name="api_key" id="api_key" class="coupon-input"
                       value="{{ old('api_key', $sms?->api_key ?? '') }}"
                       placeholder="Enter gateway API token / secret key" required>
            </div>

            <div class="input-group">
                <label for="sender_id">Sender ID / Mask (Optional)</label>
                <input type="text" name="sender_id" id="sender_id" class="coupon-input"
                       value="{{ old('sender_id', $sms?->sender_id ?? '') }}" placeholder="e.g. BrandName">
            </div>
        </div>

        <div class="input-group mt-16">
            <label for="message_template">Booking SMS Message Template</label>
            <textarea name="message_template" id="message_template" class="coupon-input" rows="3"
                      placeholder="Template text for passenger ticket confirmations...">{{ old('message_template', $sms?->message_template ?? 'SonyaBus ticket confirmed. PNR {PNR}, Seats {SEATS}, Fare BDT {FARE}. Status: {STATUS}') }}</textarea>
            <p class="gateway-field-hint">
                Available variables:
                <code>{PNR}</code>, <code>{SEATS}</code>, <code>{FARE}</code>, <code>{STATUS}</code>
            </p>
        </div>

        <div class="gateway-form-actions">
            <div class="gateway-toggle-row">
                <label class="toggle-switch">
                    <input type="checkbox" name="is_active" value="true" id="sms_active_toggle"
                           @checked($smsActive)>
                    <span class="toggle-slider"></span>
                </label>
                <span class="toggle-label" id="sms_status_label">
                    {{ $smsActive ? '🟢 SMS Service is ACTIVE' : '🔴 SMS Service is INACTIVE' }}
                </span>
            </div>

            <button type="submit" class="btn btn-primary gateway-save-btn">
                💾 Save SMS Settings
            </button>
        </div>
    </form>
</div>
