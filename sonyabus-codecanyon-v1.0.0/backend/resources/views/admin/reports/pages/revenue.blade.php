@include('admin.reports.pages._layout', [
    'reportType'  => 'revenue',
    'reportTitle' => 'Revenue Report',
    'reportDesc'  => 'Total revenue collected from paid bookings, broken down by route, coach type, and payment method.',
    'reportIcon'  => '💰',
    'reportColor' => 'rgba(245,158,11,0.12)',
])
