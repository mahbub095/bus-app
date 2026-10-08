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
        <div class="progress-step active completed">
            <div class="progress-step-circle">✓</div>
            <div class="progress-step-label">Done!</div>
        </div>
    </div>

    <div style="text-align: center; padding: 20px 0;">
        <div style="font-size: 64px; margin-bottom: 16px;">🎉</div>
        <h2 style="font-size: 24px; font-weight: 700; color: #10b981; margin-bottom: 12px;">
            Installation Successful!
        </h2>
        <p style="color: #555; font-size: 16px; line-height: 1.6; margin-bottom: 32px;">
            SonyaBus is installed and your license has been activated.<br>
            You can now log in to the admin dashboard.
        </p>

        <a href="/admin/login" class="btn" style="display: inline-block; width: auto; padding: 14px 48px; text-decoration: none;">
            Go to Admin Dashboard →
        </a>

        <p style="margin-top: 32px; font-size: 13px; color: #999;">
            <strong>Note:</strong> For security, consider deleting or restricting access to the
            <code>/install</code> route after setup.
        </p>
    </div>
@endsection
