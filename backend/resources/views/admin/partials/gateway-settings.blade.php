{{-- Integrations & Gateways Admin Panel --}}
@php
    $sms = $smsConfig;
    $mail = $siteSettings ?? [];
    $smsActive = (bool) ($sms?->is_active ?? false);
@endphp

<div class="admin-panel gateway-settings-panel">
    <div class="admin-panel-title">
        <span>🔌 Integrations & Gateways Management</span>
    </div>

    <p class="gateway-settings-intro">
        Configure third-party gateways for SMS notifications, email dispatches, and online ticket payments.
        Always test connectivity after saving changes.
    </p>

    @include('admin.partials.gateway-settings.sms', ['sms' => $sms, 'smsActive' => $smsActive])
    @include('admin.partials.gateway-settings.mail', ['mail' => $mail])
    @include('admin.partials.gateway-settings.zinipay', ['mail' => $mail])
    @include('admin.partials.gateway-settings.diagnostics')
</div>

@include('admin.partials.gateway-settings.styles')

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const smsToggle = document.getElementById('sms_active_toggle');
        const smsLabel = document.getElementById('sms_status_label');

        if (!smsToggle || !smsLabel) {
            return;
        }

        smsToggle.addEventListener('change', function() {
            smsLabel.textContent = this.checked
                ? '🟢 SMS Service is ACTIVE'
                : '🔴 SMS Service is INACTIVE';
        });
    });
</script>
