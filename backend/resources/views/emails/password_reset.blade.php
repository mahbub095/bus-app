<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f0f15;
            color: #e2e8f0;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #0f0f15;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #151521;
            border-radius: 16px;
            border: 1px solid #222235;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        .header {
            background: linear-gradient(135deg, #2e1065 0%, #1e1b4b 100%);
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid #222235;
        }
        .logo {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #a78bfa;
            text-decoration: none;
            display: inline-block;
        }
        .logo span {
            color: #ffffff;
        }
        .content {
            padding: 40px 30px;
        }
        h1 {
            font-size: 22px;
            color: #ffffff;
            margin-top: 0;
            margin-bottom: 20px;
            font-weight: 700;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #94a3b8;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .code-container {
            background-color: #1e1e2f;
            border-radius: 12px;
            border: 1px dashed #4c1d95;
            padding: 24px;
            text-align: center;
            margin: 30px 0;
        }
        .code {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 6px;
            color: #a78bfa;
            margin: 0;
            font-family: 'Courier New', Courier, monospace;
        }
        .expiry-text {
            font-size: 13px;
            color: #64748b;
            text-align: center;
            margin-top: 10px;
        }
        .footer {
            background-color: #0d0d14;
            padding: 24px 30px;
            text-align: center;
            border-top: 1px solid #1e1e2f;
        }
        .footer p {
            font-size: 12px;
            color: #475569;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <a href="#" class="logo">Sonya<span>Bus</span></a>
            </div>

            <!-- Content -->
            <div class="content">
                <h1>Password Reset Request</h1>
                <p>Hello,</p>
                <p>We received a request to reset the password for your SonyaBus account. Please use the verification code below to proceed with setting up a new password.</p>
                
                <div class="code-container">
                    <div class="code">{{ $code }}</div>
                    <div class="expiry-text">This code will expire in 60 minutes.</div>
                </div>

                <p>If you did not request a password reset, no further action is required. Your account remains secure.</p>
                <p>Best regards,<br>The SonyaBus Team</p>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>&copy; {{ date('Y') }} SonyaBus. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
