<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait RedirectsToAdminTab
{
    /**
     * Redirect to the admin dashboard, preserving the active sidebar tab via URL hash.
     */
    protected function adminTabRedirect(Request $request): \Illuminate\Http\RedirectResponse
    {
        $allowed = [
            'dashboard',
            'coach-services',
            'bookings',
            'cancel-requests',
            'stations',
            'buses',
            'routes',
            'schedules',
            'promotions',
            'reports',
            'profile',
            'users',
        ];

        $tab = $request->input('admin_tab', 'coach-services');
        if (!in_array($tab, $allowed, true)) {
            $tab = 'coach-services';
        }

        return redirect()->route('admin.dashboard')->withFragment($tab);
    }
}
