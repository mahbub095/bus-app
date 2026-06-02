<div class="admin-sections-layout" style="grid-column: 1 / -1;">
    <div class="admin-panel">
        <h3 class="admin-panel-title">Bangladesh SMS Gateway Configuration</h3>
        <div class="notice-info-box">
            Configure your SMS provider to send customer verification messages after successful ticket payment.
            Supported placeholders in message template: <strong>{PNR}</strong>, <strong>{SEATS}</strong>, <strong>{FARE}</strong>, <strong>{STATUS}</strong>.
        </div>
    </div>

    <div class="booking-form-sidebar">
        <h3 class="booking-summary-title">SMS Service Setup</h3>
        <form class="booking-form-fields" action="{{ route('admin.sms-config.update') }}" method="POST">
            @csrf

            <div class="input-group">
                <label>Gateway Name</label>
                <input
                    type="text"
                    name="gateway_name"
                    class="coupon-input"
                    required
                    value="{{ old('gateway_name', $smsConfig?->gateway_name ?? 'Generic Bangladesh SMS Gateway') }}"
                    placeholder="e.g. BulkSMSBD"
                >
            </div>

            <div class="input-group">
                <label>Gateway API URL</label>
                <input
                    type="url"
                    name="api_url"
                    class="coupon-input"
                    value="{{ old('api_url', $smsConfig?->api_url) }}"
                    placeholder="https://example.com/send-sms"
                >
            </div>

            <div class="input-group">
                <label>API Key</label>
                <input
                    type="text"
                    name="api_key"
                    class="coupon-input"
                    value="{{ old('api_key', $smsConfig?->api_key) }}"
                    placeholder="Your provider API key"
                >
            </div>

            <div class="input-group">
                <label>Sender ID</label>
                <input
                    type="text"
                    name="sender_id"
                    class="coupon-input"
                    value="{{ old('sender_id', $smsConfig?->sender_id) }}"
                    placeholder="SONYABUS"
                >
            </div>

            <div class="input-group">
                <label>Message Template</label>
                <textarea
                    name="message_template"
                    class="coupon-input"
                    rows="4"
                    placeholder="SonyaBus ticket confirmed. PNR {PNR}, Seats {SEATS}, Fare BDT {FARE}."
                >{{ old('message_template', $smsConfig?->message_template) }}</textarea>
            </div>

            <div class="input-group">
                <label>Service Status</label>
                <select name="is_active" class="coupon-input">
                    <option value="1" {{ old('is_active', $smsConfig?->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ old('is_active', $smsConfig?->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <button class="btn btn-primary" type="submit" style="height: 42px; margin-top: 10px;">
                Save SMS Configuration
            </button>
        </form>
    </div>
</div>
