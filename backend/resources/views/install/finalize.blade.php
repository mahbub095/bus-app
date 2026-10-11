@extends('install.layout')

@section('title', 'Finalize Installation')
@section('subtitle', 'Step 4 of 4 — Almost done!')

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

    @if ($errors->any())
        <div class="alert alert-error">
            {{ $errors->first() }}
        </div>
    @endif

    <div style="text-align:center; padding: 6px 0 20px;">
        <div style="font-size:48px; margin-bottom:12px;">🚀</div>
        <h2 style="font-size:19px; font-weight:700; color:#1e293b; margin-bottom:8px;">
            Confirm Admin &amp; Finalize
        </h2>
        <p style="color:#64748b; font-size:13px; line-height:1.6; margin-bottom:20px;">
            Confirm your admin account details and click <strong>Run Installation</strong>.
        </p>
    </div>

    <form method="POST" action="/install/finalize/run" id="finalizeForm">
        @csrf

        <div class="form-group">
            <label for="admin_name">Full Name</label>
            <input type="text" id="admin_name" name="admin_name"
                   value="{{ old('admin_name', session('admin_name')) }}"
                   placeholder="Super Admin" required>
        </div>

        <div class="form-group">
            <label for="admin_email">Email Address</label>
            <input type="email" id="admin_email" name="admin_email"
                   value="{{ old('admin_email', session('admin_email')) }}"
                   placeholder="admin@example.com" required>
        </div>

        <div class="form-group">
            <label for="admin_password">Password</label>
            <input type="password" id="admin_password" name="admin_password"
                   placeholder="Min 8 characters" required minlength="8">
        </div>

        <button type="submit" class="btn" id="installBtn">
            ⚙️ &nbsp;Run Installation Now
        </button>
    </form>

    <script>
        document.getElementById('finalizeForm').addEventListener('submit', function() {
            var btn = document.getElementById('installBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Installing... please wait';
            btn.style.opacity = '0.7';
        });
    </script>
@endsection
