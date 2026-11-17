<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    @include('admin.partials.theme-init')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JourneyGo | Forgot Password</title>
    @include('admin.partials.favicon')
    @include('admin.partials.fonts')
    <style>
        @include('admin.partials.theme-variables')
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-sans);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }
        .auth-container { width: 100%; max-width: 420px; padding: 20px; }
        .auth-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }
        .logo-wrap {
            display: flex; align-items: center; justify-content: center;
            gap: 10px; font-family: var(--font-display); font-size: 26px;
            font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px;
            margin-bottom: 12px;
        }
        .logo-accent { color: var(--accent); }
        .logo-icon {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff; width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 22px; box-shadow: var(--shadow-neon);
        }
        .auth-subtitle { font-size: 14px; color: var(--text-secondary); margin-bottom: 30px; }
        .booking-form-fields { display: flex; flex-direction: column; gap: 20px; text-align: left; }
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
        .coupon-input {
            width: 100%; padding: 12px 14px;
            background-color: var(--bg-input); border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm); color: var(--text-primary);
            font-size: 14px; outline: none; transition: var(--transition);
        }
        .coupon-input:focus { border-color: var(--border-active); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 100%; height: 46px; font-family: var(--font-display); font-size: 15px;
            font-weight: 700; letter-spacing: 0.5px; border-radius: var(--border-radius-sm);
            border: none; cursor: pointer; transition: var(--transition);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff; box-shadow: 0 4px 12px rgba(99,102,241,0.2); margin-top: 10px;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(99,102,241,0.35); }
        .alert-banner {
            border-radius: var(--border-radius-sm); padding: 12px 16px; margin-bottom: 20px;
            font-size: 13px; font-weight: 500; display: flex; align-items: flex-start;
            gap: 10px; text-align: left;
        }
        .alert-success { background-color: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #34D399; }
        .alert-danger { background-color: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #F87171; }
        .login-theme-toggle { position: fixed; top: 20px; right: 20px; z-index: 10; }
        .theme-toggle-btn {
            width: 42px; height: 42px; border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-color); background-color: var(--bg-card);
            color: var(--primary); cursor: pointer; display: inline-flex;
            align-items: center; justify-content: center; transition: var(--transition);
            box-shadow: var(--shadow-lg);
        }
        .theme-toggle-btn:hover { border-color: var(--primary); }
        [data-theme="light"] .theme-icon-light { display: none; }
        [data-theme="dark"] .theme-icon-dark { display: none; }
    </style>
</head>
<body>
    <div class="login-theme-toggle">
        @include('admin.partials.theme-toggle')
    </div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="logo-wrap">
                <div class="logo-icon">J</div>
                Journey<span class="logo-accent">Go</span> Admin
            </div>
            <p class="auth-subtitle">Enter your admin email to receive a 6-digit reset code</p>

            @if(session('success'))
                <div class="alert-banner alert-success" role="alert">
                    <span>✔</span><span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="alert-banner alert-danger" role="alert">
                    <span>🗙</span>
                    <div>
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form class="booking-form-fields" action="{{ route('admin.forgot-password') }}" method="POST">
                @csrf
                <div class="input-group">
                    <label for="email">Admin Email Address</label>
                    <input type="email" name="email" id="email" class="coupon-input"
                           placeholder="admin@example.com" required
                           value="{{ old('email') }}" autocomplete="email" autofocus>
                </div>

                <button class="btn btn-primary" type="submit">
                    Send Reset Code
                </button>

                <div style="text-align:center;margin-top:4px;">
                    <a href="{{ route('login') }}" style="font-size:12px;color:var(--text-secondary);text-decoration:none;">
                        ← Back to Sign In
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('admin-theme-toggle')?.addEventListener('click', () => {
                const storageKey = 'sonyabus_admin_theme';
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem(storageKey, next);
            });
        });
    </script>
</body>
</html>
