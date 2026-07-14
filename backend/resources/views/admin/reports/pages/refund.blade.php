@include('admin.reports.pages._layout', [
    'reportType'  => 'refund',
    'reportTitle' => 'Refund Report',
    'reportDesc'  => 'Online-payment cancellations (bKash, Nagad, Card) eligible for refund, with invoice IDs.',
    'reportIcon'  => '↩️',
    'reportColor' => 'rgba(168,85,247,0.12)',
])
