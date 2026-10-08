<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to the application until a valid Envato license is activated.
 *
 * The following routes are always permitted through:
 *   - /install and /install/*        — the wizard itself
 *   - /up                            — Laravel health check
 *   - /api/payment/webhook           — payment gateway callbacks
 *   - /payment/callback, /payment/cancel
 */
class EnsureLicenseIsActivated
{
    /** Routes that bypass the license check. Supports wildcard * segments. */
    private const EXCEPT = [
        'install',
        'install/*',
        'api/install/*',
        'up',
        'payment/callback',
        'payment/cancel',
        'api/payment/webhook',
    ];

    public function __construct(protected LicenseService $licenseService) {}

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::EXCEPT as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        if (! $this->licenseService->isActivated()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message'  => 'This application is not licensed. Please complete the installation.',
                    'redirect' => url('/install'),
                ], 403);
            }

            return redirect('/install');
        }

        return $next($request);
    }
}
