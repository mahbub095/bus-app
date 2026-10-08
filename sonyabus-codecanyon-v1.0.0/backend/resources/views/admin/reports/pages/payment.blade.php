@include('admin.reports.pages._layout', [
    'reportType'  => 'payment',
    'reportTitle' => 'Payment Report',
    'reportDesc'  => 'Payment transaction log broken down by method with invoice IDs and amounts.',
    'reportIcon'  => '💳',
    'reportColor' => 'rgba(20,184,166,0.12)',
])
