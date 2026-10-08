<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-stage gating for the application:
 *
 *  STAGE 1 — INSTALL LOCK (highest priority, checked FIRST)
 *    Until `APP_INSTALLED=true` OR storage/framework/installed marker file
 *    exists, ONLY the following routes are allowed:
 *      - /install and /install/*          (the install wizard itself)
 *      - /up                              (Laravel health check)
 *      - /payment/callback & /cancel      (payment gateway IPNs)
 *      - /api/payment/webhook             (ZiniPay server-to-server webhook)
 *    Everything else redirects to /install or returns 403 JSON.
 *    This enforces: "Must complete install process before anything works."
 *
 *  STAGE 2 — LICENSE ACTIVATION (runs only when install is complete)
 *    Activates the license gating. Admin-only write-style endpoints require
 *    APP_LICENSED=true + DB license record; public customer-facing API reads
 *    are permitted so that the customer React frontend can always render.
 *    (Admin routes and mutations are still gated.)
 */
class EnsureLicenseIsActivated
{
    /** Exact paths that always bypass EVERYTHING (both stages). */
    private const EXACT_ALWAYS = [
        'install',
        'up',
        'payment/callback',
        'payment/cancel',
        'api/payment/webhook',
    ];

    /** Path prefixes that always bypass. */
    private const PREFIX_ALWAYS = [
        'install/',
        'api/install/',
    ];

    // ── Public customer API routes that are allowed in STAGE 2 (after
    //    install is complete) even if license is not yet activated. ──
    private const PUBLIC_API_EXACT = [
        'api/site-settings',
        'api/stations',
        'api/search',
        'api/promotions',
        'api/promotions/check',
    ];

    private const PUBLIC_API_PREFIX = [
        'api/auth/',
        'api/bookings/public/',
    ];

    public function __construct(protected LicenseService $licenseService) {}

    /**
     * Whether the install wizard has completed (APP_INSTALLED=true or
     * storage/framework/installed marker file exists).
     */
    private function isInstallLocked(): bool
    {
        $flag = env('APP_INSTALLED', false);

        if ($flag === true || $flag === 1) {
            return true;
        }

        if (is_string($flag)) {
            $low = strtolower($flag);
            if ($low === 'true' || $flag === '1') {
                return true;
            }
        }

        $markerFile = storage_path('framework/installed');
        if (file_exists($markerFile)) {
            return true;
        }

        return false;
    }

    /**
     * Short-circuit a request to /install or throw the install-gate
     * redirect / JSON response. Used when the install wizard has NOT been
     * completed and the user tries to hit any non-install route.
     */
    private function redirectToInstall(Request $request): Response
    {
        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json([
                'message'  => 'Installation not complete. Please run the install wizard first.',
                'redirect' => url('/install'),
                'installed' => false,
            ], 403);
        }

        return redirect('/install');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // ────────────────────────────────────────────────────────────────
        // STAGE 0 — Routes that are always permitted regardless of state
        // (install wizard itself, health checks, payment webhooks).
        // ────────────────────────────────────────────────────────────────
        if (in_array($path, self::EXACT_ALWAYS, true)) {
            // Install-lock sub-branch: if already installed, redirect away
            // from /install URLs (otherwise proceed with the install page).
            if ($this->isInstallLocked()) {
                $isInstallRoute = $path === 'install' || str_starts_with($path, 'install/');
                if ($isInstallRoute) {
                    return redirect('/admin');
                }
            }
            return $next($request);
        }

        foreach (self::PREFIX_ALWAYS as $prefix) {
            if (str_starts_with($path, $prefix)) {
                if ($this->isInstallLocked()) {
                    return redirect('/admin');
                }
                return $next($request);
            }
        }

        // Str::is wildcard match (exact list)
        foreach (self::EXACT_ALWAYS as $pattern) {
            if (Str::is($pattern, $path)) {
                return $next($request);
            }
        }

        // ────────────────────────────────────────────────────────────────
        // STAGE 1 — INSTALL GATE
        // If the install wizard was never completed, block EVERYTHING and
        // force the user to /install.
        // This enforces "Must first install process" requirement.
        // ────────────────────────────────────────────────────────────────
        if (! $this->isInstallLocked()) {
            return $this->redirectToInstall($request);
        }

        // ────────────────────────────────────────────────────────────────
        // STAGE 2 — LICENSE GATE (runs only after install is complete)
        // Public read-style customer API endpoints bypass; admin / write /
        // web routes require APP_LICENSED=true + DB record.
        // ────────────────────────────────────────────────────────────────

        // Public customer API: exact paths
        if (in_array($path, self::PUBLIC_API_EXACT, true)) {
            return $next($request);
        }
        // Public customer API: prefixes
        foreach (self::PUBLIC_API_PREFIX as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        // ── Public-API fallback catch-all ────────────────────────────
        if (str_starts_with($path, 'api/')) {
            $nonAdminApiMethods = ['GET', 'HEAD', 'OPTIONS'];
            if (in_array($request->method(), $nonAdminApiMethods, true)) {
                return $next($request);
            }
            // Write-style API — allow only if it's a public write endpoint
            $publicWritePrefixes = ['api/auth/', 'api/payment/webhook'];
            foreach ($publicWritePrefixes as $wp) {
                if (str_starts_with($path, $wp)) {
                    return $next($request);
                }
            }
            if ($path === 'api/bookings' || $path === 'api/seats/hold' || $path === 'api/seats/release'
                || str_starts_with($path, 'api/bookings/')) {
                return $next($request);
            }
        }

        // ── Everything else needs a verified license ──────────────────
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
