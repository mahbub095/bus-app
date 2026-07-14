import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin/layout.js',
                'resources/js/admin/dashboard-overview.js',
                'resources/js/admin/bookings.js',
                'resources/js/admin/buses.js',
                'resources/js/admin/cancel-requests.js',
                'resources/js/admin/coach-services.js',
                'resources/js/admin/gateway-settings.js',
                'resources/js/admin/reports.js',
                'resources/js/admin/routes.js',
                'resources/js/admin/site-settings.js',
                'resources/js/admin/users.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
