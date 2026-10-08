@extends('install.layout')

@section('title', 'Database Configuration')
@section('subtitle', 'Step 2 of 4 — Configure your database')

@section('content')
    {{-- Progress Steps --}}
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
            <div class="progress-step-label">Admin</div>
        </div>
        <div class="progress-step">
            <div class="progress-step-circle">4</div>
            <div class="progress-step-label">Finish</div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="/install/database/save" enctype="multipart/form-data">
        @csrf

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="db_host">Database Host</label>
                <input type="text" id="db_host" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="db_port">Port</label>
                <input type="number" id="db_port" name="db_port" value="{{ old('db_port', '3306') }}" min="1" max="65535" required>
            </div>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label for="db_database">Database Name</label>
            <input type="text" id="db_database" name="db_database" value="{{ old('db_database', 'sonyabus') }}" required>
        </div>

        <div class="form-group">
            <label for="db_username">Database Username</label>
            <input type="text" id="db_username" name="db_username" value="{{ old('db_username', 'root') }}" required>
        </div>

        <div class="form-group">
            <label for="db_password">Database Password</label>
            <input type="password" id="db_password" name="db_password" placeholder="Leave empty if no password">
        </div>

        <div class="form-group">
            <label for="sql_file" style="display: flex; align-items: center; justify-content: space-between;">
                <span>SQL File (Optional)</span>
                <span style="font-size: 12px; font-weight: normal; color: #888;">.sql, max 200MB</span>
            </label>
            <div style="border: 2px dashed #ccc; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: border-color 0.2s, background 0.2s;"
                 onmouseover="this.style.borderColor='#4f46e5'; this.style.background='#eef2ff';"
                 onmouseout="this.style.borderColor='#ccc'; this.style.background='transparent';"
                 onclick="document.getElementById('sql_file').click()">
                <div style="font-size: 32px; margin-bottom: 8px;">📄</div>
                <div id="sql_file_name" style="color: #555; font-size: 14px;">
                    Click to upload .sql dump or leave empty for fresh install
                </div>
                <input type="file" id="sql_file" name="sql_file" accept=".sql" style="display: none;"
                    onchange="document.getElementById('sql_file_name').textContent = this.files[0] ? this.files[0].name + ' (' + (this.files[0].size/1024/1024).toFixed(2) + ' MB)' : 'Click to upload .sql dump or leave empty for fresh install';">
            </div>
            <p class="form-help">
                Upload a pre-existing database dump (e.g. from another installation).
                Tables/queries will be imported automatically. Migrations &amp; seeders will be skipped.
            </p>
        </div>

        <button type="submit" class="btn">Test Connection &amp; Continue →</button>
    </form>
@endsection
