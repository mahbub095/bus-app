@extends('install.layout')

@section('title', 'Finalizing Installation')
@section('subtitle', 'Step 4 of 4 — Setting up your system...')

@section('content')
    {{-- Progress Steps --}}
    <div class="progress-steps">
        <div class="progress-step completed">
            <div class="progress-step-circle">✓</div>
            <div class="progress-step-label">License</div>
        </div>
        <div class="progress-step completed">
            <div class="progress-step-circle">✓</div>
            <div class="progress-step-label">Database</div>
        </div>
        <div class="progress-step completed">
            <div class="progress-step-circle">✓</div>
            <div class="progress-step-label">Admin</div>
        </div>
        <div class="progress-step active">
            <div class="progress-step-circle">4</div>
            <div class="progress-step-label">Finish</div>
        </div>
    </div>

    {{-- ── WAITING STATE (before form auto-submits) ───────────────────── --}}
    <div id="stateWaiting" style="{{ request('running') ? 'display:none' : '' }}">
        <div style="text-align:center; padding:10px 0 28px;">
            <div style="font-size:48px; margin-bottom:12px;">🚀</div>
            <h2 style="font-size:18px; font-weight:700; color:#1e293b; margin-bottom:8px;">
                Ready to finalize installation
            </h2>
            <p style="color:#64748b; font-size:13px; margin-bottom:20px;">
                Click the button below to run migrations, seeders, and create your admin account.
            </p>
            <form method="POST" action="/install/finalize/run">
                @csrf
                <button type="submit" class="btn">⚙️ &nbsp;Run Installation Now</button>
            </form>
        </div>
    </div>

    {{-- ── RUNNING STATE (polling) ─────────────────────────────────────── --}}
    <div id="stateRunning" style="{{ request('running') ? '' : 'display:none' }}">
        <div style="text-align:center; padding:6px 0 18px;">
            <div id="statusIcon" style="font-size:44px; margin-bottom:10px;">⚙️</div>
            <h2 id="statusTitle" style="font-size:17px; font-weight:700; color:#1e293b; margin-bottom:6px;">
                Installation in progress...
            </h2>
            <p style="color:#64748b; font-size:12px; margin-bottom:18px;">
                Do <strong>not</strong> close or refresh this window.
            </p>

            {{-- Spinner --}}
            <div id="spinner" style="display:flex; justify-content:center; margin-bottom:18px;">
                <div style="
                    width:44px; height:44px;
                    border:5px solid #e2e8f0;
                    border-top-color:#667eea;
                    border-radius:50%;
                    animation:spin 0.9s linear infinite;
                "></div>
            </div>

            {{-- Steps list --}}
            <div id="stepsList" style="text-align:left; max-width:320px; margin:0 auto 18px; display:flex; flex-direction:column; gap:7px;">
                @foreach([
                    ['key'=>'connect',  'label'=>'Connect to database'],
                    ['key'=>'migrate',  'label'=>'Run database migrations'],
                    ['key'=>'license',  'label'=>'Persist license record'],
                    ['key'=>'seed',     'label'=>'Run database seeders'],
                    ['key'=>'admin',    'label'=>'Create super admin account'],
                    ['key'=>'lock',     'label'=>'Lock installer & clear caches'],
                ] as $step)
                    <div id="step-{{ $step['key'] }}" style="display:flex; align-items:center; gap:10px; font-size:13px; color:#475569;">
                        <span class="step-icon" style="font-size:15px; width:18px; text-align:center;">◷</span>
                        <span>{{ $step['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Log terminal --}}
        <details id="logDetails" style="margin-bottom:16px;">
            <summary style="cursor:pointer; font-size:12px; color:#667eea; font-weight:600; padding:4px 0;">
                ▸ Show install log
            </summary>
            <div id="logBox" style="
                margin-top:8px;
                background:#0f172a; color:#94a3b8;
                border-radius:8px; padding:10px 12px;
                font-family:Consolas,monospace; font-size:11px; line-height:1.7;
                max-height:180px; overflow-y:auto; text-align:left;
            ">
                <div style="color:#475569;">// Waiting for background process...</div>
            </div>
        </details>

        {{-- Success banner --}}
        <div id="bannerSuccess" style="display:none; background:#ecfdf5; border:1px solid #6ee7b7; border-radius:10px; padding:16px; text-align:center; margin-bottom:12px;">
            <div style="font-size:36px; margin-bottom:6px;">🎉</div>
            <h3 id="successTitle" style="font-size:17px; font-weight:700; color:#065f46; margin-bottom:4px;">Installation Complete!</h3>
            <p id="successMsg" style="color:#047857; font-size:13px; margin-bottom:12px;"></p>
            <a id="dashboardLink" href="/admin" class="btn" style="display:inline-block; width:auto; padding:10px 32px; text-decoration:none;">
                Go to Admin Dashboard →
            </a>
        </div>

        {{-- Error banner --}}
        <div id="bannerError" style="display:none; background:#fef2f2; border:1px solid #fca5a5; border-radius:10px; padding:16px; margin-bottom:12px;">
            <h3 style="font-size:15px; font-weight:700; color:#991b1b; margin-bottom:6px;">⚠️ Installation Failed</h3>
            <p id="errorMsg" style="color:#b91c1c; font-size:13px; margin-bottom:10px;"></p>
            <button onclick="location.href='/install/finalize'" class="btn" style="width:auto; padding:8px 20px; font-size:13px; background:#dc2626;">
                ↻ Try Again
            </button>
        </div>
    </div>

    <style>
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>

    <script>
    (function () {
        const isRunning = {{ request('running') ? 'true' : 'false' }};
        if (!isRunning) return; // Form not submitted yet — nothing to poll

        const STATUS_URL  = '/install/finalize/status';
        const POLL_MS     = 2000;   // poll every 2 seconds
        const MAX_POLLS   = 150;    // 5 minutes max
        let   pollCount   = 0;
        let   lastLogLen  = 0;

        // Step keyword → DOM id mapping
        const STEP_KEYWORDS = {
            connect : ['Database connection'],
            migrate : ['migration', 'migrate'],
            license : ['license', 'License'],
            seed    : ['seed', 'Seed'],
            admin   : ['SUPER ADMIN', 'super admin', 'admin'],
            lock    : ['Locked', 'locked', 'APP_INSTALLED', 'cache'],
        };

        function appendLog(lines) {
            const box = document.getElementById('logBox');
            lines.forEach(function(l) {
                const d = document.createElement('div');
                d.textContent = l;
                if (l.startsWith('✅')) d.style.color = '#6ee7b7';
                else if (l.startsWith('❌')) d.style.color = '#fca5a5';
                else if (l.startsWith('⚠️')) d.style.color = '#fcd34d';
                else if (l.startsWith('⏳')) d.style.color = '#93c5fd';
                box.appendChild(d);
            });
            box.scrollTop = box.scrollHeight;
        }

        function markStep(key, state) {
            const el = document.getElementById('step-' + key);
            if (!el) return;
            const icon = el.querySelector('.step-icon');
            if (state === 'done') {
                icon.textContent = '✓';
                icon.style.color = '#10b981';
                el.style.color   = '#065f46';
            } else if (state === 'running') {
                icon.textContent = '◌';
                icon.style.color = '#667eea';
            }
        }

        function applyLogToSteps(log) {
            const text = log.join('\n');
            Object.keys(STEP_KEYWORDS).forEach(function(key) {
                STEP_KEYWORDS[key].forEach(function(kw) {
                    if (text.includes(kw)) markStep(key, 'done');
                });
            });
        }

        function poll() {
            if (pollCount++ > MAX_POLLS) {
                showError('Timed out waiting for install to complete. Check PHP logs.');
                return;
            }

            fetch(STATUS_URL, { cache: 'no-store' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    // Append new log lines
                    const log = data.log || [];
                    if (log.length > lastLogLen) {
                        appendLog(log.slice(lastLogLen));
                        lastLogLen = log.length;
                        applyLogToSteps(log);
                    }

                    if (data.status === 'done') {
                        // Mark all steps done
                        Object.keys(STEP_KEYWORDS).forEach(function(k) { markStep(k, 'done'); });
                        document.getElementById('spinner').style.display = 'none';
                        document.getElementById('statusTitle').textContent = 'Installation complete!';
                        document.getElementById('statusIcon').textContent  = '🎉';

                        const title = data.admin_name
                            ? 'Welcome, ' + data.admin_name + '! 🎉'
                            : 'Installation Successful!';
                        document.getElementById('successTitle').textContent = title;
                        document.getElementById('successMsg').innerHTML =
                            'Signed in as <strong>' + (data.admin_email || 'admin') + '</strong>. ' +
                            'Redirecting in <span id="countdown">3</span>s...';
                        document.getElementById('bannerSuccess').style.display = 'block';

                        // auto-redirect countdown
                        let n = 3;
                        const tick = setInterval(function() {
                            n--;
                            const cd = document.getElementById('countdown');
                            if (cd) cd.textContent = Math.max(n, 0);
                            if (n <= 0) {
                                clearInterval(tick);
                                window.location.href = '/admin/login';
                            }
                        }, 1000);

                    } else if (data.status === 'failed') {
                        showError(data.error || 'Unknown error. Open install log for details.');
                    } else {
                        // still running — keep polling
                        setTimeout(poll, POLL_MS);
                    }
                })
                .catch(function(err) {
                    // Network blip — keep polling, don't give up yet
                    setTimeout(poll, POLL_MS * 2);
                });
        }

        function showError(msg) {
            document.getElementById('spinner').style.display = 'none';
            document.getElementById('bannerError').style.display = 'block';
            document.getElementById('errorMsg').textContent = msg;
            // Open log automatically on error
            document.getElementById('logDetails').open = true;
        }

        // Start polling after a short delay to let the background process boot
        setTimeout(poll, 1500);
    })();
    </script>
@endsection
