<div class="admin-sections-layout" style="grid-column: 1 / -1;">
    <div class="admin-panel">
        <h3 class="admin-panel-title">Bangladesh SMS Gateway Configuration</h3>
        <div class="notice-info-box">
            <p style="margin-bottom: 8px;">
                SMS is sent automatically when a booking status is <strong>PAID</strong>.
                Use a Bangladesh mobile number format: <strong>01712345678</strong> or <strong>8801712345678</strong>.
            </p>
            <p style="margin-bottom: 0;">
                Message placeholders: <strong>{PNR}</strong>, <strong>{SEATS}</strong>, <strong>{FARE}</strong>, <strong>{STATUS}</strong>.
            </p>
        </div>

        @if($errors->has('sms_test'))
            <div class="alert-banner" style="margin-top: 16px; border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.12);">
                <span>✖</span>
                <span>{{ $errors->first('sms_test') }}</span>
            </div>
        @endif
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
                <label>Provider / API Format</label>
                <select name="gateway_driver" class="coupon-input" required>
                    @php
                        $driver = old('gateway_driver', $smsConfig?->gateway_driver ?? 'bulksmsbd');
                    @endphp
                    <option value="bulksmsbd" {{ $driver === 'bulksmsbd' ? 'selected' : '' }}>
                        BulkSMSBD (api_key, number, senderid, message)
                    </option>
                    <option value="custom" {{ $driver === 'custom' ? 'selected' : '' }}>
                        Custom POST (api_key, sender_id, mobile, message)
                    </option>
                    <option value="get_query" {{ $driver === 'get_query' ? 'selected' : '' }}>
                        Custom GET (query string parameters)
                    </option>
                </select>
            </div>

            <div class="input-group">
                <label>Gateway API URL</label>
                <input
                    type="url"
                    name="api_url"
                    class="coupon-input"
                    value="{{ old('api_url', $smsConfig?->api_url) }}"
                    placeholder="https://bulksmsbd.net/api/smsapi"
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
                    placeholder="Approved sender ID from your provider"
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

        <hr style="border: none; border-top: 1px solid var(--border-color); margin: 24px 0;">

        <h3 class="booking-summary-title" style="font-size: 16px;">Send Test SMS</h3>
        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 12px;">
            Save your configuration first, then send a test message to verify the gateway.
        </p>
        <form class="booking-form-fields" action="{{ route('admin.sms-config.test') }}" method="POST">
            @csrf
            <div class="input-group">
                <label>Test Mobile Number</label>
                <input
                    type="text"
                    name="test_phone"
                    class="coupon-input"
                    required
                    value="{{ old('test_phone') }}"
                    placeholder="01712345678"
                >
            </div>
            <button class="btn btn-secondary" type="submit" style="height: 42px; margin-top: 10px;">
                Send Test SMS
            </button>
        </form>
    </div>
</div>
