@extends('install.layout')

@section('title', 'License Activation')
@section('subtitle', 'Step 1 of 3 — Activate your license')

@section('content')

    {{-- 3-Step Progress Bar --}}
    <div class="progress-steps">
        <div class="progress-step active">
            <div class="progress-step-circle">1</div>
            <div class="progress-step-label">License</div>
        </div>
        <div class="progress-step">
            <div class="progress-step-circle">2</div>
            <div class="progress-step-label">Database</div>
        </div>
        <div class="progress-step">
            <div class="progress-step-circle">3</div>
            <div class="progress-step-label">Finish</div>
        </div>
    </div>

    {{-- Alerts --}}
    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- ── FORM STATE ─────────────────────────────────────────────────── --}}
    <div id="stateForm">
        <form id="licenseForm">
            @csrf

            <div class="form-group">
                <label for="purchase_code">Envato Purchase Code</label>
                <input
                    type="text"
                    id="purchase_code"
                    name="purchase_code"
                    value="{{ old('purchase_code') }}"
                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                    required
                    autocomplete="off"
                >
                <p class="form-help">
                    Find it in
                    <a href="https://help.market.envato.com/hc/en-us/articles/202822600" target="_blank">
                        Envato Market → Downloads → Licenses &amp; Purchase Codes
                    </a>.
                </p>
            </div>

            <div id="formError" class="alert alert-error" style="display:none;"></div>

            <button type="button" class="btn" id="verifyBtn" onclick="submitLicense()">
                Verify License &amp; Continue →
            </button>
        </form>
    </div>

    {{-- ── LOADING STATE ───────────────────────────────────────────────── --}}
    <div id="stateLoading" style="display:none; text-align:center; padding: 20px 0 28px;">
        <div style="
            width: 52px; height: 52px;
            border: 5px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 18px;
        "></div>
        <h2 style="font-size: 17px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
            Verifying your license…
        </h2>
        <p style="color: #64748b; font-size: 13px;">
            Connecting to Envato API. This takes a few seconds.
        </p>
    </div>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            color: #065f46;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>

    <script>
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function submitLicense() {
            const code   = document.getElementById('purchase_code').value.trim();
            const errEl  = document.getElementById('formError');

            if (!code) {
                errEl.textContent = 'Please enter your purchase code.';
                errEl.style.display = 'block';
                return;
            }

            // Basic UUID format check
            const uuidRe = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
            if (!uuidRe.test(code)) {
                errEl.textContent = 'Invalid purchase code format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
                errEl.style.display = 'block';
                return;
            }

            errEl.style.display = 'none';

            // Show loader
            document.getElementById('stateForm').style.display    = 'none';
            document.getElementById('stateLoading').style.display = 'block';

            // Submit via fetch so we control the transition
            const formData = new FormData();
            formData.append('_token', CSRF);
            formData.append('purchase_code', code);

            fetch('/install/license/verify', {
                method: 'POST',
                body: formData,
                redirect: 'follow',
            })
            .then(response => {
                // If server redirected us to /install/database → go there
                if (response.ok || response.redirected) {
                    window.location.href = response.url || '/install/database';
                } else {
                    return response.text().then(html => {
                        // Parse error message from redirected page
                        document.open();
                        document.write(html);
                        document.close();
                    });
                }
            })
            .catch(() => {
                // Fallback: just do a normal form POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/install/license/verify';
                const t = document.createElement('input');
                t.type = 'hidden'; t.name = '_token'; t.value = CSRF;
                form.appendChild(t);
                const p = document.createElement('input');
                p.type = 'hidden'; p.name = 'purchase_code'; p.value = code;
                form.appendChild(p);
                document.body.appendChild(form);
                form.submit();
            });
        }

        // Allow Enter key to submit
        document.getElementById('purchase_code').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); submitLicense(); }
        });
    </script>
@endsection
