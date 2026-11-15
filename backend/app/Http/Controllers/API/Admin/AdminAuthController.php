<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\FormatsAdminResources;
use App\Models\User;
use App\Services\AdminSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token authentication for the admin API.
 *
 * A token is valid for the idle timeout saved under
 * Site Settings → Session Security (admin_session_lifetime), so shortening the
 * policy also shortens tokens that were issued earlier.
 */
class AdminAuthController extends BaseAdminApiController
{
    use FormatsAdminResources;

    public function __construct(protected AdminSessionService $adminSessionService)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:60',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'Only admin accounts can sign in to the admin API.',
            ], 403);
        }

        $minutes = $this->adminSessionService->minutes();
        $expiresAt = now()->addMinutes($minutes);

        $token = $user->createToken(
            $credentials['device_name'] ?? 'admin-api',
            ['admin'],
            $expiresAt
        );

        return response()->json([
            'message' => 'Signed in successfully.',
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in' => $minutes * 60,
            'session' => $this->sessionPolicy(),
            'user' => $this->formatUser($user),
            'menus' => $this->menusFor($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        return response()->json([
            'user' => $this->formatUser($user),
            'menus' => $this->menusFor($user),
            'session' => $this->sessionPolicy(),
            'token' => [
                'name' => $token?->name,
                'expires_at' => optional($token?->expires_at)->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Change the password of the signed-in admin (Profile tab).
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        // Other devices were signed in with the old password — drop them.
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();

        return response()->json([
            'message' => 'Password updated successfully.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    /**
     * @return array<string, int|null>
     */
    protected function sessionPolicy(): array
    {
        return [
            'admin_idle_timeout_minutes' => $this->adminSessionService->minutes(),
            'customer_token_lifetime_minutes' => $this->adminSessionService->customerMinutes(),
        ];
    }

    /**
     * Menu keys the account may open — drives the client side navigation.
     *
     * @return list<string>
     */
    protected function menusFor(User $user): array
    {
        return array_values(array_filter(
            User::MENU_KEYS,
            fn (string $menu) => $user->hasMenuPermission($menu)
        ));
    }
}
