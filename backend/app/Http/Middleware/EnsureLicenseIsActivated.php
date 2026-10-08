<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-stage gating for the application:
 *
 *  STAGE 1 — INSTALL LOCK
 *    Until APP_INSTALLED=true, only install/* routes and a few system
 *    routes are allowed. Everything else gets a 403 JSON or redirect to
 *    /install — but NEVER from within an install/* route (no loop).
 *
 *  STAGE 2 — LICENSE GATE (runs only when install is complete)
 *    Public customer API reads are always permitted.
 *    Admin / write routes require a verified license.
 */
class EnsureLicenseIsActivated
{
    public function __construct(protected LicenseService $licenseService) {}

    /**
     * Paths that ALWAYS pass through — no install check, no license check.
     * These must never redirect to each other.
     */
    private function isAlwaysAllowed(string $path): bool
    {
        // Exact matches
        $exact = ['up', 'payment/callback', 'payment/cancel', 'api/payment/webhook'];
        if (in_array($path, $exact, true)) {
            return true;
        }

        // Install wizard — always pass through (controller handles locked state)
        if ($path === 'install' || str_starts_with($path, 'install/')) {
            return true;
        }

        // Install API steps
        if (str_starts_with($path, 'api/install/')) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the install wizard has been completed.
     */
    private function isInstalled(): bool
    {
        $flag = env('APP_INSTALLED', false);

        if ($flag === true || $flag === 1) {
            return true;
        }

        if (is_string($flag) && in_array(strtolower($flag), ['true', '1'], true)) {
            return true;
        }

        return file_exists(storage_path('framework/installed'));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // ── Always-allowed routes pass straight through ──────────────────
        if ($this->isAlwaysAllowed($path)) {
            return $next($request);
        }

        // ── STAGE 1: Install gate ────────────────────────────────────────
        // If install hasn't been completed, block everything except the
        // install routes (already handled above).
        if (! $this->isInstalled()) {
            if ($request->expectsJson() || str_starts_with($path, 'api/')) {
                return response()->json([
                    'message'   => 'Installation not complete. Please run the install wizard first.',
                    'redirect'  => url('/install'),
                    'installed' => false,
                ], 403);
            }

            // Only redirect to /install if we're NOT already heading there
            // (belt-and-suspenders guard; isAlwaysAllowed() above should
            // have already returned for install routes).
            return redirect('/install');
        }

        // ── STAGE 2: License gate (install is done) ──────────────────────

        // Public customer API — exact paths
        $publicExact = [
            'api/site-settings',
            'api/stations',
            'api/search',
            'api/promotions',
            'api/promotions/check',
        ];
        if (in_array($path, $publicExact, true)) {
            return $next($request);
        }

        // Public customer API — prefixes
        $publicPrefixes = ['api/auth/', 'api/bookings/public/'];
        foreach ($publicPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        // All other API GET/HEAD/OPTIONS calls are public reads
        if (str_starts_with($path, 'api/')) {
            if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                return $next($request);
            }

            // Public write endpoints
            if (
                str_starts_with($path, 'api/auth/') ||
                $path === 'api/bookings' ||
                str_starts_with($path, 'api/bookings/') ||
                $path === 'api/seats/hold' ||
                $path === 'api/seats/release'
            ) {
                return $next($request);
            }
        }

        // ── License required for everything else ─────────────────────────
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
