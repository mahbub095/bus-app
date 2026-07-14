{{-- ======================================================================
     Shared Report Detail Page Layout
     Usage: @include('admin.reports.pages._layout', [
                'reportType'  => 'selling',
                'reportTitle' => 'Ticket Selling Report',
                'reportDesc'  => '...',
                'reportIcon'  => '🎫',
                'reportColor' => 'rgba(99,102,241,0.12)',
            ])
     Requires: $routes, $buses  (passed by ReportController::detailPage)
====================================================================== --}}
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    @include('admin.partials.theme-init')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $reportTitle }} | SonyaBus Admin</title>

    @include('admin.partials.fonts')

    {{-- Inherit the full shared stylesheet from layout --}}
    <style>
        @include('admin.partials.theme-variables')

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-sans);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* ── shared layout pieces copied from layout.blade.php ── */
        .app-header { background-color: var(--bg-header); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; }
        .container { width: 100%; max-width: 1400px; margin: 0 auto; padding: 0 24px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 70px; }
        .logo { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-size: 24px; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px; text-decoration: none; }
        .logo-icon { background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 20px; }
        .logo-accent { color: var(--accent); }
        .external-link-btn { background-color: var(--bg-btn-secondary); color: var(--text-primary); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: var(--border-radius-sm); font-size: 13px; font-weight: 600; text-decoration: none; transition: var(--transition); display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .external-link-btn:hover { background-color: var(--bg-btn-secondary-hover); border-color: var(--border-active); }
        .page-main { max-width: 1400px; margin: 0 auto; padding: 36px 24px 60px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; font-size: 13px; font-weight: 600; border-radius: var(--border-radius-sm); border: none; cursor: pointer; transition: var(--transition); text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; box-shadow: 0 4px 12px rgba(99,102,241,0.2); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(99,102,241,0.35); }
        .btn-secondary { background-color: var(--bg-btn-secondary); color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-secondary:hover { background-color: var(--bg-btn-secondary-hover); }
        .admin-panel { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 24px; box-shadow: var(--shadow-lg); }
        .admin-panel-title { font-family: var(--font-display); font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
        .coupon-input { width: 100%; padding: 10px 14px; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-primary); font-size: 13px; outline: none; transition: var(--transition); }
        .coupon-input:focus { border-color: var(--border-active); }
        .coupon-input option { background-color: var(--bg-card); color: var(--text-primary); }
        .table-wrapper { width: 100%; overflow-x: auto; }
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        .admin-table th { padding: 12px; border-bottom: 2px solid var(--border-color); color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .admin-table td { padding: 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }
        .admin-table tr:hover { background-color: rgba(255,255,255,0.02); }
        .coach-tag { display: inline-flex; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; background-color: rgba(255,255,255,0.06); color: var(--text-secondary); }
        .coach-tag.ac { background-color: rgba(99,102,241,0.15); color: #818CF8; }
        .stat-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 20px; display: flex; align-items: center; gap: 16px; }
        .stat-icon { background-color: rgba(99,102,241,0.08); width: 48px; height: 48px; border-radius: var(--border-radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 20px; }
        .stat-info { display: flex; flex-direction: column; }
        .stat-label { font-size: 12px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-family: var(--font-display); font-size: 22px; font-weight: 700; color: var(--text-primary); margin-top: 2px; }

        /* ── Report-specific pieces ── */
        .report-detail-header { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
        .report-detail-back { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background-color: var(--bg-btn-secondary); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); color: var(--text-secondary); font-size: 13px; font-weight: 600; text-decoration: none; transition: var(--transition); flex-shrink: 0; }
        .report-detail-back:hover { border-color: var(--border-active); color: var(--text-primary); background-color: var(--bg-btn-secondary-hover); }
        .report-detail-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .report-detail-title-block h1 { font-family: var(--font-display); font-size: 24px; font-weight: 800; background: linear-gradient(to right, var(--title-gradient-start), var(--title-gradient-end)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 4px; }
        .report-detail-title-block p { font-size: 13px; color: var(--text-secondary); }
        .report-filter-block { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .report-custom-dates { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; grid-column: 1 / -1; }
        .report-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .report-filter-label { font-size: 13px; color: var(--text-secondary); background-color: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.15); padding: 10px 14px; border-radius: var(--border-radius-sm); margin-bottom: 20px; }
        .report-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 10px; }
        .notice-info-box { background: rgba(99,102,241,0.07); border: 1px dashed rgba(99,102,241,0.25); border-radius: var(--border-radius-sm); padding: 18px 22px; font-size: 13px; color: var(--text-secondary); text-align: center; margin-top: 10px; }

        [data-theme="light"] .theme-icon-light { display: none; }
        [data-theme="dark"]  .theme-icon-dark  { display: none; }
    </style>
</head>
<body>

    <header class="app-header">
        <div class="container navbar">
            <a href="/admin" class="logo">
                <div class="logo-icon">S</div>
                Sonya<span class="logo-accent">Bus</span> Admin
            </a>
            <div style="display:flex;align-items:center;gap:15px;">
                @include('admin.partials.theme-toggle')
                <a href="http://localhost:5173" target="_blank" class="external-link-btn">🌐 View Booking Site</a>
                <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                    @csrf
                    <button class="external-link-btn" type="submit" style="cursor:pointer;background:none;border:1px solid rgba(239,68,68,0.3);color:#F87171;">🚪 Sign Out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="page-main">

        {{-- Back + title header ─────────────────────────────────────── --}}
        <div class="report-detail-header">
            <a href="/admin#reports" class="report-detail-back">
                ← Back to Reports
            </a>
            <div class="report-detail-icon" style="background-color: {{ $reportColor }}">
                {{ $reportIcon }}
            </div>
            <div class="report-detail-title-block">
                <h1>{{ $reportTitle }}</h1>
                <p>{{ $reportDesc }}</p>
            </div>
        </div>

        {{-- Filter + generate panel ─────────────────────────────────── --}}
        <div class="admin-panel" style="margin-bottom: 24px;">
            @php $operators = $buses->pluck('operator_name')->unique()->sort()->values(); @endphp

            <div class="report-filter-block">
                <div class="input-group">
                    <label>Period</label>
                    <select class="coupon-input" id="rp-period">
                        <option value="weekly">Weekly (This Week)</option>
                        <option value="monthly" selected>Monthly (This Month)</option>
                        <option value="quarterly">Quarterly (This Quarter)</option>
                        <option value="yearly">Yearly (This Year)</option>
                        <option value="custom">Custom Date Range</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Coach Type</label>
                    <select class="coupon-input" id="rp-coach-type">
                        <option value="All">All Coach Types</option>
                        <option value="AC">AC</option>
                        <option value="Non AC">Non AC</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Payment Method</label>
                    <select class="coupon-input" id="rp-payment-method">
                        <option value="All">All Methods</option>
                        <option value="bKash">bKash</option>
                        <option value="Nagad">Nagad</option>
                        <option value="Card">Card</option>
                        <option value="Cash">Cash</option>
                    </select>
                </div>

                <div class="input-group">
                    <label>Route</label>
                    <select class="coupon-input" id="rp-route-id">
                        <option value="All">All Routes</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}">{{ $route->from }} → {{ $route->to }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="input-group">
                    <label>Operator</label>
                    <select class="coupon-input" id="rp-operator">
                        <option value="All">All Operators</option>
                        @foreach($operators as $op)
                            <option value="{{ $op }}">{{ $op }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="report-custom-dates" id="rp-custom-dates" style="display:none; margin-bottom:20px;">
                <div class="input-group">
                    <label>From Date</label>
                    <input type="date" class="coupon-input" id="rp-from-date">
                </div>
                <div class="input-group">
                    <label>To Date</label>
                    <input type="date" class="coupon-input" id="rp-to-date">
                </div>
            </div>

            <div class="report-actions">
                <button type="button" class="btn btn-primary" id="rp-generate-btn">
                    ⚡ Generate Report
                </button>
                <button type="button" class="btn btn-secondary" id="rp-excel-btn" disabled>
                    📥 Export Excel
                </button>
                <button type="button" class="btn btn-secondary" id="rp-pdf-btn" disabled>
                    📄 Export PDF
                </button>
            </div>
        </div>

        {{-- Filter label ─────────────────────────────────────────────── --}}
        <div class="report-filter-label" id="rp-filter-label" style="display:none;"></div>

        {{-- Summary stat cards ───────────────────────────────────────── --}}
        <div class="report-summary-grid" id="rp-summary" style="display:none; margin-bottom:24px;"></div>

        {{-- Data table ───────────────────────────────────────────────── --}}
        <div class="admin-panel" id="rp-table-panel" style="display:none;">
            <h3 class="admin-panel-title">{{ $reportTitle }} — Details</h3>
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead id="rp-table-head"></thead>
                    <tbody id="rp-table-body"></tbody>
                </table>
            </div>
        </div>

        {{-- Empty hint ───────────────────────────────────────────────── --}}
        <div class="notice-info-box" id="rp-empty-hint">
            Select filters above and click <strong>Generate Report</strong> to view data.
        </div>

    </main>

    {{-- Pass report config to JS ─────────────────────────────────────── --}}
    <script>
        window.ReportDetail = {
            type: @json($reportType),
            routes: {
                preview: @json(route('admin.reports.' . $reportType . '.preview')),
                excel:   @json(route('admin.reports.' . $reportType . '.excel')),
                pdf:     @json(route('admin.reports.' . $reportType . '.pdf')),
            },
        };
    </script>

    @vite('resources/js/admin/report-detail.js')

</body>
</html>
