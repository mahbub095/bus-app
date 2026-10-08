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

    {{-- Intro message --}}
    <div style="text-align:center; margin-bottom:28px;">
        <div style="font-size:42px; margin-bottom:10px;">🚀</div>
        <h2 style="font-size:20px; font-weight:700; color:#1e293b; margin-bottom:6px;">
            Please wait — installation in progress...
        </h2>
        <p style="color:#64748b; font-size:14px; line-height:1.6;">
            This will take around 15-30 seconds. Please do <strong>not</strong> close this window or refresh the page.
        </p>
    </div>

    {{-- Overall progress bar --}}
    <div style="margin-bottom:26px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span id="overallProgressLabel" style="font-size:13px; color:#475569; font-weight:600;">
                Initializing...
            </span>
            <span id="overallProgressPct" style="font-size:13px; color:#667eea; font-weight:700;">0%</span>
        </div>
        <div style="width:100%; height:8px; background:#e2e8f0; border-radius:999px; overflow:hidden;">
            <div id="overallProgressBar" style="height:100%; width:0%; background:linear-gradient(90deg,#667eea 0%,#8b5cf6 100%); border-radius:999px; transition:width .45s ease;"></div>
        </div>
    </div>

    {{-- Task status cards --}}
    <div id="taskList" style="display:flex; flex-direction:column; gap:8px; margin-bottom:28px;">
        <div class="task-item" data-key="config" data-index="0">
            <div class="task-icon pending" id="taskIcon-0">◷</div>
            <div class="task-body">
                <div class="task-title">Reload Application Config</div>
                <div class="task-desc">Loading new DB credentials from .env...</div>
            </div>
        </div>
        <div class="task-item" data-key="migrate" data-index="1">
            <div class="task-icon pending" id="taskIcon-1">◷</div>
            <div class="task-body">
                <div class="task-title">Run Database Migrations</div>
                <div class="task-desc">Creating all required tables...</div>
            </div>
        </div>
        <div class="task-item" data-key="license" data-index="2">
            <div class="task-icon pending" id="taskIcon-2">◷</div>
            <div class="task-body">
                <div class="task-title">Persist Envato License</div>
                <div class="task-desc">Saving verified purchase code to DB...</div>
            </div>
        </div>
        <div class="task-item" data-key="seed" data-index="3">
            <div class="task-icon pending" id="taskIcon-3">◷</div>
            <div class="task-body">
                <div class="task-title">Run Database Seeders</div>
                <div class="task-desc">Inserting default demo data...</div>
            </div>
        </div>
        <div class="task-item" data-key="admin" data-index="4">
            <div class="task-icon pending" id="taskIcon-4">◷</div>
            <div class="task-body">
                <div class="task-title">Create Super Admin Account</div>
                <div class="task-desc">role = super_admin • all menu permissions = ON</div>
            </div>
        </div>
        <div class="task-item" data-key="lock" data-index="5">
            <div class="task-icon pending" id="taskIcon-5">◷</div>
            <div class="task-body">
                <div class="task-title">Lock Installer Wizard</div>
                <div class="task-desc">APP_INSTALLED=true + lock file marker</div>
            </div>
        </div>
        <div class="task-item" data-key="auth" data-index="6">
            <div class="task-icon pending" id="taskIcon-6">◷</div>
            <div class="task-body">
                <div class="task-title">Sign In + Redirect</div>
                <div class="task-desc">Auto-login super admin to dashboard...</div>
            </div>
        </div>
    </div>

    {{-- Live log terminal --}}
    <div id="logWrapper" style="margin-bottom:24px;">
        <button type="button" id="toggleLogBtn" style="background:none; border:none; cursor:pointer; padding:6px 0; color:#667eea; font-size:12px; font-weight:600; margin-bottom:8px;">
            ▸ Show install log
        </button>
        <div id="logBox" style="display:none; background:#0f172a; color:#cbd5e1; border-radius:10px; padding:12px 14px; font-family:'Fira Code', Consolas, monospace; font-size:11.5px; line-height:1.7; max-height:200px; overflow-y:auto; text-align:left;">
            <div style="color:#64748b;">// Waiting for AJAX POST to /install/finalize/run...</div>
        </div>
    </div>

    {{-- Success banner (hidden initially) --}}
    <div id="successBox" style="display:none; background:#ecfdf5; border:1px solid #6ee7b7; border-radius:12px; padding:18px 18px; text-align:center; margin-bottom:20px;">
        <div style="font-size:36px; margin-bottom:6px;">🎉</div>
        <h3 id="successTitle" style="font-size:18px; font-weight:700; color:#065f46; margin-bottom:4px;">
            Installation Successful!
        </h3>
        <p id="successSubtitle" style="color:#047857; font-size:13px; line-height:1.6; margin:0;">
            Redirecting you to the admin dashboard...
        </p>
    </div>

    {{-- Error banner (hidden initially) --}}
    <div id="errorBox" style="display:none; background:#fef2f2; border:1px solid #fca5a5; border-radius:12px; padding:18px 18px; text-align:left; margin-bottom:20px;">
        <div style="display:flex; gap:12px; align-items:flex-start;">
            <div style="font-size:28px; flex-shrink:0;">⚠️</div>
            <div style="flex:1;">
                <h3 style="font-size:16px; font-weight:700; color:#991b1b; margin-bottom:6px;">
                    Installation Failed
                </h3>
                <p id="errorMessage" style="color:#b91c1c; font-size:13px; line-height:1.6; margin-bottom:8px;">
                    An unknown error occurred.
                </p>
                <button type="button" id="retryBtn" class="btn" style="width:auto; padding:8px 20px; font-size:13px; background:#dc2626;">
                    ↻ Retry Installation
                </button>
            </div>
        </div>
    </div>

    <style>
        .task-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: all .3s ease;
        }
        .task-item.active {
            background: #eef2ff;
            border-color: #c7d2fe;
            box-shadow: 0 2px 10px rgba(102,126,234,.1);
        }
        .task-item.done {
            background: #ecfdf5;
            border-color: #a7f3d0;
            opacity: .9;
        }
        .task-item.failed {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .task-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }
        .task-icon.pending {
            background: #e2e8f0;
            color: #94a3b8;
            animation: pulse 1.4s ease-in-out infinite;
        }
        .task-icon.running {
            background: linear-gradient(135deg,#667eea,#8b5cf6);
            color: #fff;
            animation: spin 1.1s linear infinite;
        }
        .task-icon.success {
            background: #10b981;
            color: #fff;
        }
        .task-icon.failed {
            background: #ef4444;
            color: #fff;
        }
        .task-title {
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.4;
        }
        .task-item.active .task-title { color: #4338ca; }
        .task-item.done   .task-title { color: #065f46; }
        .task-item.failed .task-title { color: #991b1b; }
        .task-desc {
            font-size: 11.5px;
            color: #64748b;
            line-height: 1.4;
            margin-top: 1px;
        }
        @keyframes pulse {
            0%,100% { opacity: 1 }
            50%     { opacity: .45 }
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        #overallProgressBar { transition: width .45s cubic-bezier(.4,0,.2,1); }
    </style>

    <script>
    (function () {
        const TASKS = [
            { key: 'config',  match: ['✅ Application config reloaded','config'] },
            { key: 'migrate', match: ['migrations','migration'] },
            { key: 'license', match: ['license'] },
            { key: 'seed',    match: ['seeders','seed'] },
            { key: 'admin',   match: ['SUPER ADMIN','super_admin','admin'] },
            { key: 'lock',    match: ['Installer locked','APP_INSTALLED','lock'] },
            { key: 'auth',    match: ['Authenticated','Auth','dashboard','Redirecting'] },
        ];

        const csrfToken = '{{ csrf_token() }}';
        const runUrl    = '/install/finalize/run';

        const bar     = document.getElementById('overallProgressBar');
        const pct     = document.getElementById('overallProgressPct');
        const label   = document.getElementById('overallProgressLabel');
        const logBox  = document.getElementById('logBox');
        const togBtn  = document.getElementById('toggleLogBtn');
        const succBox = document.getElementById('successBox');
        const errBox  = document.getElementById('errorBox');
        const errMsg  = document.getElementById('errorMessage');
        const retry   = document.getElementById('retryBtn');

        togBtn.addEventListener('click', () => {
            if (logBox.style.display === 'none') {
                logBox.style.display = 'block';
                togBtn.textContent = '▾ Hide install log';
            } else {
                logBox.style.display = 'none';
                togBtn.textContent = '▸ Show install log';
            }
        });

        function setTaskState(idx, state, descText) {
            const icon = document.getElementById('taskIcon-' + idx);
            const taskWrap = icon.closest('.task-item');
            icon.className = 'task-icon ' + state;
            taskWrap.classList.remove('active','done','failed');
            if (state === 'running') {
                taskWrap.classList.add('active');
                icon.textContent = '◌';
            } else if (state === 'success') {
                taskWrap.classList.add('done');
                icon.textContent = '✓';
            } else if (state === 'failed') {
                taskWrap.classList.add('failed');
                icon.textContent = '✕';
            } else {
                icon.textContent = '◷';
            }
            if (descText) {
                const dd = taskWrap.querySelector('.task-desc');
                if (dd) dd.textContent = descText;
            }
        }

        function setProgress(p, labelText) {
            const value = Math.min(100, Math.max(0, p));
            bar.style.width = value + '%';
            pct.textContent = Math.round(value) + '%';
            if (labelText) label.textContent = labelText;
        }

        function appendLog(lines) {
            if (!Array.isArray(lines)) lines = [String(lines)];
            lines.forEach(function (l) {
                if (!l && l !== '') return;
                const div = document.createElement('div');
                div.textContent = l;
                logBox.appendChild(div);
            });
            logBox.scrollTop = logBox.scrollHeight;
            // auto-open log box only on errors
        }

        // Map backend log line → task index completion
        function applyLogToTasks(log) {
            const normalized = log.join('\n').toLowerCase();
            // task 0 (config) -> always first, done right away
            setTaskState(0, 'success', 'Config reloaded with new .env values.');
            TASKS.forEach(function (task, i) {
                const icon = document.getElementById('taskIcon-' + i);
                // Skip if already marked success
                if (icon.classList.contains('success')) return;
                let matched = false;
                task.match.forEach(function (m) {
                    if (normalized.includes(m.toLowerCase())) matched = true;
                });
                if (matched && i !== 6) {
                    // task 6 = auth+redirect — set only on final redirect_url
                    setTaskState(i, 'success');
                }
            });
        }

        function runInstall() {
            succBox.style.display = 'none';
            errBox.style.display  = 'none';
            // Reset tasks
            for (let i = 0; i < TASKS.length; i++) setTaskState(i, 'pending');

            // Kick off with task 0 → running state, progress 5%
            setTaskState(0, 'running', 'Calling Artisan::config:clear...');
            setProgress(5, 'Step 1/7 · Reloading config');

            // Simulated step-by-step progress (so UI feels alive even while a
            // long-running migrate query is blocking the HTTP response).
            const stagedProgress = [
                { pct: 15, idx: 0, lbl: 'Step 1/7 · Config reloaded → starting migrations' },
                { pct: 30, idx: 1, lbl: 'Step 2/7 · Running database migrations...' },
                { pct: 45, idx: 2, lbl: 'Step 3/7 · Persisting license to database...' },
                { pct: 60, idx: 3, lbl: 'Step 4/7 · Running seeders (demo data)...' },
                { pct: 75, idx: 4, lbl: 'Step 5/7 · Creating SUPER ADMIN user...' },
                { pct: 85, idx: 5, lbl: 'Step 6/7 · Locking installer wizard...' },
                { pct: 92, idx: 6, lbl: 'Step 7/7 · Auto-login + redirecting...' },
            ];
            let stage = 0;
            const interval = setInterval(() => {
                if (stage >= stagedProgress.length) { clearInterval(interval); return; }
                const s = stagedProgress[stage++];
                // Only apply if not already moved forward by real response
                const current = parseFloat(bar.style.width || '0');
                if (current < s.pct) {
                    // Mark current task running for animation
                    setTaskState(s.idx, 'running');
                    setProgress(s.pct, s.lbl);
                }
            }, 650);

            fetch(runUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: new URLSearchParams({ _token: csrfToken }).toString(),
            })
            .then(function (resp) {
                clearInterval(interval);
                return resp.text().then(function (raw) {
                    let data = null;
                    try { data = JSON.parse(raw); } catch (_) { data = null; }
                    if (!data) {
                        appendLog([
                            '❌ Server returned non-JSON response (status ' + resp.status + '):',
                            raw.substring(0, 800)
                        ]);
                        errBox.style.display = 'block';
                        errMsg.textContent = 'Unexpected server response. Please show install log and report this.';
                        togBtn.click(); // open log
                        return;
                    }
                    if (Array.isArray(data.log)) {
                        appendLog(data.log);
                        applyLogToTasks(data.log);
                    }
                    if (data.success === true) {
                        // All tasks done
                        TASKS.forEach(function (_, i) { setTaskState(i, 'success'); });
                        // task 6 last-minute update with admin info
                        if (data.admin_email) {
                            setTaskState(6, 'success', 'Signed in as ' + data.admin_email + ' — remember_me = ON.');
                        }
                        setProgress(100, 'Done! Redirecting...');
                        succBox.style.display = 'block';
                        if (data.admin_name) {
                            document.getElementById('successTitle').textContent =
                                'Welcome, ' + data.admin_name + '! 🎉';
                        }
                        document.getElementById('successSubtitle').innerHTML =
                            'You are now signed in as <strong>' +
                            (data.admin_email || 'Super Admin') +
                            '</strong> (role = <code>super_admin</code>). Redirecting to the dashboard in <span id="countdown">3</span> seconds...';
                        let n = 3;
                        const cd = document.getElementById('countdown');
                        const tick = setInterval(() => {
                            n--;
                            if (cd) cd.textContent = String(Math.max(n, 0));
                            if (n <= 0) { clearInterval(tick); }
                        }, 1000);
                        setTimeout(function () {
                            window.location.href = data.redirect_url || '/admin';
                        }, 3000);
                    } else {
                        // Failure
                        // Mark any remaining tasks as failed; open log
                        for (let i = 0; i < TASKS.length; i++) {
                            const ic = document.getElementById('taskIcon-' + i);
                            if (!ic.classList.contains('success')) {
                                setTaskState(i, 'failed');
                            }
                        }
                        errBox.style.display = 'block';
                        errMsg.textContent =
                            (data.error || 'Unknown error') +
                            (data.exception ? ' [' + data.exception + ']' : '');
                        togBtn.click();
                    }
                });
            })
            .catch(function (err) {
                clearInterval(interval);
                appendLog(['❌ Network / fetch error: ' + (err && err.message ? err.message : String(err))]);
                errBox.style.display = 'block';
                errMsg.textContent = 'Network error: Could not reach /install/finalize/run. Please check PHP error logs and retry.';
                togBtn.click();
            });
        }

        retry.addEventListener('click', runInstall);

        // Auto-run install 600ms after page paint
        setTimeout(runInstall, 600);
    })();
    </script>
@endsection
