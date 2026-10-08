<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Installation') - SonyaBus</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .install-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }

        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .install-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .install-header p {
            font-size: 16px;
            opacity: 0.95;
        }

        .install-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-help {
            font-size: 13px;
            color: #666;
            margin-top: 6px;
            line-height: 1.5;
        }

        .form-help a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .form-help a:hover {
            text-decoration: underline;
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert-error {
            background-color: #fee;
            border-left: 4px solid #e53e3e;
            color: #c53030;
        }

        .alert-success {
            background-color: #f0fdf4;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 32px;
            padding: 0 20px;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 16px;
            left: 50%;
            right: -50%;
            height: 2px;
            background: #e0e0e0;
            z-index: -1;
        }

        .progress-step.active:not(:last-child)::after {
            background: #667eea;
        }

        .progress-step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .progress-step.active .progress-step-circle {
            background: #667eea;
            color: white;
        }

        .progress-step.completed .progress-step-circle {
            background: #10b981;
            color: white;
        }

        .progress-step-label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }

        .progress-step.active .progress-step-label {
            color: #667eea;
            font-weight: 600;
        }

        .install-footer {
            background: #f9fafb;
            padding: 20px 40px;
            text-align: center;
            color: #666;
            font-size: 13px;
        }

        .install-footer a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>SonyaBus Installation</h1>
            <p>@yield('subtitle', 'Setting up your bus booking system')</p>
        </div>

        <div class="install-body">
            @yield('content')
        </div>

        <div class="install-footer">
            <p>SonyaBus &copy; 2026 | <a href="https://codecanyon.net" target="_blank">CodeCanyon</a></p>
        </div>
    </div>
</body>
</html>
