@extends('install.layout')

@section('title', 'Database Configuration')
@section('subtitle', 'Step 2 of 3 — Configure your database')

@section('content')

    {{-- 3-Step Progress Bar --}}
    <div class="progress-steps">
        <div class="progress-step completed">
            <div class="progress-step-circle">✓</div>
            <div class="progress-step-label">License</div>
        </div>
        <div class="progress-step active">
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
        <form id="dbForm" method="POST" action="/install/database/save" enctype="multipart/form-data">
            @csrf

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host"
                           value="{{ old('db_host', '127.0.0.1') }}" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="db_port">Port</label>
                    <input type="number" id="db_port" name="db_port"
                           value="{{ old('db_port', '3306') }}" min="1" max="65535" required>
                </div>
            </div>

            <div class="form-group" style="margin-top: 16px;">
                <label for="db_database">Database Name</label>
                <input type="text" id="db_database" name="db_database"
                       value="{{ old('db_database', 'sonyabus') }}" required>
            </div>

            <div class="form-group">
                <label for="db_username">Database Username</label>
                <input type="text" id="db_username" name="db_username"
                       value="{{ old('db_username', 'root') }}" required>
            </div>

            <div class="form-group">
                <label for="db_password">Database Password</label>
                <input type="password" id="db_password" name="db_password"
                       placeholder="Leave empty if no password">
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; justify-content: space-between;">
                    <span>SQL File <span style="font-weight:normal; color:#888;">(Optional)</span></span>
                    <span style="font-size: 12px; font-weight: normal; color: #888;">.sql, max 200MB</span>
                </label>
                <div style="border: 2px dashed #ccc; border-radius: 8px; padding: 20px; text-align: center;
                            cursor: pointer; transition: border-color 0.2s, background 0.2s;"
                     onmouseover="this.style.borderColor='#667eea'; this.style.background='#eef2ff';"
                     onmouseout="this.style.borderColor='#ccc'; this.style.background='transparent';"
                     onclick="document.getElementById('sql_file').click()">
                    <div style="font-size: 28px; margin-bottom: 8px;">📄</div>
                    <div id="sql_file_name" style="color: #555; font-size: 13px;">
                        Click to upload .sql dump, or leave empty for fresh install
                    </div>
                    <input type="file" id="sql_file" name="sql_file" accept=".sql" style="display:none;"
                        onchange="document.getElementById('sql_file_name').textContent =
                            this.files[0]
                                ? this.files[0].name + ' (' + (this.files[0].size/1024/1024).toFixed(2) + ' MB)'
                                : 'Click to upload .sql dump, or leave empty for fresh install';">
                </div>
                <p class="form-help" style="margin-top:8px; font-size:12px; color:#888;">
                    Upload an existing SQL dump to import. Migrations will be skipped.
                </p>
            </div>

            <button type="button" class="btn" id="dbBtn" onclick="submitDatabase()">
                Test Connection &amp; Continue →
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
            Testing database connection…
        </h2>
        <p style="color: #64748b; font-size: 13px;">
            Verifying credentials and writing configuration.
        </p>
    </div>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        .alert-error {
            background: #fef2f2; border-left: 4px solid #ef4444;
            color: #991b1b; border-radius: 6px;
            padding: 12px 16px; margin-bottom: 20px; font-size: 14px;
        }
        .alert-success {
            background: #f0fdf4; border-left: 4px solid #10b981;
            color: #065f46; border-radius: 6px;
            padding: 12px 16px; margin-bottom: 20px; font-size: 14px;
        }
        .form-help { font-size: 13px; color: #666; margin-top: 6px; line-height: 1.5; }
        .form-help a { color: #667eea; text-decoration: none; }
    </style>

    <script>
        function submitDatabase() {
            const host = document.getElementById('db_host').value.trim();
            const db   = document.getElementById('db_database').value.trim();
            const user = document.getElementById('db_username').value.trim();

            if (!host || !db || !user) {
                alert('Host, database name, and username are required.');
                return;
            }

            // Show loader then submit the real form
            document.getElementById('stateForm').style.display    = 'none';
            document.getElementById('stateLoading').style.display = 'block';

            document.getElementById('dbForm').submit();
        }
    </script>
@endsection
