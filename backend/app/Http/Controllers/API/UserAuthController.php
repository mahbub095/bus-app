<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserAuthController extends BaseController
{
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

        $token = $user->createToken('frontend-api')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'token' => $token,
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
        $token = $user->createToken('frontend-api')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully.',
            'token' => $token,
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

        // Log the code (since mailer is set to log)
        Log::info("Password reset code for {$user->email}: {$code}");

        // Return token/code in local/development env for testing convenience
        $response = [
            'message' => 'A password reset code has been sent to your email.'
        ];
        
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
}
