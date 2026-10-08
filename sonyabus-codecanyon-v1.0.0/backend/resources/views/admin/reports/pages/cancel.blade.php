@include('admin.reports.pages._layout', [
    'reportType'  => 'cancel',
    'reportTitle' => 'Ticket Cancel Report',
    'reportDesc'  => 'Export cancelled ticket logs with full passenger, route, and payment details.',
    'reportIcon'  => '🚫',
    'reportColor' => 'rgba(239,68,68,0.08)',
])
