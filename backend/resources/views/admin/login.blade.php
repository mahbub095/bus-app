<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SonyaBus | Admin Login Portal</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-display: 'Outfit', sans-serif;
            
            --bg-main: #0B0B14;
            --bg-card: #141424;
            --border-color: rgba(255, 255, 255, 0.08);
            --border-active: #6366F1;
            
            --primary: #6366F1;
            --primary-hover: #4F46E5;
            --primary-glow: rgba(99, 102, 241, 0.25);
            
            --accent: #A855F7;
            
            --text-primary: #F3F4F6;
            --text-secondary: #9CA3AF;
            --text-muted: #6B7280;
            
            --success: #10B981;
            --danger: #EF4444;
            
            --border-radius: 12px;
            --border-radius-sm: 8px;
            --border-radius-lg: 20px;
            
            --shadow-lg: 0 16px 40px rgba(0,0,0,0.7);
            --shadow-neon: 0 0 15px rgba(99, 102, 241, 0.4);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

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

        /* Glassmorphism auth card */
        .auth-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .auth-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            position: relative;
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            margin-bottom: 12px;
        }

        .logo-accent {
            color: var(--accent);
        }

        .logo-icon {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 22px;
            box-shadow: var(--shadow-neon);
        }

        .auth-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 30px;
        }

        .booking-form-fields {
            display: flex;
            flex-direction: column;
            gap: 20px;
            text-align: left;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-group label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .coupon-input {
            width: 100%;
            padding: 12px 14px;
            background-color: #1A1A2E;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            transition: var(--transition);
        }

        .coupon-input:focus {
            border-color: var(--border-active);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
            user-select: none;
            cursor: pointer;
        }

        .checkbox-group input {
            cursor: pointer;
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 46px;
            font-family: var(--font-display);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-radius: var(--border-radius-sm);
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
            margin-top: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.35);
        }

        /* Banners alerts */
        .alert-banner {
            border-radius: var(--border-radius-sm);
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #34D399;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #F87171;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        
        <div class="auth-card">
            
            <div class="logo-wrap">
                <div class="logo-icon">S</div>
                Sonya<span class="logo-accent">Bus</span> Admin
            </div>
            <p class="auth-subtitle">Access administrative control console</p>

            <!-- Notifications / Session logs -->
            @if(session('success'))
                <div class="alert-banner alert-success">
                    <span>✔</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="alert-banner alert-danger">
                    <span>🗙</span>
                    <div>
                        <ul style="list-style: none; padding-left: 0;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form class="booking-form-fields" action="{{ route('login') }}" method="POST">
                @csrf
                
                <div class="input-group">
                    <label for="email">E-mail Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        class="coupon-input" 
                        placeholder="e.g. admin@sonyabus.com" 
                        required 
                        value="{{ old('email') }}"
                        autocomplete="email"
                        autofocus
                    >
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        class="coupon-input" 
                        placeholder="••••••••" 
                        required
                        autocomplete="current-password"
                    >
                </div>

                <label class="checkbox-group">
                    <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <span>Remember my credentials</span>
                </label>

                <button class="btn btn-primary" type="submit">
                    Sign In to Portal
                </button>
            </form>

        </div>
        
    </div>

</body>
</html>
