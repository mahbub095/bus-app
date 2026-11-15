@extends('install.layout')

@section('title', 'Installation Complete')
@section('subtitle', 'Your system is ready!')

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
        <div class="progress-step completed active">
            <div class="progress-step-circle">✓</div>
            <div class="progress-step-label">Done!</div>
        </div>
    </div>

    {{-- Success message --}}
    <div style="text-align: center; padding: 20px 0 16px;">
        <div style="font-size: 56px; margin-bottom: 14px;">🎉</div>
        <h2 style="font-size: 22px; font-weight: 700; color: #10b981; margin-bottom: 10px;">
            Installation Successful!
        </h2>
        <p style="color: #555; font-size: 14px; line-height: 1.7; margin-bottom: 24px;">
            SonyaBus is installed and your license is activated.<br>
            You can now log in to the admin dashboard.
        </p>
        <a href="/admin/login" style="
            display: inline-block;
            padding: 13px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
        ">Go to Admin Dashboard →</a>
    </div>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">

    {{-- Demo Data Section --}}
    <div id="demoSection">
        <h3 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 8px;">
            🗂️ Import Demo Data (Optional)
        </h3>
        <p style="color: #64748b; font-size: 13px; line-height: 1.6; margin-bottom: 16px;">
            Populates the database with sample stations, routes, schedules, buses, and bookings so you can explore the system immediately.
            <strong>Do not use on a live production site.</strong>
        </p>

        {{-- Idle state --}}
        <div id="demoIdle">
            <button onclick="startDemoImport()" class="btn" style="width: auto; padding: 11px 28px; font-size: 14px;">
                📦 Import Demo Data
            </button>
        </div>

        {{-- Running state --}}
        <div id="demoRunning" style="display:none;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="
                    width: 22px; height: 22px; flex-shrink: 0;
                    border: 3px solid #e2e8f0;
                    border-top-color: #667eea;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                "></div>
                <span style="font-weight: 600; color: #667eea; font-size: 14px;">Importing demo data...</span>
            </div>
            <div id="demoLog" style="
                background: #0f172a;
                color: #94a3b8;
                border-radius: 8px;
                padding: 14px 16px;
                font-family: monospace;
                font-size: 12px;
                line-height: 1.7;
                max-height: 200px;
                overflow-y: auto;
            "></div>
        </div>

        {{-- Done state --}}
        <div id="demoDone" style="display:none;">
            <div style="
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: 8px;
                padding: 14px 16px;
                color: #166534;
                font-size: 14px;
                font-weight: 600;
            ">✅ Demo data imported successfully!</div>
        </div>

        {{-- Error state --}}
        <div id="demoError" style="display:none;">
            <div style="
                background: #fef2f2;
                border: 1px solid #fecaca;
                border-radius: 8px;
                padding: 14px 16px;
                color: #991b1b;
                font-size: 13px;
            ">
                <strong>Import failed.</strong>
                <span id="demoErrorMsg"></span>
                <br><br>
                <button onclick="startDemoImport()" style="
                    padding: 8px 20px;
                    background: #ef4444;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 13px;
                ">Retry</button>
            </div>
        </div>
    </div>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>

    <script>
        let pollTimer = null;

        function startDemoImport() {
            document.getElementById('demoIdle').style.display = 'none';
            document.getElementById('demoRunning').style.display = 'block';
            document.getElementById('demoDone').style.display = 'none';
            document.getElementById('demoError').style.display = 'none';

            // Get CSRF token from meta tag
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            fetch('/install/demo-seed/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({})
            })
            .then(r => r.json())
            .then(data => {
                if (data.started) {
                    // Start polling
                    pollTimer = setInterval(pollStatus, 1500);
                } else {
                    showError('Failed to start demo import process.');
                }
            })
            .catch(err => showError(err.message));
        }

        function pollStatus() {
            fetch('/install/demo-seed/status', { headers: { Accept: 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    // Update log
                    const logEl = document.getElementById('demoLog');
                    if (data.log && data.log.length) {
                        logEl.innerHTML = data.log.map(l => escapeHtml(l)).join('<br>');
                        logEl.scrollTop = logEl.scrollHeight;
                    }

                    if (data.status === 'done') {
                        clearInterval(pollTimer);
                        document.getElementById('demoRunning').style.display = 'none';
                        document.getElementById('demoDone').style.display = 'block';
                    } else if (data.status === 'failed') {
                        clearInterval(pollTimer);
                        showError(data.error || 'Unknown error');
                    }
                })
                .catch(() => {}); // ignore transient fetch errors
        }

        function showError(msg) {
            clearInterval(pollTimer);
            document.getElementById('demoRunning').style.display = 'none';
            document.getElementById('demoError').style.display = 'block';
            document.getElementById('demoErrorMsg').textContent = ' ' + msg;
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    </script>
@endsection
