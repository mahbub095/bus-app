@extends('install.layout')

@section('title', 'Finalize Installation')
@section('subtitle', 'Step 3 of 3 — Create admin &amp; finish')

@section('content')

    {{-- 3-Step Progress Bar --}}
    <div class="progress-steps">
        <div class="progress-step completed">
            <div class="progress-step-circle">✓</div>
            <div class="progress-step-label">License</div>
        </div>
        <div class="progress-step completed">
            <div class="progress-step-circle">✓</div>
            <div class="progress-step-label">Database</div>
        </div>
        <div class="progress-step active">
            <div class="progress-step-circle">3</div>
            <div class="progress-step-label">Finish</div>
        </div>
    </div>

    {{-- ── FORM STATE ─────────────────────────────────────────────────── --}}
    <div id="stateForm">

        <div style="text-align:center; padding: 4px 0 18px;">
            <div style="font-size:44px; margin-bottom:10px;">🚀</div>
            <h2 style="font-size:18px; font-weight:700; color:#1e293b; margin-bottom:6px;">
                Create Admin Account
            </h2>
            <p style="color:#64748b; font-size:13px; margin-bottom:0;">
                Set up your super-admin login to complete the installation.
            </p>
        </div>

        <div id="formError" class="alert alert-error" style="display:none;"></div>

        <div class="form-group">
            <label for="admin_name">Full Name</label>
            <input type="text" id="admin_name" placeholder="Super Admin" autocomplete="name">
        </div>
        <div class="form-group">
            <label for="admin_email">Email Address</label>
            <input type="email" id="admin_email" placeholder="admin@example.com" autocomplete="email">
        </div>
        <div class="form-group">
            <label for="admin_password">
                Password
                <span style="color:#94a3b8; font-weight:normal; font-size:12px;">(min 8 characters)</span>
            </label>
            <input type="password" id="admin_password" placeholder="Min 8 characters" autocomplete="new-password">
        </div>

        <button class="btn" id="runBtn" onclick="startInstall()">
            ⚙️ &nbsp;Run Installation Now
        </button>
    </div>

    {{-- ── RUNNING STATE ────────────────────────────────────────────────── --}}
    <div id="stateRunning" style="display:none;">
        <div style="text-align:center; margin-bottom:20px;">
            <div style="
                width:50px; height:50px;
                border:5px solid #e2e8f0;
                border-top-color:#667eea;
                border-radius:50%;
                animation:spin 0.8s linear infinite;
                margin:0 auto 16px;
            "></div>
            <h2 style="font-size:17px; font-weight:700; color:#1e293b; margin-bottom:4px;">
                Installing…
            </h2>
            <p style="color:#64748b; font-size:12px;">
                Do <strong>not</strong> close this window.
            </p>
        </div>
        <div id="runLog" style="
            background:#0f172a; color:#94a3b8;
            border-radius:8px; padding:14px 16px;
            font-family:monospace; font-size:12px;
            line-height:1.7; max-height:200px; overflow-y:auto;
        "></div>
    </div>

    {{-- ── ERROR STATE ──────────────────────────────────────────────────── --}}
    <div id="stateError" style="display:none; text-align:center; padding:12px 0;">
        <div style="font-size:40px; margin-bottom:10px;">❌</div>
        <h2 style="font-size:16px; font-weight:700; color:#ef4444; margin-bottom:8px;">
            Installation Failed
        </h2>
        <div id="errorMsg" style="
            background:#fef2f2; border:1px solid #fecaca;
            border-radius:8px; padding:12px 16px;
            color:#991b1b; font-size:12px; text-align:left;
            margin-bottom:16px; font-family:monospace; word-break:break-all;
        "></div>
        <button class="btn" onclick="resetToForm()" style="width:auto; padding:11px 28px;">
            ← Try Again
        </button>
    </div>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        .alert-error {
            background:#fef2f2; border-left:4px solid #ef4444;
            color:#991b1b; border-radius:6px;
            padding:12px 16px; margin-bottom:20px; font-size:14px;
        }
    </style>

    <script>
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
        let pollTimer = null;

        function startInstall() {
            const name  = document.getElementById('admin_name').value.trim();
            const email = document.getElementById('admin_email').value.trim();
            const pass  = document.getElementById('admin_password').value;
            const errEl = document.getElementById('formError');

            // Validate
            if (!name || !email || !pass) {
                errEl.textContent = 'All fields are required.';
                errEl.style.display = 'block';
                return;
            }
            const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRe.test(email)) {
                errEl.textContent = 'Please enter a valid email address.';
                errEl.style.display = 'block';
                return;
            }
            if (pass.length < 8) {
                errEl.textContent = 'Password must be at least 8 characters.';
                errEl.style.display = 'block';
                return;
            }
            errEl.style.display = 'none';

            // Switch to running state
            show('stateRunning');

            fetch('/install/finalize/run', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    admin_name:     name,
                    admin_email:    email,
                    admin_password: pass,
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.started) {
                    pollTimer = setInterval(pollStatus, 1500);
                } else {
                    showError(data.error || 'Failed to start installation.');
                }
            })
            .catch(err => showError(err.message));
        }

        function pollStatus() {
            fetch('/install/finalize/status', {
                headers: { Accept: 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                const logEl = document.getElementById('runLog');
                if (data.log && data.log.length) {
                    logEl.innerHTML = data.log.map(esc).join('<br>');
                    logEl.scrollTop = logEl.scrollHeight;
                }
                if (data.status === 'done') {
                    clearInterval(pollTimer);
                    window.location.href = data.redirect || '/install/complete';
                } else if (data.status === 'failed') {
                    clearInterval(pollTimer);
                    showError(data.error || 'Installation failed. Check server logs.');
                }
            })
            .catch(() => {});
        }

        function showError(msg) {
            clearInterval(pollTimer);
            document.getElementById('errorMsg').textContent = msg;
            show('stateError');
        }

        function resetToForm() {
            show('stateForm');
        }

        function show(id) {
            ['stateForm','stateRunning','stateError'].forEach(s => {
                document.getElementById(s).style.display = s === id ? 'block' : 'none';
            });
        }

        function esc(s) {
            return String(s)
                .replace(/&/g,'&amp;')
                .replace(/</g,'&lt;')
                .replace(/>/g,'&gt;');
        }
    </script>
@endsection
