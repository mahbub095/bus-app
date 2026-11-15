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

    @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
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
                    spellcheck="false"
                >
                <p class="form-help">
                    Find your purchase code in
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
    <div id="stateLoading" style="display:none; text-align:center; padding: 24px 0 32px;">
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
            background: #fef2f2; border-left: 4px solid #ef4444;
            color: #991b1b; border-radius: 6px;
            padding: 12px 16px; margin-bottom: 20px; font-size: 14px;
        }
        .form-help { font-size: 12px; color: #64748b; margin-top: 6px; line-height: 1.5; }
        .form-help a { color: #667eea; text-decoration: none; }
        .form-help a:hover { text-decoration: underline; }
    </style>

    <script>
        const CSRF    = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

        function submitLicense() {
            const code  = document.getElementById('purchase_code').value.trim();
            const errEl = document.getElementById('formError');

            errEl.style.display = 'none';

            if (! code) {
                errEl.textContent = 'Please enter your purchase code.';
                errEl.style.display = 'block';
                return;
            }
            if (! UUID_RE.test(code)) {
                errEl.textContent = 'Invalid format. It should look like: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
                errEl.style.display = 'block';
                return;
            }

            // Show loader, hide form
            document.getElementById('stateForm').style.display    = 'none';
            document.getElementById('stateLoading').style.display = 'block';

            const formData = new FormData();
            formData.append('_token', CSRF);
            formData.append('purchase_code', code);

            fetch('/install/license/verify', {
                method: 'POST',
                body: formData,
                redirect: 'follow',
            })
            .then(response => {
                if (response.ok || response.redirected) {
                    window.location.href = response.url || '/install/database';
                } else {
                    return response.text().then(html => {
                        document.open(); document.write(html); document.close();
                    });
                }
            })
            .catch(() => {
                // Fallback: plain form POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/install/license/verify';
                [['_token', CSRF], ['purchase_code', code]].forEach(([n, v]) => {
                    const i = document.createElement('input');
                    i.type = 'hidden'; i.name = n; i.value = v;
                    form.appendChild(i);
                });
                document.body.appendChild(form);
                form.submit();
            });
        }

        document.getElementById('purchase_code').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); submitLicense(); }
        });
    </script>
@endsection
