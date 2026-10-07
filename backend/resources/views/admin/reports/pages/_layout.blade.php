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
@extends('admin.layout')

@section('title', $reportTitle . ' | SonyaBus Admin')

@php $activeTab = 'reports'; @endphp

@push('styles')
<style>
    /* ── Report detail page overrides ── */
    .report-detail-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }

    .report-detail-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background-color: var(--bg-btn-secondary);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: var(--transition);
        flex-shrink: 0;
    }

    .report-detail-back:hover {
        border-color: var(--border-active);
        color: var(--text-primary);
        background-color: var(--bg-btn-secondary-hover);
    }

    .report-detail-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }

    .report-detail-title-block h1 {
        font-family: var(--font-display);
        font-size: 24px;
        font-weight: 800;
        background: linear-gradient(to right, var(--title-gradient-start), var(--title-gradient-end));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 4px;
    }

    .report-detail-title-block p {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .report-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 10px;
    }

    .notice-info-box {
        background: rgba(99, 102, 241, 0.07);
        border: 1px dashed rgba(99, 102, 241, 0.25);
        border-radius: var(--border-radius-sm);
        padding: 18px 22px;
        font-size: 13px;
        color: var(--text-secondary);
        text-align: center;
        margin-top: 10px;
    }
</style>
@endpush

@section('content')

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

@endsection

@push('scripts')
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
@endpush
