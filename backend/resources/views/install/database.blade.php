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

    <form method="POST" action="/install/database/save">
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

        <button type="submit" class="btn">Test Connection &amp; Continue →</button>
    </form>
@endsection
