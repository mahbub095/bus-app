<?php

namespace App\Http\Middleware;

use App\Services\AdminSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pushes the admin-configured session expiry into the framework config before
 * the session store is built. Registered as global middleware so it runs ahead
 * of StartSession in the "web" group: the session cookie lifetime, the session
 * handler's idle window and the "remember me" cookie all follow the value the
 * admin saved under Site Settings → Session & Security.
 */
class ApplyAdminSessionLifetime
{
    public function __construct(protected AdminSessionService $adminSessionService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $minutes = $this->adminSessionService->minutes();

        config([
            'session.lifetime' => $minutes,
            // A "remember me" cookie must not outlive the configured policy,
            // otherwise an idle session is silently re-authenticated.
            'auth.guards.web.remember' => $minutes,
        ]);

        return $next($request);
    }
}
