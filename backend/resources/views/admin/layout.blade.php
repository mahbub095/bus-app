<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SonyaBus | Admin Dashboard Portal</title>
    
    <!-- Outfit & Inter fonts from Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-display: 'Outfit', sans-serif;
            
            --bg-main: #0B0B14;
            --bg-card: #141424;
            --bg-card-hover: #1C1C34;
            --border-color: rgba(255, 255, 255, 0.08);
            --border-active: #6366F1;
            
            --primary: #6366F1;
            --primary-hover: #4F46E5;
            --primary-glow: rgba(99, 102, 241, 0.25);
            
            --accent: #A855F7;
            --accent-glow: rgba(168, 85, 247, 0.2);
            
            --gold: #F59E0B;
            
            --text-primary: #F3F4F6;
            --text-secondary: #9CA3AF;
            --text-muted: #6B7280;
            
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            
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
            line-height: 1.5;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Admin shell: sidebar + main content */
        .admin-shell {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        .admin-sidebar {
            width: 260px;
            flex-shrink: 0;
            background-color: var(--bg-card);
            border-right: 1px solid var(--border-color);
            padding: 20px 0;
            position: sticky;
            top: 70px;
            height: calc(100vh - 70px);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar-section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            padding: 8px 24px 12px;
        }

        .sidebar-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: var(--text-secondary);
            cursor: pointer;
            border-left: 3px solid transparent;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
            user-select: none;
        }

        .sidebar-nav-item:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.03);
        }

        .sidebar-nav-item.active {
            color: var(--primary);
            background-color: rgba(99, 102, 241, 0.1);
            border-left-color: var(--primary);
        }

        .sidebar-nav-item.danger {
            color: #F87171;
        }

        .sidebar-nav-item.danger.active {
            background-color: rgba(239, 68, 68, 0.1);
            border-left-color: #EF4444;
            color: #F87171;
        }

        .sidebar-nav-icon {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-spacer {
            flex: 1;
        }

        .admin-main {
            flex: 1;
            min-width: 0;
            padding: 32px 32px 60px;
        }

        @media (max-width: 900px) {
            .admin-shell {
                flex-direction: column;
            }

            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
                flex-direction: row;
                flex-wrap: wrap;
                padding: 12px;
                gap: 4px;
            }

            .sidebar-section-label,
            .sidebar-spacer {
                display: none;
            }

            .sidebar-nav-item {
                border-left: none;
                border-radius: var(--border-radius-sm);
                padding: 10px 14px;
                font-size: 13px;
            }

            .sidebar-nav-item.active {
                border-left: none;
            }

            .admin-main {
                padding: 20px 16px 40px;
            }
        }

        /* Header logo navbar */
        .app-header {
            background-color: rgba(11, 11, 20, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            text-decoration: none;
        }

        .logo-accent {
            color: var(--accent);
        }

        .logo-icon {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 20px;
            box-shadow: var(--shadow-neon);
        }

        .external-link-btn {
            background-color: #202038;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: var(--border-radius-sm);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .external-link-btn:hover {
            background-color: #2B2B4C;
            border-color: rgba(255,255,255,0.2);
        }

        /* Banner title block */
        .admin-header {
            padding: 40px 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-title-wrap h1 {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(to right, #fff, #D1D5DB);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .admin-title-wrap p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 4px;
        }

        /* Metrics grid panel */
        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            background-color: rgba(99,102,241,0.08);
            color: var(--primary);
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 20px;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            margin-top: 2px;
        }

        /* Custom Tabs header panel - legacy, kept for compatibility */
        .auth-tabs {
            display: none;
        }

        /* Content panels switcher */
        .admin-tab-content {
            display: none;
        }

        /* Two column layout list/form */
        .admin-sections-layout {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 30px;
        }

        @media (max-width: 900px) {
            .admin-sections-layout {
                grid-template-columns: 1fr;
            }
        }

        .admin-panel {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow-lg);
        }

        .admin-panel-title {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Styled table */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .admin-table th {
            padding: 12px;
            border-bottom: 2px solid var(--border-color);
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .admin-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .admin-table tr:hover {
            background-color: rgba(255,255,255,0.02);
        }

        .badge-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-status.paid {
            background-color: rgba(16, 185, 129, 0.15);
            color: #34D399;
        }

        .badge-status.cancelled {
            background-color: rgba(239, 68, 68, 0.15);
            color: #F87171;
        }

        .coach-tag {
            display: inline-flex;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background-color: rgba(255,255,255,0.06);
            color: var(--text-secondary);
        }

        .coach-tag.ac {
            background-color: rgba(99, 102, 241, 0.15);
            color: #818CF8;
        }

        /* Sidebar input forms details */
        .booking-form-sidebar {
            background-color: #121223;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 24px;
            height: max-content;
        }

        .booking-summary-title {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .booking-form-fields {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            text-align: left;
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
            padding: 10px 14px;
            background-color: #1A1A2E;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            color: var(--text-primary);
            font-size: 13px;
            outline: none;
            transition: var(--transition);
        }

        .coupon-input:focus {
            border-color: var(--border-active);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
            border-radius: var(--border-radius-sm);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.35);
        }

        .btn-danger {
            background-color: var(--danger);
            color: #fff;
        }

        .btn-danger:hover {
            background-color: #D32F2F;
        }

        .btn-secondary {
            background-color: #202038;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: #2B2B4C;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 11px;
        }

        .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }

        .action-btns form {
            margin: 0;
            display: inline;
        }

        .form-cancel-btn {
            display: none;
            margin-top: 8px;
        }

        .form-cancel-btn.visible {
            display: inline-flex;
        }

        /* Flash session notifications alerts */
        .alert-banner {
            border-radius: var(--border-radius-sm);
            padding: 14px 20px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
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

        /* Console Output box styled like terminal */
        .terminal-window {
            background-color: #05050A;
            border: 1px solid #1E1E34;
            border-radius: 8px;
            margin-top: 24px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8);
        }

        .terminal-header {
            background-color: #121223;
            border-bottom: 1px solid #1E1E34;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .terminal-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .terminal-dot.red { background-color: #EF4444; }
        .terminal-dot.yellow { background-color: #F59E0B; }
        .terminal-dot.green { background-color: #10B981; }

        .terminal-title {
            font-size: 11px;
            font-family: var(--font-sans);
            color: var(--text-secondary);
            font-weight: bold;
            margin-left: 8px;
        }

        .terminal-body {
            padding: 18px;
            color: #10B981;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 250px;
            overflow-y: auto;
            text-align: left;
        }

        .notice-info-box {
            background-color: rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.15);
            color: #A5B4FC;
            padding: 16px;
            border-radius: var(--border-radius-sm);
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .db-action-card {
            background-color: #121223;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .db-actions-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .app-footer {
            background-color: #08080E;
            border-top: 1px solid var(--border-color);
            padding: 30px 0;
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
            margin-top: 60px;
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <header class="app-header">
        <div class="container navbar">
            <a href="/admin" class="logo">
                <div class="logo-icon">S</div>
                Sonya<span class="logo-accent">Bus</span> Admin
            </a>
            
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="http://localhost:5173" target="_blank" class="external-link-btn">
                    🌐 View Booking Site
                </a>
                
                <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button class="external-link-btn" type="submit" style="cursor: pointer; background: none; border: 1px solid rgba(239, 68, 68, 0.3); color: #F87171;">
                        🚪 Sign Out
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="admin-shell">
        <!-- Left Sidebar Navigation -->
        <aside class="admin-sidebar">
            <div class="sidebar-section-label">Management</div>
            <div class="sidebar-nav-item active" data-tab="bookings">
                <span class="sidebar-nav-icon">📋</span>
                Bookings Logs
            </div>
            <div class="sidebar-nav-item" data-tab="stations">
                <span class="sidebar-nav-icon">🚉</span>
                Stations
            </div>
            <div class="sidebar-nav-item" data-tab="buses">
                <span class="sidebar-nav-icon">🚌</span>
                Coaches
            </div>
            <div class="sidebar-nav-item" data-tab="routes">
                <span class="sidebar-nav-icon">🛣️</span>
                Routes
            </div>
            <div class="sidebar-nav-item" data-tab="schedules">
                <span class="sidebar-nav-icon">📅</span>
                Schedules
            </div>
            <div class="sidebar-nav-item" data-tab="promotions">
                <span class="sidebar-nav-icon">🎟️</span>
                Coupons
            </div>

            <div class="sidebar-spacer"></div>

            <div class="sidebar-section-label">System</div>
            <div class="sidebar-nav-item danger" data-tab="database">
                <span class="sidebar-nav-icon">⚙️</span>
                Database Operations
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-main">
        
        <!-- Header Banner -->
        <section class="admin-header">
            <div class="admin-title-wrap">
                <h1>Control Panel Dashboard</h1>
                <p>Welcome back, {{ Auth::user()->name }}. Manage timetables, seating templates, and database migrations.</p>
            </div>
        </section>

        <!-- Notification messages -->
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

        <!-- Metrics widgets -->
        <section class="admin-stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--gold)">$</div>
                <div class="stat-info">
                    <span class="stat-label">Sales Revenue</span>
                    <span class="stat-value">BDT {{ number_format($metrics['total_sales']) }}</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: var(--success)">✔</div>
                <div class="stat-info">
                    <span class="stat-label">Active Bookings</span>
                    <span class="stat-value">{{ $metrics['active_bookings'] }} Tickets</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: var(--danger)">🗙</div>
                <div class="stat-info">
                    <span class="stat-label">Cancelled Tickets</span>
                    <span class="stat-value">{{ $metrics['cancelled_bookings'] }} Cancelled</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: var(--primary)">🚌</div>
                <div class="stat-info">
                    <span class="stat-label">Active Schedules</span>
                    <span class="stat-value">{{ $metrics['total_schedules'] }} Runs</span>
                </div>
            </div>
        </section>

        <!-- Yield the content which contains tab panels -->
        @yield('content')

        </main>
    </div>

    <!-- Footer -->
    <footer class="app-footer">
        <div class="container">
            <p>© 2026 SonyaBus Enterprise Ltd. All rights reserved. Admin Dashboard Control Portal.</p>
        </div>
    </footer>

    <!-- Tab state switching javascript -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const navItems = document.querySelectorAll('.sidebar-nav-item');
            const contents = document.querySelectorAll('.admin-tab-content');
            
            let activeTab = localStorage.getItem('admin_active_tab') || 'bookings';
            
            const switchTab = (tabName) => {
                navItems.forEach(item => {
                    if (item.getAttribute('data-tab') === tabName) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                });
                
                contents.forEach(c => {
                    if (c.id === `tab-content-${tabName}`) {
                        c.style.display = 'grid';
                    } else {
                        c.style.display = 'none';
                    }
                });
                
                localStorage.setItem('admin_active_tab', tabName);
            };
            
            navItems.forEach(item => {
                item.addEventListener('click', () => {
                    switchTab(item.getAttribute('data-tab'));
                });
            });
            
            switchTab(activeTab);
        });

        function setCrudFormMode(formId, config) {
            const form = document.getElementById(formId);
            if (!form) return;

            const titleEl = document.getElementById(formId + '-title');
            const submitBtn = document.getElementById(formId + '-submit');
            const cancelBtn = document.getElementById(formId + '-cancel');
            const idInput = form.querySelector('[name="_edit_id"]');
            const methodInput = form.querySelector('[name="_method"]');

            if (config.mode === 'edit') {
                form.action = config.action;
                if (methodInput) methodInput.value = 'PUT';
                if (titleEl) titleEl.textContent = config.title;
                if (submitBtn) submitBtn.textContent = config.submitLabel;
                if (cancelBtn) cancelBtn.classList.add('visible');
                if (idInput) idInput.value = config.id;

                Object.entries(config.fields).forEach(([name, value]) => {
                    const field = form.querySelector(`[name="${name}"]`);
                    if (field) field.value = value ?? '';
                });
            } else {
                form.reset();
                form.action = config.createAction;
                if (methodInput) methodInput.value = 'POST';
                if (titleEl) titleEl.textContent = config.createTitle;
                if (submitBtn) submitBtn.textContent = config.createSubmitLabel;
                if (cancelBtn) cancelBtn.classList.remove('visible');
                if (idInput) idInput.value = '';
            }

            if (formId === 'booking-form') {
                const scheduleGroup = document.getElementById('booking-schedule-group');
                if (scheduleGroup) {
                    scheduleGroup.style.display = config.mode === 'edit' ? 'none' : 'flex';
                }
            }
        }

        function resetCrudForm(formId, createAction, createTitle, createSubmitLabel) {
            setCrudFormMode(formId, {
                mode: 'create',
                createAction: createAction,
                createTitle: createTitle,
                createSubmitLabel: createSubmitLabel
            });
        }
    </script>
</body>
</html>
