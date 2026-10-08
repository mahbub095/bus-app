{{-- ======================================================================
     License Management Panel (Super Admin only)
     Displays purchase code status and allows re-verification.
====================================================================== --}}
<div class="admin-panel">
    <div class="admin-panel-title">
        <span>🔑 License Management</span>
    </div>

    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 24px; line-height: 1.6;">
        Your Envato CodeCanyon purchase license status. Re-verification is required if you migrate to a new domain.
    </p>

    {{-- Alerts --}}
    @if (session('success') && request('admin_tab') === 'license')
        <div class="alert-success" style="padding: 12px 16px; background: #f0fdf4; border-left: 4px solid #10b981;
             color: #065f46; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->has('purchase_code') && request('admin_tab') === 'license')
        <div class="alert-error" style="padding: 12px 16px; background: #fef2f2; border-left: 4px solid #ef4444;
             color: #991b1b; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
            {{ $errors->first('purchase_code') }}
        </div>
    @endif

    {{-- Current License Status --}}
    <div id="license-status-card" style="background: var(--bg-secondary); border-radius: 10px; padding: 20px; margin-bottom: 28px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
            <span style="font-size: 20px;">📋</span>
            <h3 style="font-size: 15px; font-weight: 600; color: var(--text-primary);">Current License Status</h3>
        </div>

        @php
            $licenseInfo = app(\App\Services\LicenseService::class)->info();
        @endphp

        @if (!empty($licenseInfo['activated']))
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
                <div>
                    <span style="color: var(--text-secondary);">Status</span><br>
                    <span style="font-weight: 600; color: #10b981;">✅ Activated</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary);">License Type</span><br>
                    <span style="font-weight: 600; color: var(--text-primary);">{{ $licenseInfo['license_type'] ?? '—' }}</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary);">Purchase Code</span><br>
                    <span style="font-weight: 600; color: var(--text-primary); font-family: monospace;">{{ $licenseInfo['purchase_code'] ?? '—' }}</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary);">Envato Username</span><br>
                    <span style="font-weight: 600; color: var(--text-primary);">{{ $licenseInfo['envato_username'] ?? '—' }}</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary);">Purchase Date</span><br>
                    <span style="font-weight: 600; color: var(--text-primary);">{{ $licenseInfo['purchase_date'] ?? '—' }}</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary);">Verified At</span><br>
                    <span style="font-weight: 600; color: var(--text-primary);">{{ $licenseInfo['verified_at'] ?? '—' }}</span>
                </div>
            </div>
        @else
            <div style="color: #ef4444; font-weight: 600; font-size: 14px;">
                ❌ No valid license found. Please verify your purchase code below.
            </div>
        @endif
    </div>

    {{-- Re-Verification Form --}}
    <div style="background: var(--bg-secondary); border-radius: 10px; padding: 20px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
            <span style="font-size: 20px;">🔄</span>
            <h3 style="font-size: 15px; font-weight: 600; color: var(--text-primary);">Verify / Re-Verify License</h3>
        </div>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px; line-height: 1.5;">
            Use this form if you have migrated to a new domain or want to re-confirm your license.
        </p>

        <form action="{{ route('admin.license.re-verify') }}" method="POST">
            @csrf
            <input type="hidden" name="admin_tab" value="license">

            <div class="input-group" style="margin-bottom: 20px;">
                <label for="purchase_code">Envato Purchase Code</label>
                <input
                    type="text"
                    id="purchase_code"
                    name="purchase_code"
                    class="coupon-input"
                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                    autocomplete="off"
                    required
                    style="width: 100%;"
                >
                <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                    Found in Envato Market → Downloads → Licenses &amp; Purchase Codes.
                </small>
            </div>

            <button type="submit" class="btn btn-primary">
                🔑 Verify License
            </button>
        </form>
    </div>
</div>
