{{-- ======================================================================
     Admin Sidebar Navigation
     Used in: admin.layout  AND  admin.reports.pages._layout
     Pass $activeTab (optional) to pre-highlight a specific item.
     On the main dashboard, layout.js handles active state via JS.
     On standalone pages (report detail), pass $activeTab = 'reports'.
====================================================================== --}}
<aside class="admin-sidebar">
    {{-- Sidebar logo — shown on mobile when the top navbar is scrolled away --}}
    <a href="/admin" class="sidebar-brand">
        @if(!empty($siteSettings['logo_url'] ?? null))
            <img src="{{ $siteSettings['logo_url'] }}"
                 alt="{{ $siteSettings['logo_text'] ?? 'SonyaBus' }}"
                 class="sidebar-brand-img">
        @elseif(!empty($siteSettings['logo_text'] ?? null))
            <div class="logo-icon" style="width:30px;height:30px;font-size:15px;border-radius:8px;flex-shrink:0;">
                {{ strtoupper(substr($siteSettings['logo_text'], 0, 1)) }}
            </div>
            <span class="sidebar-brand-text">
                {{ $siteSettings['logo_text'] }}
            </span>
        @else
            <div class="logo-icon" style="width:30px;height:30px;font-size:15px;border-radius:8px;flex-shrink:0;">S</div>
            <span class="sidebar-brand-text">SonyaBus</span>
        @endif
    </a>

    <div class="sidebar-section-label">Overview</div>
    <a href="/admin" class="sidebar-nav-item {{ ($activeTab ?? '') === 'dashboard' ? 'active' : '' }}" data-tab="dashboard">
        <span class="sidebar-nav-icon">📊</span>
        Dashboard
    </a>

    <div class="sidebar-section-label">Management</div>
    @if(Auth::user()->hasMenuPermission('coach-services'))
    <a href="/admin#coach-services" class="sidebar-nav-item {{ ($activeTab ?? '') === 'coach-services' ? 'active' : '' }}" data-tab="coach-services">
        <span class="sidebar-nav-icon">🚌</span>
        Coach Services
    </a>
    @endif
    @if(Auth::user()->hasMenuPermission('bookings'))
    <a href="/admin#bookings" class="sidebar-nav-item {{ ($activeTab ?? '') === 'bookings' ? 'active' : '' }}" data-tab="bookings">
        <span class="sidebar-nav-icon">📋</span>
        Bookings Logs
    </a>
    @endif
    @if(Auth::user()->hasMenuPermission('cancel-requests'))
    <a href="/admin#cancel-requests" class="sidebar-nav-item {{ ($activeTab ?? '') === 'cancel-requests' ? 'active' : '' }}" data-tab="cancel-requests">
        <span class="sidebar-nav-icon">📝</span>
        Cancel Requests
    </a>
    @endif
    @if(Auth::user()->hasMenuPermission('stations'))
    <a href="/admin#stations" class="sidebar-nav-item {{ ($activeTab ?? '') === 'stations' ? 'active' : '' }}" data-tab="stations">
        <span class="sidebar-nav-icon">🚉</span>
        Stations
    </a>
    @endif
    @if(Auth::user()->hasMenuPermission('buses'))
    <a href="/admin#buses" class="sidebar-nav-item {{ ($activeTab ?? '') === 'buses' ? 'active' : '' }}" data-tab="buses">
        <span class="sidebar-nav-icon">🚌</span>
        Coaches
    </a>
    @endif
    @if(Auth::user()->hasMenuPermission('routes'))
    <a href="/admin#routes" class="sidebar-nav-item {{ ($activeTab ?? '') === 'routes' ? 'active' : '' }}" data-tab="routes">
        <span class="sidebar-nav-icon">🛣️</span>
        Routes
    </a>
    @endif
    @if(Auth::user()->hasMenuPermission('schedules'))
    <a href="/admin#schedules" class="sidebar-nav-item {{ ($activeTab ?? '') === 'schedules' ? 'active' : '' }}" data-tab="schedules">
        <span class="sidebar-nav-icon">📅</span>
        Schedules
    </a>
    @endif
    @if(Auth::user()->hasMenuPermission('promotions'))
    <a href="/admin#promotions" class="sidebar-nav-item {{ ($activeTab ?? '') === 'promotions' ? 'active' : '' }}" data-tab="promotions">
        <span class="sidebar-nav-icon">🎟️</span>
        Coupons
    </a>
    @endif
    @if(Auth::user()->hasMenuPermission('users'))
    <a href="/admin#users" class="sidebar-nav-item {{ ($activeTab ?? '') === 'users' ? 'active' : '' }}" data-tab="users">
        <span class="sidebar-nav-icon">👥</span>
        Users & Roles
    </a>
    @endif

    <div class="sidebar-section-label">Reports</div>
    @if(Auth::user()->hasMenuPermission('reports'))
    <a href="/admin#reports" class="sidebar-nav-item {{ ($activeTab ?? '') === 'reports' ? 'active' : '' }}" data-tab="reports">
        <span class="sidebar-nav-icon">📊</span>
        Ticket Reports
    </a>
    @endif

    <div class="sidebar-spacer"></div>

    <div class="sidebar-section-label">System</div>
    @if(Auth::user()->isSuperAdmin())
    <a href="/admin#license" class="sidebar-nav-item {{ ($activeTab ?? '') === 'license' ? 'active' : '' }}" data-tab="license">
        <span class="sidebar-nav-icon">🔑</span>
        License
    </a>
    <a href="/admin#site-settings" class="sidebar-nav-item {{ ($activeTab ?? '') === 'site-settings' ? 'active' : '' }}" data-tab="site-settings">
        <span class="sidebar-nav-icon">⚙️</span>
        Site Settings
    </a>
    <a href="/admin#gateways" class="sidebar-nav-item {{ ($activeTab ?? '') === 'gateways' ? 'active' : '' }}" data-tab="gateways">
        <span class="sidebar-nav-icon">🔌</span>
        Integrations & Gateways
    </a>
    @endif
    <a href="/admin#profile" class="sidebar-nav-item {{ ($activeTab ?? '') === 'profile' ? 'active' : '' }}" data-tab="profile">
        <span class="sidebar-nav-icon">👤</span>
        Profile
    </a>
</aside>
