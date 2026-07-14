@include('admin.reports.pages._layout', [
    'reportType'  => 'route-sales',
    'reportTitle' => 'Route-wise Sales Report',
    'reportDesc'  => 'Aggregated sales per route showing total bookings, seats sold, and revenue — sorted by revenue.',
    'reportIcon'  => '🛣️',
    'reportColor' => 'rgba(245,158,11,0.10)',
])
