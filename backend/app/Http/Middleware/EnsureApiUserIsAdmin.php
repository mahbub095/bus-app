<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token-guard counterpart of EnsureUserIsAdmin.
 *
 * The web middleware reads Auth::user() (session guard); the admin API is
 * authenticated with a Sanctum bearer token, so the resolved request user is
 * the only reliable source here.
 */
class EnsureApiUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'Access denied. Only admin accounts can use this resource.',
            ], 403);
        }

        return $next($request);
    }
}
