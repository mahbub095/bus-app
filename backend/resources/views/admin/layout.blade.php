<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    @include('admin.partials.theme-init')
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
            color: var(--text-primary);
            background-color: var(--sidebar-hover);
        }

        .sidebar-nav-item.active {
            color: var(--primary);
            background-color: var(--sidebar-active-bg);
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
            background-color: var(--bg-header);
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
            background-color: var(--bg-btn-secondary);
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
            background-color: var(--bg-btn-secondary-hover);
            border-color: var(--border-active);
        }

        .theme-toggle-btn {
            width: 42px;
            height: 42px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-color);
            background-color: var(--bg-btn-secondary);
            color: var(--primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .theme-toggle-btn:hover {
            border-color: var(--primary);
            background-color: var(--sidebar-active-bg);
        }

        .theme-icon {
            width: 18px;
            height: 18px;
        }

        [data-theme="light"] .theme-icon-light {
            display: none;
        }

        [data-theme="dark"] .theme-icon-dark {
            display: none;
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
            background: linear-gradient(to right, var(--title-gradient-start), var(--title-gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            color: var(--text-primary);
            margin-top: 2px;
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
            display: flex;
            flex-direction: column;
        }

        .admin-panel .table-wrapper {
            flex: 1;
        }

        /* Custom Pagination Styling */
        .custom-pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }

        .pagination-list {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 6px;
            align-items: center;
        }

        .page-item {
            display: inline-flex;
        }

        .page-link, .page-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-color);
            background-color: var(--bg-btn-secondary);
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .page-link:hover {
            border-color: var(--border-active);
            background-color: var(--bg-btn-secondary-hover);
            color: var(--primary);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }

        .page-item.disabled .page-link, .page-ellipsis {
            opacity: 0.5;
            cursor: not-allowed;
            border-color: var(--border-color);
            background-color: transparent;
            color: var(--text-muted);
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
            background-color: var(--bg-panel-alt);
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
            background-color: var(--bg-input);
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

        /* Select option styling with theme colors */
        .coupon-input option {
            background-color: var(--bg-card);
            color: var(--text-primary);
        }

        .coupon-input option:checked {
            background: linear-gradient(var(--primary), var(--primary));
            background-color: var(--primary);
            color: #fff;
        }

        .coupon-input option:hover {
            background: linear-gradient(var(--border-active), var(--border-active));
            background-color: var(--border-active);
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
            background-color: var(--bg-btn-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: var(--bg-btn-secondary-hover);
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
            background-color: var(--bg-terminal);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-top: 24px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8);
        }

        .terminal-header {
            background-color: var(--bg-terminal-header);
            border-bottom: 1px solid var(--border-color);
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
            background-color: var(--bg-panel-alt);
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
            background-color: var(--bg-footer);
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
            color: var(--text-primary);
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
            background-color: var(--bg-seat-panel);
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
            background-color: var(--bg-seat-map);
            border: 2px solid var(--border-seat);
            border-radius: 20px;
            padding: 24px;
            max-width: 360px;
            margin: 0 auto;
        }

        .bus-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px dashed var(--border-seat);
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
            border-bottom: 1px dashed var(--border-seat);
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
            background-color: var(--bg-card);
            color: var(--text-primary);
        }

        .ticket-booking-panel select option:checked {
            background: linear-gradient(var(--primary), var(--primary));
            background-color: var(--primary);
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
            background-color: var(--bg-input-elevated);
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
            background-color: var(--sidebar-active-bg);
            color: var(--text-primary);
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

        /* Dashboard overview */
        .dashboard-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .dashboard-period-label {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 0;
        }

        .dashboard-filter {
            position: relative;
        }

        .dashboard-filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            color: var(--primary);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .dashboard-filter-btn:hover,
        .dashboard-filter-btn[aria-expanded="true"] {
            border-color: var(--primary);
            background-color: rgba(99, 102, 241, 0.1);
            box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.15);
        }

        .dashboard-filter-btn:disabled {
            opacity: 0.6;
            cursor: wait;
        }

        .dashboard-filter-icon {
            width: 16px;
            height: 16px;
        }

        .dashboard-filter-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 180px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-lg);
            padding: 6px 0;
            z-index: 50;
        }

        .dashboard-filter-option {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 10px 16px;
            border: none;
            background: transparent;
            color: var(--text-primary);
            font-size: 13px;
            text-align: left;
            cursor: pointer;
            transition: var(--transition);
        }

        .dashboard-filter-option:hover {
            background-color: rgba(99, 102, 241, 0.08);
            color: var(--primary);
        }

        .dashboard-filter-option.is-active {
            color: var(--primary);
            font-weight: 600;
        }

        .dashboard-filter-option.is-active::before {
            content: '✓';
            margin-right: 8px;
            font-size: 12px;
        }

        .dashboard-filter-option:not(.is-active)::before {
            content: '';
            display: inline-block;
            width: 18px;
            margin-right: 0;
        }

        .dashboard-charts-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        @media (max-width: 1100px) {
            .dashboard-charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .dashboard-chart-panel {
            min-height: 320px;
            display: flex;
            flex-direction: column;
        }

        .dashboard-chart-title {
            font-family: var(--font-display);
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 16px;
            color: var(--text-primary);
        }

        .dashboard-chart-wrap {
            position: relative;
            flex: 1;
            min-height: 240px;
        }

        .dashboard-chart-wrap-wide {
            min-height: 260px;
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
                @include('admin.partials.theme-toggle')

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
            @if(Auth::user()->hasMenuPermission('coach-services'))
            <a href="/admin#coach-services" class="sidebar-nav-item" data-tab="coach-services">
                <span class="sidebar-nav-icon">🚌</span>
                Coach Services
            </a>
            @endif
            @if(Auth::user()->hasMenuPermission('bookings'))
            <a href="/admin#bookings" class="sidebar-nav-item" data-tab="bookings">
                <span class="sidebar-nav-icon">📋</span>
                Bookings Logs
            </a>
            @endif
            @if(Auth::user()->hasMenuPermission('cancel-requests'))
            <a href="/admin#cancel-requests" class="sidebar-nav-item" data-tab="cancel-requests">
                <span class="sidebar-nav-icon">📝</span>
                Cancel Requests
            </a>
            @endif
            @if(Auth::user()->hasMenuPermission('stations'))
            <a href="/admin#stations" class="sidebar-nav-item" data-tab="stations">
                <span class="sidebar-nav-icon">🚉</span>
                Stations
            </a>
            @endif
            @if(Auth::user()->hasMenuPermission('buses'))
            <a href="/admin#buses" class="sidebar-nav-item" data-tab="buses">
                <span class="sidebar-nav-icon">🚌</span>
                Coaches
            </a>
            @endif
            @if(Auth::user()->hasMenuPermission('routes'))
            <a href="/admin#routes" class="sidebar-nav-item" data-tab="routes">
                <span class="sidebar-nav-icon">🛣️</span>
                Routes
            </a>
            @endif
            @if(Auth::user()->hasMenuPermission('schedules'))
            <a href="/admin#schedules" class="sidebar-nav-item" data-tab="schedules">
                <span class="sidebar-nav-icon">📅</span>
                Schedules
            </a>
            @endif
            @if(Auth::user()->hasMenuPermission('promotions'))
            <a href="/admin#promotions" class="sidebar-nav-item" data-tab="promotions">
                <span class="sidebar-nav-icon">🎟️</span>
                Coupons
            </a>
            @endif
            @if(Auth::user()->hasMenuPermission('users'))
            <a href="/admin#users" class="sidebar-nav-item" data-tab="users">
                <span class="sidebar-nav-icon">👥</span>
                Users & Roles
            </a>
            @endif

            <div class="sidebar-section-label">Reports</div>
            @if(Auth::user()->hasMenuPermission('reports'))
            <a href="/admin#reports" class="sidebar-nav-item" data-tab="reports">
                <span class="sidebar-nav-icon">📊</span>
                Ticket Reports
            </a>
            @endif

            <div class="sidebar-spacer"></div>

            <div class="sidebar-section-label">System</div>
            @if(Auth::user()->isSuperAdmin())
            <a href="/admin#site-settings" class="sidebar-nav-item" data-tab="site-settings">
                <span class="sidebar-nav-icon">⚙️</span>
                Site Settings
            </a>
            <a href="/admin#gateways" class="sidebar-nav-item" data-tab="gateways">
                <span class="sidebar-nav-icon">🔌</span>
                Integrations & Gateways
            </a>
            @endif
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
                <p>Welcome back, {{ Auth::user()->name }}. Manage timetables, seating templates, and system settings.</p>
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
            <p>{{ $siteSettings['footer_copyright'] ?? '© 2026 SonyaBus Enterprise Ltd. All rights reserved.' }} Admin Dashboard Control Portal.</p>
        </div>
    </footer>

    <!-- Data bridge for layout.js -->
    <script>
        window.AdminLayout = {
            serverTab: @json(old('admin_tab'))
        };
    </script>
    @vite('resources/js/admin/layout.js')
</body>
</html>
