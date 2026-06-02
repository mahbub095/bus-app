@extends('admin.layout')

@section('content')
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

    <!-- Sub-tab 8: SMS Config -->
    <section class="admin-tab-content" id="tab-content-sms-config">
        @include('admin.partials.sms-config')
    </section>

    <!-- Sub-tab 9: Reports -->
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
