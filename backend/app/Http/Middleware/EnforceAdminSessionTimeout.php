<?php

namespace App\Http\Middleware;

use App\Services\AdminSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs an admin out once the configured idle window has elapsed.
 *
 * Laravel's own session expiry only garbage-collects stale storage, which makes
 * the cut-off non-deterministic on low-traffic admin panels. This check makes
 * the timeout exact and gives the operator a clear "session expired" message.
 */
class EnforceAdminSessionTimeout
{
    /** Session key holding the last admin request timestamp (unix seconds). */
    public const LAST_ACTIVITY_KEY = 'admin_last_activity';

    public function __construct(protected AdminSessionService $adminSessionService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user  = $guard->user();

        // Only admin sessions are subject to the policy. Customers sign in
        // through the API token flow and guests have nothing to expire.
        if (! $user || ! $user->isAdmin()) {
            return $next($request);
        }

        $lifetime     = $this->adminSessionService->minutes() * 60;
        $lastActivity = (int) $request->session()->get(self::LAST_ACTIVITY_KEY, 0);

        if ($lastActivity > 0 && (now()->getTimestamp() - $lastActivity) >= $lifetime) {
            return $this->expireSession($request);
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, now()->getTimestamp());

        return $next($request);
    }

    /**
     * Log the admin out and send them back to the login screen.
     */
    protected function expireSession(Request $request): Response
    {
        // Logout also forgets the "remember me" cookie, so the next request
        // cannot silently re-authenticate the expired session.
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'Your session expired after '
            .$this->adminSessionService->minutes()
            .' minutes of inactivity. Please sign in again.';

        if ($request->expectsJson()) {
            return response()->json([
                'message'         => $message,
                'session_expired' => true,
            ], 401);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
