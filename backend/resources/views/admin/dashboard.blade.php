@extends('admin.layout')

@section('content')
    <!-- Dashboard Metrics Cards - Shows only on dashboard (hidden when tabs are active) -->
    <section class="admin-stats-grid dashboard-metrics" id="dashboard-metrics">
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

    <!-- Sub-tab 0: Available Coach Services (Live) -->
    <section class="admin-tab-content" id="tab-content-coach-services">
        @include('admin.partials.coach-services')
    </section>

    <!-- Sub-tab 1: Bookings Logs -->
    <section class="admin-tab-content" id="tab-content-bookings">
        @include('admin.partials.bookings')
    </section>

    <!-- Sub-tab 2: Cancel Requests -->
    <section class="admin-tab-content" id="tab-content-cancel-requests">
        @include('admin.partials.cancel-requests')
    </section>

    <!-- Sub-tab 3: Stations Terminal Management -->
    <section class="admin-tab-content" id="tab-content-stations">
        @include('admin.partials.stations')
    </section>

    <!-- Sub-tab 4: Bus Fleet Registration -->
    <section class="admin-tab-content" id="tab-content-buses">
        @include('admin.partials.buses')
    </section>

    <!-- Sub-tab 5: Transport Route Connections -->
    <section class="admin-tab-content" id="tab-content-routes">
        @include('admin.partials.routes')
    </section>

    <!-- Sub-tab 6: Departure Schedules timetables -->
    <section class="admin-tab-content" id="tab-content-schedules">
        @include('admin.partials.schedules')
    </section>

    <!-- Sub-tab 7: Coupon Vouchers generation -->
    <section class="admin-tab-content" id="tab-content-promotions">
        @include('admin.partials.promotions')
    </section>

    <!-- Sub-tab 8: Reports -->
    <section class="admin-tab-content" id="tab-content-reports">
        @include('admin.partials.reports')
    </section>

    <!-- Sub-tab 10: System Database Migrations & Artisan hooks -->
    <section class="admin-tab-content" id="tab-content-database">
        @include('admin.partials.database')
    </section>

    <!-- Sub-tab 11: Admin profile -->
    <section class="admin-tab-content" id="tab-content-profile">
        @include('admin.partials.profile')
    </section>
@endsection
