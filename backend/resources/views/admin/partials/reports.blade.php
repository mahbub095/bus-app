<div class="admin-sections-layout" style="grid-column: 1 / -1; display: block;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Ticket Selling Report</h3>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
            View all paid ticket sales with date, coach type, route, and payment filters. Export to Excel or PDF.
        </p>

        @include('admin.partials.report-filters', [
            'reportType' => 'selling',
            'reportTitle' => 'Ticket Selling',
        ])
    </div>

</div>

<div class="admin-sections-layout" style="grid-column: 1 / -1; display: block; margin-top: 30px;">

    <div class="admin-panel">
        <h3 class="admin-panel-title">Ticket Cancel Report</h3>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
            View all cancelled tickets with the same filters. Export cancellation logs to Excel or PDF.
        </p>

        @include('admin.partials.report-filters', [
            'reportType' => 'cancel',
            'reportTitle' => 'Ticket Cancel',
        ])
    </div>

</div>

<script>
    window.Reports = {
        routes: {
            selling: {
                preview: @json(route('admin.reports.selling.preview')),
                excel: @json(route('admin.reports.selling.excel')),
                pdf: @json(route('admin.reports.selling.pdf')),
            },
            cancel: {
                preview: @json(route('admin.reports.cancel.preview')),
                excel: @json(route('admin.reports.cancel.excel')),
                pdf: @json(route('admin.reports.cancel.pdf')),
            },
        },
    };
</script>
@vite('resources/js/admin/reports.js')
