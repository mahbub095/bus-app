@extends('install.layout')

@section('title', 'Installation Complete')
@section('subtitle', 'Your system is ready!')

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
        <div class="progress-step completed active">
            <div class="progress-step-circle">✓</div>
            <div class="progress-step-label">Done!</div>
        </div>
    </div>

    <div style="text-align:center; padding:10px 0 24px;">
        <div style="font-size:60px; margin-bottom:14px;">🎉</div>
        <h2 style="font-size:22px; font-weight:700; color:#10b981; margin-bottom:10px;">
            Installation Successful!
        </h2>
        @if(!empty($adminName))
            <p style="color:#334155; font-size:15px; margin-bottom:4px;">
                Welcome, <strong>{{ $adminName }}</strong>!
            </p>
        @endif
        @if(!empty($adminEmail))
            <p style="color:#64748b; font-size:13px; margin-bottom:24px;">
                Signed in as <strong>{{ $adminEmail }}</strong> (super_admin)
            </p>
        @endif

        <a href="/admin" class="btn" style="display:inline-block; width:auto; padding:14px 48px; text-decoration:none; margin-bottom:16px;">
            Go to Admin Dashboard →
        </a>

        <p style="font-size:12px; color:#94a3b8; margin-top:8px;">
            You are already signed in. Click the button above to open your dashboard.
        </p>
    </div>

    {{-- Install log (collapsed) --}}
    @if(!empty($log))
        <details style="margin-top:8px;">
            <summary style="cursor:pointer; font-size:12px; color:#667eea; font-weight:600; padding:6px 0;">
                ▸ Show install log
            </summary>
            <pre style="
                margin-top:8px;
                background:#0f172a;
                color:#94a3b8;
                border-radius:8px;
                padding:12px 14px;
                font-size:11px;
                line-height:1.7;
                max-height:220px;
                overflow-y:auto;
                white-space:pre-wrap;
                word-break:break-all;
                text-align:left;
            ">{{ implode("\n", $log) }}</pre>
        </details>
    @endif
@endsection
