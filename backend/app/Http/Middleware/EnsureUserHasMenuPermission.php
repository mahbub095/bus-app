<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasMenuPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $menu): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (! Auth::user()->hasMenuPermission($menu)) {
            if ($request->expectsJson() || $request->is('admin/api/*')) {
                return response()->json([
                    'message' => "Access denied. You do not have permission to access the {$menu} menu.",
                ], 403);
            }

            return redirect()->route('admin.dashboard')->withErrors([
                'message' => "Access denied. You do not have permission to access the {$menu} menu.",
            ]);
        }

        return $next($request);
    }
}
