<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('admin/api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
            return redirect()->route('login');
        }

        if (! Auth::user()->isAdmin()) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('admin/api/*')) {
                return response()->json([
                    'message' => 'Access denied. Only admin accounts can use this resource.',
                ], 403);
            }
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Access denied. Only admin accounts can use the admin dashboard.',
            ]);
        }

        return $next($request);
    }
}
