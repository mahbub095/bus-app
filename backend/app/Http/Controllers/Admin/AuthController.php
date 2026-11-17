<?php

namespace App\Http\Controllers\Admin;

use App\Mail\ResetPasswordCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends BaseAdminController
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (Auth::check()) {
            Auth::logout();
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $user = Auth::user();

            if (! $user->isAdmin()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->back()->withInput()->withErrors([
                    'email' => 'Access denied. Only admin accounts can sign in here. Please use the booking site for customer login.',
                ]);
            }

            $request->session()->regenerate();

            return redirect()->intended('/admin');
        }

        return redirect()->back()->withInput()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out successfully.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->adminTabRedirect($request)->withErrors([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->update(['password' => $validated['password']]);

        return $this->adminTabRedirect($request)->with('success', 'Password updated successfully.');
    }

    // ─── Forgot Password ──────────────────────────────────────────────────────

    public function showForgotPassword()
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.forgot-password');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:100',
        ]);

        $email = $request->input('email');
        $user  = \App\Models\User::where('email', $email)->first();

        // Always show success to prevent email enumeration
        if (! $user || ! $user->isAdmin()) {
            return back()->with('success', 'If that email belongs to an admin account, a reset code has been sent.');
        }

        $code = strval(rand(100000, 999999));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        try {
            Mail::to($email)->send(new ResetPasswordCodeMail($code));
        } catch (\Exception $e) {
            Log::error("Admin password reset email failed for {$email}: " . $e->getMessage());
            return back()->withErrors(['email' => 'Failed to send reset email: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.reset-password.show')
            ->with('success', 'Reset code sent to your email. It expires in 60 minutes.')
            ->with('reset_email', $email);
    }

    // ─── Reset Password ───────────────────────────────────────────────────────

    public function showResetPassword(Request $request)
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.reset-password', [
            'email' => session('reset_email', $request->query('email', '')),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email'                 => 'required|email|max:100',
            'code'                  => 'required|string|digits:6',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (! $reset || ! Hash::check($validated['code'], $reset->token)) {
            return back()->withInput()->withErrors(['code' => 'Invalid reset code. Please check and try again.']);
        }

        if (\Carbon\Carbon::parse($reset->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            return back()->withInput()->withErrors(['code' => 'This reset code has expired. Please request a new one.']);
        }

        $user = \App\Models\User::where('email', $validated['email'])->first();

        if (! $user || ! $user->isAdmin()) {
            return back()->withInput()->withErrors(['email' => 'No admin account found with this email.']);
        }

        $user->update(['password' => $validated['password']]);

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        // Invalidate all existing sessions for this user
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return redirect()->route('login')
            ->with('success', 'Password reset successfully. Please sign in with your new password.');
    }
}
