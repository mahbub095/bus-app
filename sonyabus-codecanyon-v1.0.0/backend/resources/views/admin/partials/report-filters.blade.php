@php
    $operators = $buses->pluck('operator_name')->unique()->sort()->values();
@endphp

<div class="report-filter-block">
    <div class="input-group">
        <label>Period</label>
        <select class="coupon-input report-period" data-report="{{ $reportType }}">
            <option value="weekly">Weekly (This Week)</option>
            <option value="monthly" selected>Monthly (This Month)</option>
            <option value="quarterly">Quarterly (This Quarter)</option>
            <option value="yearly">Yearly (This Year)</option>
            <option value="custom">Custom Date Range</option>
        </select>
    </div>

    <div class="report-custom-dates" id="{{ $reportType }}-custom-dates" style="display: none;">
        <div class="input-group">
            <label>From Date</label>
            <input type="date" class="coupon-input report-from-date" data-report="{{ $reportType }}">
        </div>
        <div class="input-group">
            <label>To Date</label>
            <input type="date" class="coupon-input report-to-date" data-report="{{ $reportType }}">
        </div>
    </div>

    <div class="input-group">
        <label>Coach Type</label>
        <select class="coupon-input report-coach-type" data-report="{{ $reportType }}">
            <option value="All">All Coach Types</option>
            <option value="AC">AC</option>
            <option value="Non AC">Non AC</option>
        </select>
    </div>

    <div class="input-group">
        <label>Payment Method</label>
        <select class="coupon-input report-payment-method" data-report="{{ $reportType }}">
            <option value="All">All Methods</option>
            <option value="bKash">bKash</option>
            <option value="Nagad">Nagad</option>
            <option value="Card">Card</option>
            <option value="Cash">Cash</option>
        </select>
    </div>

    <div class="input-group">
        <label>Route</label>
        <select class="coupon-input report-route-id" data-report="{{ $reportType }}">
            <option value="All">All Routes</option>
            @foreach($routes as $route)
                <option value="{{ $route->id }}">{{ $route->from }} → {{ $route->to }}</option>
            @endforeach
        </select>
    </div>

    <div class="input-group">
        <label>Operator</label>
        <select class="coupon-input report-operator" data-report="{{ $reportType }}">
            <option value="All">All Operators</option>
            @foreach($operators as $operator)
                <option value="{{ $operator }}">{{ $operator }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="report-actions">
    <button type="button" class="btn btn-primary report-generate-btn" data-report="{{ $reportType }}">
        Generate Report
    </button>
    <button type="button" class="btn btn-secondary report-export-excel-btn" data-report="{{ $reportType }}" disabled>
        Export Excel
    </button>
    <button type="button" class="btn btn-secondary report-export-pdf-btn" data-report="{{ $reportType }}" disabled>
        Export PDF
    </button>
</div>

<div class="report-filter-label" id="{{ $reportType }}-filter-label" style="display: none;"></div>

<div class="report-summary-grid" id="{{ $reportType }}-summary" style="display: none;"></div>

<div class="admin-panel" id="{{ $reportType }}-table-panel" style="display: none; margin-top: 20px;">
    <h3 class="admin-panel-title">{{ $reportTitle }} Details</h3>
    <div class="table-wrapper">
        <table class="admin-table" id="{{ $reportType }}-table">
            <thead id="{{ $reportType }}-table-head"></thead>
            <tbody id="{{ $reportType }}-table-body"></tbody>
        </table>
    </div>
</div>

<div class="notice-info-box" id="{{ $reportType }}-empty-hint">
    Select filters and click <strong>Generate Report</strong> to view {{ strtolower($reportTitle) }} data.
</div>
