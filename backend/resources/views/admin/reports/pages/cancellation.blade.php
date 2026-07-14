@include('admin.reports.pages._layout', [
    'reportType'  => 'cancellation',
    'reportTitle' => 'Cancellation Report',
    'reportDesc'  => 'Detailed cancellation log with booking dates, routes, and fare amounts released.',
    'reportIcon'  => '❌',
    'reportColor' => 'rgba(239,68,68,0.12)',
])
