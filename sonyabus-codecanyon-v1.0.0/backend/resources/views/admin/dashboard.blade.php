@extends('admin.layout')

@section('content')
    @include('admin.partials.dashboard-overview')

    <!-- Sub-tab 0: Available Coach Services (Live) -->
    @if(Auth::user()->hasMenuPermission('coach-services'))
    <section class="admin-tab-content" id="tab-content-coach-services">
        @include('admin.partials.coach-services')
    </section>
    @endif

    <!-- Sub-tab 1: Bookings Logs -->
    @if(Auth::user()->hasMenuPermission('bookings'))
    <section class="admin-tab-content" id="tab-content-bookings">
        @include('admin.partials.bookings')
    </section>
    @endif

    <!-- Sub-tab 2: Cancel Requests -->
    @if(Auth::user()->hasMenuPermission('cancel-requests'))
    <section class="admin-tab-content" id="tab-content-cancel-requests">
        @include('admin.partials.cancel-requests')
    </section>
    @endif

    <!-- Sub-tab 3: Stations Terminal Management -->
    @if(Auth::user()->hasMenuPermission('stations'))
    <section class="admin-tab-content" id="tab-content-stations">
        @include('admin.partials.stations')
    </section>
    @endif

    <!-- Sub-tab 4: Bus Fleet Registration -->
    @if(Auth::user()->hasMenuPermission('buses'))
    <section class="admin-tab-content" id="tab-content-buses">
        @include('admin.partials.buses')
    </section>
    @endif

    <!-- Sub-tab 5: Transport Route Connections -->
    @if(Auth::user()->hasMenuPermission('routes'))
    <section class="admin-tab-content" id="tab-content-routes">
        @include('admin.partials.routes')
    </section>
    @endif

    <!-- Sub-tab 6: Departure Schedules timetables -->
    @if(Auth::user()->hasMenuPermission('schedules'))
    <section class="admin-tab-content" id="tab-content-schedules">
        @include('admin.partials.schedules')
    </section>
    @endif

    <!-- Sub-tab 7: Coupon Vouchers generation -->
    @if(Auth::user()->hasMenuPermission('promotions'))
    <section class="admin-tab-content" id="tab-content-promotions">
        @include('admin.partials.promotions')
    </section>
    @endif

    <!-- Sub-tab 9: Users & Roles management -->
    @if(Auth::user()->hasMenuPermission('users'))
    <section class="admin-tab-content" id="tab-content-users">
        @include('admin.partials.users')
    </section>
    @endif

    <!-- Sub-tab 8: Reports -->
    @if(Auth::user()->hasMenuPermission('reports'))
    <section class="admin-tab-content" id="tab-content-reports">
        @include('admin.partials.reports')
    </section>
    @endif

    @if(Auth::user()->isSuperAdmin())
    <!-- Sub-tab 12: Site Settings (Footer, Title, Favicon, Maintenance, SEO) -->
    <section class="admin-tab-content" id="tab-content-site-settings">
        @include('admin.partials.site-settings')
    </section>

    <!-- Sub-tab 13: Integrations & Gateways (SMS, Mail, ZiniPay) -->
    <section class="admin-tab-content" id="tab-content-gateways">
        @include('admin.partials.gateway-settings')
    </section>
    @endif

    <!-- Sub-tab 11: Admin profile -->
    <section class="admin-tab-content" id="tab-content-profile">
        @include('admin.partials.profile')
    </section>
@endsection
