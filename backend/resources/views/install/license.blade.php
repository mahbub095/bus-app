@extends('install.layout')

@section('title', 'License Activation')
@section('subtitle', 'Step 1 of 4 — Activate your license')

@section('content')
    {{-- Progress Steps --}}
    <div class="progress-steps">
        <div class="progress-step active">
            <div class="progress-step-circle">1</div>
            <div class="progress-step-label">License</div>
        </div>
        <div class="progress-step">
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

    <form method="POST" action="/install/license/verify">
        @csrf

        <div class="form-group">
            <label for="purchase_code">Envato Purchase Code</label>
            <input
                type="text"
                id="purchase_code"
                name="purchase_code"
                value="{{ old('purchase_code') }}"
                placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                required
                autocomplete="off"
            >
            <p class="form-help">
                Find your purchase code in
                <a href="https://help.market.envato.com/hc/en-us/articles/202822600" target="_blank">
                    Envato Market → Downloads → Licenses &amp; Purchase Codes
                </a>.
            </p>
        </div>

        <div class="form-group">
            <label for="personal_token">Envato Personal Token</label>
            <input
                type="password"
                id="personal_token"
                name="personal_token"
                placeholder="Your Envato API personal token"
                required
                autocomplete="off"
            >
            <p class="form-help">
                Generate a token at
                <a href="https://build.envato.com/create-token/?purchase:download=t&purchase:verify=t&purchase:list=t" target="_blank">
                    build.envato.com → Create Token
                </a>.
                Enable <strong>Verify Purchases</strong> permission. The token is used once and not stored.
            </p>
        </div>

        <button type="submit" class="btn">Verify License &amp; Continue →</button>
    </form>
@endsection
