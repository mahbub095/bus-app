@include('admin.reports.pages._layout', [
    'reportType'  => 'booking',
    'reportTitle' => 'Booking Report',
    'reportDesc'  => 'All active bookings (PAID, SOLD, BOOKED) with full status breakdown.',
    'reportIcon'  => '📋',
    'reportColor' => 'rgba(16,185,129,0.12)',
])
