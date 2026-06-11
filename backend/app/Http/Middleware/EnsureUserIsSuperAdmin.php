<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (! Auth::user()->isSuperAdmin()) {
            if ($request->expectsJson() || $request->is('admin/api/*')) {
                return response()->json([
                    'message' => 'Access denied. You do not have Super Admin permissions.',
                ], 403);
            }

            return redirect()->route('admin.dashboard')->withErrors([
                'message' => 'Access denied. You do not have Super Admin permissions.',
            ]);
        }

        return $next($request);
    }
}
