<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Services\AdminSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordCodeMail;

class UserAuthController extends BaseController
{
    public function __construct(protected AdminSessionService $adminSessionService)
    {
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'user',
        ]);

        $token = $this->issueToken($user);

        return response()->json([
            'message' => 'Account created successfully.',
            'token' => $token['token'],
            'expires_at' => $token['expires_at'],
            'expires_in' => $token['expires_in'],
            'user' => $this->formatUser($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isUser()) {
            return response()->json([
                'message' => 'Admin accounts must sign in through the admin portal.',
            ], 403);
        }

        $user->tokens()->delete();
        $token = $this->issueToken($user);

        return response()->json([
            'message' => 'Logged in successfully.',
            'token' => $token['token'],
            'expires_at' => $token['expires_at'],
            'expires_in' => $token['expires_in'],
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
            'expires_at' => $this->effectiveTokenExpiry($request->user()),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:100',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->isUser()) {
            throw ValidationException::withMessages([
                'email' => ['This email is not registered as a customer.'],
            ]);
        }

        // Generate a 6-digit random code
        $code = strval(rand(100000, 999999));

        // Save to password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($code),
                'created_at' => now()
            ]
        );

        // Do not log reset codes — they are sensitive credentials

        // Send the email
        try {
            Mail::to($user->email)->send(new ResetPasswordCodeMail($code));
        } catch (\Exception $e) {
            Log::error("Failed to send password reset email to {$user->email}: " . $e->getMessage());
            if (config('app.env') !== 'local') {
                throw ValidationException::withMessages([
                    'email' => ['We encountered an error while sending the password reset code. Please try again.'],
                ]);
            }
        }

        // Return message
        $response = [
            'message' => 'A password reset code has been sent to your email.'
        ];
        
        // Return code in local and testing environments for development convenience
        if (config('app.env') === 'local' || config('app.env') === 'testing') {
            $response['code'] = $code;
        }

        return response()->json($response);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:100',
            'code' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$reset || !Hash::check($validated['code'], $reset->token)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid reset code.'],
            ]);
        }

        // Check if token has expired (60 minutes)
        if (\Carbon\Carbon::parse($reset->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            throw ValidationException::withMessages([
                'code' => ['The reset code has expired.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json([
            'message' => 'Password reset successfully. You can now login.'
        ]);
    }

    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    /**
     * Issue an API token whose lifetime follows the admin-configured
     * "customer_session_lifetime" setting (0 minutes = never expires).
     *
     * @return array{token: string, expires_at: string|null, expires_in: int|null}
     */
    protected function issueToken(User $user): array
    {
        $expiresAt = $this->adminSessionService->customerTokenExpiresAt();

        $token = $user->createToken('frontend-api', ['*'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_in' => $expiresAt ? $expiresAt->getTimestamp() - now()->getTimestamp() : null,
        ];
    }

    /**
     * The cut-off that actually applies to the current token: the earlier of
     * its own expires_at and (created_at + the currently configured lifetime),
     * so shortening the setting also shortens already issued tokens.
     */
    protected function effectiveTokenExpiry(User $user): ?string
    {
        $token = $user->currentAccessToken();

        if (! $token) {
            return null;
        }

        $expiresAt = $token->expires_at;
        $configuredMinutes = $this->adminSessionService->customerMinutes();

        if ($configuredMinutes !== null && $token->created_at) {
            $fromSetting = $token->created_at->copy()->addMinutes($configuredMinutes);
            $expiresAt = $expiresAt ? $expiresAt->min($fromSetting) : $fromSetting;
        }

        return $expiresAt?->toIso8601String();
    }
}
