<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token-guard counterpart of EnsureUserHasMenuPermission.
 *
 * Usage: ->middleware('api_menu:bookings')
 */
class EnsureApiUserHasMenuPermission
{
    public function handle(Request $request, Closure $next, string $menu): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->hasMenuPermission($menu)) {
            return response()->json([
                'message' => "Access denied. You do not have permission to access the {$menu} menu.",
            ], 403);
        }

        return $next($request);
    }
}
