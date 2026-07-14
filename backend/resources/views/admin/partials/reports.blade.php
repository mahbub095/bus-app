{{-- ======================================================================
     Reports — Card Gallery Index
     Each card links to its own full-page report view at /admin/reports/{type}
====================================================================== --}}

<div class="admin-header" style="padding-top: 0; margin-bottom: 28px;">
    <div class="admin-title-wrap">
        <h1>Reports</h1>
        <p>Select a report to view filters, generate data, and export.</p>
    </div>
</div>

@php
$reportCards = [
    [
        'type'    => 'selling',
        'title'   => 'Ticket Selling Report',
        'desc'    => 'All paid ticket sales by date, route, coach type, and payment method.',
        'icon'    => '🎫',
        'color'   => 'rgba(99,102,241,0.12)',
        'route'   => 'admin.reports.page.selling',
    ],
    [
        'type'    => 'booking',
        'title'   => 'Booking Report',
        'desc'    => 'Active bookings (PAID, SOLD, BOOKED) with full status breakdown.',
        'icon'    => '📋',
        'color'   => 'rgba(16,185,129,0.12)',
        'route'   => 'admin.reports.page.booking',
    ],
    [
        'type'    => 'revenue',
        'title'   => 'Revenue Report',
        'desc'    => 'Total revenue from paid bookings, split by AC / Non-AC and method.',
        'icon'    => '💰',
        'color'   => 'rgba(245,158,11,0.12)',
        'route'   => 'admin.reports.page.revenue',
    ],
    [
        'type'    => 'passenger',
        'title'   => 'Passenger Report',
        'desc'    => 'Passenger travel history with boarding, dropping, gender details.',
        'icon'    => '👤',
        'color'   => 'rgba(139,92,246,0.12)',
        'route'   => 'admin.reports.page.passenger',
    ],
    [
        'type'    => 'seat-occupancy',
        'title'   => 'Seat Occupancy Report',
        'desc'    => 'Per-schedule booked vs available seats and occupancy percentage.',
        'icon'    => '💺',
        'color'   => 'rgba(6,182,212,0.12)',
        'route'   => 'admin.reports.page.seat-occupancy',
    ],
    [
        'type'    => 'cancellation',
        'title'   => 'Cancellation Report',
        'desc'    => 'Full cancellation log with booking date, route, and fare released.',
        'icon'    => '❌',
        'color'   => 'rgba(239,68,68,0.12)',
        'route'   => 'admin.reports.page.cancellation',
    ],
    [
        'type'    => 'cancel',
        'title'   => 'Ticket Cancel Report',
        'desc'    => 'Cancelled tickets export — same data with Excel and PDF support.',
        'icon'    => '🚫',
        'color'   => 'rgba(239,68,68,0.08)',
        'route'   => 'admin.reports.page.cancel',
    ],
    [
        'type'    => 'refund',
        'title'   => 'Refund Report',
        'desc'    => 'Online-payment cancellations eligible for refund with invoice IDs.',
        'icon'    => '↩️',
        'color'   => 'rgba(168,85,247,0.12)',
        'route'   => 'admin.reports.page.refund',
    ],
    [
        'type'    => 'payment',
        'title'   => 'Payment Report',
        'desc'    => 'Payment transaction log broken down by method with amounts.',
        'icon'    => '💳',
        'color'   => 'rgba(20,184,166,0.12)',
        'route'   => 'admin.reports.page.payment',
    ],
    [
        'type'    => 'route-sales',
        'title'   => 'Route-wise Sales Report',
        'desc'    => 'Aggregated sales per route — bookings, seats, and revenue ranked.',
        'icon'    => '🛣️',
        'color'   => 'rgba(245,158,11,0.10)',
        'route'   => 'admin.reports.page.route-sales',
    ],
    [
        'type'    => 'agent-sales',
        'title'   => 'Agent / Counter Sales',
        'desc'    => 'Aggregated sales per operator with routes covered and revenue.',
        'icon'    => '🏪',
        'color'   => 'rgba(59,130,246,0.12)',
        'route'   => 'admin.reports.page.agent-sales',
    ],
];
@endphp

<div class="report-cards-grid">
    @foreach($reportCards as $card)
    <a href="{{ route($card['route']) }}" class="report-card">
        <div class="report-card-body">
            <div class="report-card-icon" style="background-color: {{ $card['color'] }}">
                {{ $card['icon'] }}
            </div>
            <div class="report-card-info">
                <div class="report-card-title">{{ $card['title'] }}</div>
                <div class="report-card-desc">{{ $card['desc'] }}</div>
            </div>
        </div>
        <div class="report-card-footer">
            <span>Show</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 18l6-6-6-6"/>
            </svg>
        </div>
    </a>
    @endforeach
</div>
