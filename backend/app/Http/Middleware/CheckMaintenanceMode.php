<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SiteSetting;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * API routes that should be excluded from maintenance mode checks.
     * The site-settings endpoint must remain accessible so the frontend
     * can fetch the maintenance status and message.
     */
    protected array $except = [
        'api/site-settings',
        'api/site-settings/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Check if this route is excluded
        foreach ($this->except as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        // Check maintenance mode from database
        $maintenanceMode = SiteSetting::getValue('maintenance_mode', 'false');

        if ($maintenanceMode === 'true') {
            $message = SiteSetting::getValue(
                'maintenance_message',
                'We are currently performing scheduled maintenance. Please check back soon.'
            );

            return response()->json([
                'maintenance' => true,
                'message' => $message,
            ], 503);
        }

        return $next($request);
    }
}
