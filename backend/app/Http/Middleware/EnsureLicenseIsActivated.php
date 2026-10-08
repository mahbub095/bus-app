<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
    /** Routes that bypass the license check. Matched against request path. */
    private const EXACT = [
        'install',
        'up',
        'payment/callback',
        'payment/cancel',
        'api/payment/webhook',
    ];

    private const PREFIX = [
        'install/',
        'api/install/',
    ];

    public function __construct(protected LicenseService $licenseService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // Exact path match
        if (in_array($path, self::EXACT, true)) {
            return $next($request);
        }

        // Prefix match
        foreach (self::PREFIX as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        // Str::is wildcard match (covers legacy wildcard patterns like install/*)
        foreach (self::EXACT as $pattern) {
            if (Str::is($pattern, $path)) {
                return $next($request);
            }
        }

        if (! $this->licenseService->isActivated()) {
            if ($request->expectsJson() || str_starts_with($path, 'api/')) {
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
