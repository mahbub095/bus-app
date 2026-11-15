<?php

namespace App\Http\Middleware;

use App\Services\AdminSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pushes the session/login lifetimes configured in the admin panel into the
 * framework config before the session store or the Sanctum guard is built.
 *
 * Registered as global middleware so it runs ahead of StartSession in the
 * "web" group and of auth:sanctum in the "api" group:
 *  - session.lifetime / auth.guards.web.remember → admin panel idle timeout
 *  - sanctum.expiration                          → admin API timeout for
 *                                                  api/admin/* requests, else
 *                                                  the customer token lifetime
 */
class ApplySessionLifetime
{
    public function __construct(protected AdminSessionService $adminSessionService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $adminMinutes = $this->adminSessionService->minutes();

        // The admin API is guarded by a token, not by the session cookie, and
        // its tokens follow the admin panel policy instead of the customer one.
        $isAdminApi = $request->is('api/admin') || $request->is('api/admin/*');

        config([
            'session.lifetime' => $adminMinutes,
            // A "remember me" cookie must not outlive the configured policy,
            // otherwise an idle session is silently re-authenticated.
            'auth.guards.web.remember' => $adminMinutes,
            // null = tokens never expire (admin chose 0 minutes).
            'sanctum.expiration' => $isAdminApi
                ? $adminMinutes
                : $this->adminSessionService->customerMinutes(),
        ]);

        return $next($request);
    }
}
