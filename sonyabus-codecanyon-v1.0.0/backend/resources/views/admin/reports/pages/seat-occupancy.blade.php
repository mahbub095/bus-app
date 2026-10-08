@include('admin.reports.pages._layout', [
    'reportType'  => 'seat-occupancy',
    'reportTitle' => 'Seat Occupancy Report',
    'reportDesc'  => 'Per-schedule seat occupancy rates showing booked vs available seats and occupancy percentage.',
    'reportIcon'  => '💺',
    'reportColor' => 'rgba(6,182,212,0.12)',
])
