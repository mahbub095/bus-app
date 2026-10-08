@extends('install.layout')

@section('title', 'Create Admin Account')
@section('subtitle', 'Step 3 of 4 — Set up your super admin')

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
        <div class="progress-step active">
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

    <form method="POST" action="/install/admin/save">
        @csrf

        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="John Doe" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="At least 8 characters" required>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Re-enter password" required>
        </div>

        <button type="submit" class="btn">Continue →</button>
    </form>
@endsection
