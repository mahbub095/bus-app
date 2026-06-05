<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SonyaBus | Admin Dashboard Portal</title>
    
    @include('admin.partials.fonts')

    <style>
        @include('admin.partials.theme-variables')

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
            text-decoration: none;
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

        .badge-status.pending {
            background-color: rgba(245, 158, 11, 0.15);
            color: #FBBF24;
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

        /* Sidebar input forms details (match frontend booking panel) */
        .booking-form-sidebar {
            background-color: #18182E;
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
            transition: opacity 0.4s ease, transform 0.4s ease, margin 0.4s ease, padding 0.4s ease;
        }

        .alert-banner.flash-dismissed {
            opacity: 0;
            transform: translateY(-6px);
            margin-bottom: 0;
            padding-top: 0;
            padding-bottom: 0;
            pointer-events: none;
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

        /* Coach Services — frontend-style search & seat map */
        .search-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-lg);
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 16px;
            align-items: end;
        }

        @media (max-width: 900px) {
            .search-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }

        .results-count {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .bus-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .bus-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: var(--transition);
        }

        .bus-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }

        .bus-main-info {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 1.2fr 1.5fr 1.5fr;
            padding: 24px;
            align-items: center;
            gap: 16px;
        }

        @media (max-width: 900px) {
            .bus-main-info {
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
        }

        .operator-block {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .operator-name {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }

        .time-block, .seats-block, .price-block {
            display: flex;
            flex-direction: column;
        }

        .time-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
        }

        .time-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 2px;
        }

        .station-value {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .seats-count {
            font-size: 15px;
            font-weight: 700;
        }

        .price-amount {
            font-size: 20px;
            font-weight: 800;
            color: var(--gold);
            font-family: var(--font-display);
        }

        .seats-selector-container {
            border-top: 1px solid var(--border-color);
            background-color: #121221;
            padding: 30px;
        }

        .seat-selection-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 900px) {
            .seat-selection-grid {
                grid-template-columns: 1fr;
            }
        }

        .bus-blueprint {
            background-color: #0A0A12;
            border: 2px solid #2A2A44;
            border-radius: 20px;
            padding: 24px;
            max-width: 360px;
            margin: 0 auto;
        }

        .bus-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px dashed #2A2A44;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .bus-body-seats {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .seat-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .seat-pair {
            display: flex;
            gap: 8px;
        }

        .bus-aisle {
            width: 36px;
        }

        .seat-single {
            display: flex;
            width: 36px;
        }

        .seat-placeholder {
            width: 36px;
            height: 36px;
            visibility: hidden;
            pointer-events: none;
        }

        .sleeper-decks {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .deck-title {
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px dashed #2A2A44;
            padding-bottom: 6px;
        }

        .seat {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid #3F3F5F;
            background-color: #161625;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: default;
            transition: var(--transition);
            user-select: none;
        }

        .seat.selectable {
            cursor: pointer;
        }

        .seat.selectable:hover {
            transform: scale(1.05);
        }

        .seat.selected {
            background-color: #22c55e;
            border-color: #16a34a;
            color: #fff;
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.45);
            cursor: pointer;
        }

        .seat.viewing-booking {
            outline: 3px solid #6366f1;
            outline-offset: 2px;
            z-index: 1;
        }

        .seat.viewing-booking:not(.selected) {
            box-shadow: none;
        }

        .seat.status-available {
            background-color: #fff;
            border-color: #d1d5db;
            color: #374151;
            cursor: pointer;
        }

        .seat.status-blocked {
            background-color: #6b7280;
            border-color: #4b5563;
            color: #f3f4f6;
            cursor: not-allowed;
            opacity: 0.85;
        }

        .seat.status-booked_m {
            background-color: #fecaca;
            border-color: #f87171;
            color: #7f1d1d;
            cursor: pointer;
        }

        .seat.status-booked_f {
            background-color: #fdf4ff;
            border-color: #f0abfc;
            color: #a21caf;
            cursor: pointer;
        }

        .seat.status-sold_m {
            background-color: #ef4444;
            border-color: #b91c1c;
            color: #fff;
            cursor: pointer;
        }

        .seat.status-sold_f {
            background-color: #ec4899;
            border-color: #be185d;
            color: #fff;
            cursor: pointer;
        }

        .seat-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px 18px;
            margin-top: 20px;
            font-size: 11px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .legend-dot {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            border: 1px solid #3F3F5F;
        }

        .legend-dot.status-available { background-color: #fff; border-color: #d1d5db; }
        .legend-dot.status-selected { background-color: #22c55e; border-color: #16a34a; }
        .legend-dot.status-blocked { background-color: #6b7280; border-color: #4b5563; }
        .legend-dot.status-booked_m { background-color: #fecaca; border-color: #f87171; }
        .legend-dot.status-booked_f { background-color: #fdf4ff; border-color: #f0abfc; }

        .seat-map-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 14px;
            margin-bottom: 12px;
        }

        .seat-map-toolbar-hint {
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .cs-block-mode-toggle.active {
            border-color: var(--warning);
            background-color: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
        }
        .legend-dot.status-sold_m { background-color: #ef4444; border-color: #b91c1c; }
        .legend-dot.status-sold_f { background-color: #ec4899; border-color: #be185d; }

        .routes-admin-layout {
            grid-template-columns: 1.4fr 1fr !important;
        }

        .route-form-sidebar {
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }

        .route-points-section {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }

        .route-points-heading {
            color: var(--primary);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 10px;
            font-weight: 700;
        }

        .route-points-table input.coupon-input {
            margin-bottom: 0;
            min-width: 0;
        }

        .route-points-table th {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-secondary);
        }

        .ticket-booking-panel {
            background: var(--bg-card);
            color: var(--text-primary);
            border-radius: var(--border-radius);
            padding: 20px;
            border: 1px solid var(--border-color);
        }

        .ticket-booking-panel h3,
        .ticket-booking-panel h4 {
            color: var(--primary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 12px;
            font-weight: 700;
        }

        .ticket-booking-panel label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            display: block;
            margin-bottom: 6px;
        }

        .ticket-booking-panel .ticket-field,
        .ticket-booking-panel select,
        .ticket-booking-panel input[type="text"],
        .ticket-booking-panel input[type="tel"],
        .ticket-booking-panel input[type="email"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 14px;
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-primary);
            box-sizing: border-box;
        }

        .ticket-booking-panel select.ticket-field {
            color-scheme: light;
            margin-bottom: 8px;
        }

        .ticket-booking-panel input[type="text"]::placeholder,
        .ticket-booking-panel input[type="tel"]::placeholder,
        .ticket-booking-panel input[type="email"]::placeholder,
        .ticket-booking-panel .ticket-field::placeholder {
            color: #9ca3af;
            opacity: 1;
        }

        .ticket-booking-panel select option {
            background-color: #ffffff;
            color: #1f2937;
        }

        .ticket-booking-panel select option:checked {
            background-color: #4f46e5;
            color: #ffffff;
        }

        .ticket-booking-panel select:focus,
        .ticket-booking-panel .ticket-field:focus,
        .ticket-booking-panel input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-glow);
        }

        .ticket-booking-panel input.ticket-field:-webkit-autofill,
        .ticket-booking-panel input.ticket-field:-webkit-autofill:hover,
        .ticket-booking-panel input.ticket-field:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--text-primary);
            -webkit-box-shadow: 0 0 0 1000px #1c1c34 inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        .payment-toggle-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }

        .payment-toggle {
            flex: 1;
            min-width: 70px;
            padding: 10px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-color);
            background-color: #1F1F38;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }

        .payment-toggle:hover {
            border-color: rgba(255, 255, 255, 0.2);
        }

        .payment-toggle.active {
            border-color: var(--primary);
            background-color: rgba(99, 102, 241, 0.08);
            color: #fff;
        }

        .seat-info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin: 8px 0 12px;
        }

        .seat-info-table th {
            text-align: left;
            color: var(--primary);
            font-size: 11px;
            text-transform: uppercase;
            padding: 8px 4px;
            border-bottom: 1px solid var(--border-color);
        }

        .seat-info-table td {
            padding: 8px 4px;
            border-bottom: 1px solid var(--border-color);
        }

        .fare-breakdown {
            font-size: 13px;
            line-height: 1.8;
        }

        .fare-breakdown .fare-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .fare-breakdown .fare-total {
            font-weight: 700;
            margin-top: 6px;
            padding-top: 8px;
            border-top: 1px solid var(--border-color);
        }

        .btn-ticket-submit {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            margin-top: 8px;
            transition: var(--transition);
        }

        .btn-ticket-submit:hover {
            background: var(--primary-hover);
        }

        .boarding-point-info {
            font-size: 11px;
            color: var(--text-muted);
            margin: -4px 0 12px;
            line-height: 1.45;
        }

        .selected-seats-badge {
            display: inline-block;
            background-color: rgba(99, 102, 241, 0.2);
            color: #818CF8;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 4px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 12px;
            gap: 12px;
        }

        .summary-label {
            color: var(--text-secondary);
            font-size: 12px;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .summary-value {
            text-align: right;
            font-weight: 500;
        }

        .live-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--success);
            font-weight: 600;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--success);
            animation: livePulse 1.5s ease-in-out infinite;
        }

        @keyframes livePulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
            50% { opacity: 0.7; box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        }

        /* Reports */
        .report-filter-block {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .report-custom-dates {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            grid-column: 1 / -1;
        }

        .report-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .report-filter-label {
            font-size: 13px;
            color: var(--text-secondary);
            background-color: rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.15);
            padding: 10px 14px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 20px;
        }

        .report-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 10px;
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
            <div class="sidebar-section-label">Overview</div>
            <a href="/admin" class="sidebar-nav-item active" data-tab="dashboard">
                <span class="sidebar-nav-icon">📊</span>
                Dashboard
            </a>

            <div class="sidebar-section-label">Management</div>
            <a href="/admin#coach-services" class="sidebar-nav-item" data-tab="coach-services">
                <span class="sidebar-nav-icon">🚌</span>
                Coach Services
            </a>
            <a href="/admin#bookings" class="sidebar-nav-item" data-tab="bookings">
                <span class="sidebar-nav-icon">📋</span>
                Bookings Logs
            </a>
            <a href="/admin#cancel-requests" class="sidebar-nav-item" data-tab="cancel-requests">
                <span class="sidebar-nav-icon">📝</span>
                Cancel Requests
            </a>
            <a href="/admin#stations" class="sidebar-nav-item" data-tab="stations">
                <span class="sidebar-nav-icon">🚉</span>
                Stations
            </a>
            <a href="/admin#buses" class="sidebar-nav-item" data-tab="buses">
                <span class="sidebar-nav-icon">🚌</span>
                Coaches
            </a>
            <a href="/admin#routes" class="sidebar-nav-item" data-tab="routes">
                <span class="sidebar-nav-icon">🛣️</span>
                Routes
            </a>
            <a href="/admin#schedules" class="sidebar-nav-item" data-tab="schedules">
                <span class="sidebar-nav-icon">📅</span>
                Schedules
            </a>
            <a href="/admin#promotions" class="sidebar-nav-item" data-tab="promotions">
                <span class="sidebar-nav-icon">🎟️</span>
                Coupons
            </a>
            <a href="/admin#sms-config" class="sidebar-nav-item" data-tab="sms-config">
                <span class="sidebar-nav-icon">📲</span>
                SMS Gateway
            </a>

            <div class="sidebar-section-label">Reports</div>
            <a href="/admin#reports" class="sidebar-nav-item" data-tab="reports">
                <span class="sidebar-nav-icon">📊</span>
                Ticket Reports
            </a>

            <div class="sidebar-spacer"></div>

            <div class="sidebar-section-label">System</div>
            <a href="/admin#database" class="sidebar-nav-item danger" data-tab="database" target="_blank" rel="noopener noreferrer">
                <span class="sidebar-nav-icon">⚙️</span>
                Database Operations
            </a>
            <a href="/admin#profile" class="sidebar-nav-item" data-tab="profile">
                <span class="sidebar-nav-icon">👤</span>
                Profile
            </a>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-main">
        
        <!-- Header Banner -->
        <section class="admin-header" id="admin-header">
            <div class="admin-title-wrap">
                <h1>Control Panel Dashboard</h1>
                <p>Welcome back, {{ Auth::user()->name }}. Manage timetables, seating templates, and database migrations.</p>
            </div>
        </section>

        <!-- Notification messages -->
        @if(session('success'))
            <div class="alert-banner alert-success flash-alert" role="alert">
                <span>✔</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="alert-banner alert-danger flash-alert" role="alert">
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
        const FLASH_ALERT_MS = 10000;

        function initFlashAlerts() {
            document.querySelectorAll('.flash-alert').forEach((el) => {
                setTimeout(() => {
                    el.classList.add('flash-dismissed');
                    const removeEl = () => el.remove();
                    el.addEventListener('transitionend', removeEl, { once: true });
                    setTimeout(removeEl, 500);
                }, FLASH_ALERT_MS);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            initFlashAlerts();

            const navItems = document.querySelectorAll('.sidebar-nav-item');
            const contents = document.querySelectorAll('.admin-tab-content');
            const dashboardMetrics = document.getElementById('dashboard-metrics');
            const adminHeader = document.getElementById('admin-header');
            function resolveAdminTab() {
                const path = window.location.pathname.replace(/\/+$/, '');
                const hashTab = window.location.hash.replace(/^#/, '').trim();

                if (hashTab === 'dashboard') {
                    return 'dashboard';
                }

                if (hashTab && document.getElementById(`tab-content-${hashTab}`)) {
                    return hashTab;
                }

                if (path === '/admin' && !hashTab) {
                    return 'dashboard';
                }

                return 'dashboard';
            }

            function getCurrentAdminTab() {
                const navActive = document.querySelector('.sidebar-nav-item.active');
                const navTab = navActive?.getAttribute('data-tab');
                if (navTab === 'dashboard') {
                    return 'dashboard';
                }
                if (navTab && document.getElementById(`tab-content-${navTab}`)) {
                    return navTab;
                }
                return resolveAdminTab();
            }

            let activeTab = resolveAdminTab();
            const serverTab = @json(old('admin_tab'));
            if (serverTab === 'dashboard' || (serverTab && document.getElementById(`tab-content-${serverTab}`))) {
                activeTab = serverTab;
            }
            
            const switchTab = (tabName, updateHash = false) => {
                navItems.forEach(item => {
                    if (item.getAttribute('data-tab') === tabName) {
                        item.classList.add('active');
                    } else {
                        item.classList.remove('active');
                    }
                });
                
                contents.forEach(c => {
                    c.style.display = 'none';
                });

                if (tabName !== 'dashboard') {
                    const panel = document.getElementById(`tab-content-${tabName}`);
                    if (panel) {
                        panel.style.display = 'grid';
                    }
                }

                if (tabName === 'dashboard') {
                    dashboardMetrics?.style.setProperty('display', 'grid');
                    adminHeader?.style.setProperty('display', 'block');
                } else {
                    dashboardMetrics?.style.setProperty('display', 'none');
                    adminHeader?.style.setProperty('display', 'none');
                }
                
                if (tabName !== 'dashboard') {
                    localStorage.setItem('admin_active_tab', tabName);
                }

                if (updateHash) {
                    window.location.hash = tabName;
                }

                if (window.coachServicesModule) {
                    if (tabName === 'coach-services') {
                        window.coachServicesModule.startPolling();
                    } else {
                        window.coachServicesModule.stopPolling();
                    }
                }

                if (window.bookingsLogsModule) {
                    if (tabName === 'bookings') {
                        window.bookingsLogsModule.startPolling();
                    } else {
                        window.bookingsLogsModule.stopPolling();
                    }
                }

                if (window.cancelRequestsLogsModule) {
                    if (tabName === 'cancel-requests') {
                        window.cancelRequestsLogsModule.startPolling();
                    } else {
                        window.cancelRequestsLogsModule.stopPolling();
                    }
                }
            };
            
            navItems.forEach(item => {
                item.addEventListener('click', (event) => {
                    if (item.target === '_blank') {
                        return;
                    }

                    event.preventDefault();
                    const tabName = item.getAttribute('data-tab');
                    switchTab(tabName, true);
                });
            });
            
            switchTab(activeTab);

            document.querySelector('.admin-main')?.addEventListener('submit', (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                const method = (form.getAttribute('method') || 'get').toLowerCase();
                if (method !== 'post') {
                    return;
                }

                const tab = getCurrentAdminTab();
                let input = form.querySelector('input[name="admin_tab"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'admin_tab';
                    form.appendChild(input);
                }
                input.value = tab;
            });
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

                if (formId === 'route-form' && typeof window.loadRoutePointsForm === 'function') {
                    window.loadRoutePointsForm([], []);
                }
            }

            if (formId === 'booking-form') {
                const scheduleGroup = document.getElementById('booking-schedule-group');
                const scheduleSelect = scheduleGroup?.querySelector('select[name="schedule_id"]');
                if (scheduleGroup) {
                    scheduleGroup.style.display = config.mode === 'edit' ? 'none' : 'flex';
                }
                if (scheduleSelect) {
                    if (config.mode === 'edit') {
                        scheduleSelect.required = false;
                        scheduleSelect.disabled = true;
                    } else {
                        scheduleSelect.required = true;
                        scheduleSelect.disabled = false;
                    }
                }
            }

            if (formId === 'route-form' && typeof window.loadRoutePointsForm === 'function') {
                window.loadRoutePointsForm(
                    config.mode === 'edit' ? (config.boarding_points || []) : [],
                    config.mode === 'edit' ? (config.dropping_points || []) : []
                );
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
