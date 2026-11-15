<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\FormatsAdminResources;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User & permission management (dashboard "Users" tab).
 *
 * The role rules mirror App\Http\Controllers\Admin\UserController exactly:
 * only a Super Admin may create accounts, touch other admin accounts or
 * re-assign roles.
 */
class UserApiController extends BaseAdminApiController
{
    use FormatsAdminResources;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'role' => 'nullable|in:super_admin,admin,user',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $users = User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $this->likeTerm((string) $request->query('search'));
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->where('role', (string) $request->query('role'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage($request))
            ->through(fn (User $user) => $this->formatUser($user));

        return response()->json($users);
    }

    public function show(int $id): JsonResponse
    {
        return $this->itemResponse($this->formatUser(User::findOrFail($id)));
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only a Super Admin can create user accounts.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:super_admin,admin,user',
        ]);

        $role = $validated['role'];

        // An Admin account is useless without at least one menu permission.
        if ($role === 'admin') {
            $request->validate([
                'menu_permissions' => 'required|array|min:1',
                'menu_permissions.*' => 'in:'.implode(',', User::MENU_KEYS),
            ], [
                'menu_permissions.required' => 'Select at least one menu permission for the Admin role.',
                'menu_permissions.min' => 'Select at least one menu permission for the Admin role.',
            ]);
        }

        // NULL means "every menu" — only plain Admins store an explicit list.
        $permissions = $role === 'admin'
            ? array_values(array_intersect((array) $request->input('menu_permissions', []), User::MENU_KEYS))
            : null;

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'password' => $validated['password'], // hashed by the model cast
            'role' => $role,
            'menu_permissions' => $permissions,
        ]);

        return $this->itemResponse(
            $this->formatUser($user),
            "User account {$user->email} created successfully.",
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // Only a Super Admin may touch an admin account, otherwise a staff admin
        // could grant itself the system permissions.
        if (! $currentUser->isSuperAdmin() && in_array($user->role, ['super_admin', 'admin'], true)) {
            return response()->json(['message' => 'Only a Super Admin can modify admin accounts.'], 403);
        }

        // A Super Admin may re-assign anyone's role except their own.
        $canEditRole = $currentUser->isSuperAdmin() && $currentUser->id !== $user->id;

        $rules = ['name' => 'required|string|max:100'];

        if ($user->role !== 'super_admin') {
            $rules['email'] = 'required|email|max:100|unique:users,email,'.$id;
        }

        if ($canEditRole) {
            $rules['role'] = 'required|string|in:super_admin,admin,user';
        }

        // Role that will be stored once this request is applied.
        $effectiveRole = $canEditRole ? $request->input('role', $user->role) : $user->role;
        $roleChanged = $effectiveRole !== $user->role;

        if ($currentUser->isAdmin() && $effectiveRole === 'admin') {
            // Omitting menu_permissions keeps the stored values; an explicit
            // empty list is rejected because an admin needs at least one menu.
            $rules['menu_permissions'] = 'nullable|array|min:1';
            $rules['menu_permissions.*'] = 'in:'.implode(',', User::MENU_KEYS);
        }

        $request->validate($rules, [
            'menu_permissions.min' => 'Select at least one menu permission for the Admin role.',
        ]);

        $updateData = ['name' => trim((string) $request->input('name'))];

        if ($user->role !== 'super_admin') {
            $updateData['email'] = trim((string) $request->input('email'));
        }

        if ($canEditRole) {
            $updateData['role'] = $effectiveRole;
        }

        if ($effectiveRole === 'admin') {
            if ($request->has('menu_permissions')) {
                $updateData['menu_permissions'] = array_values(
                    array_intersect((array) $request->input('menu_permissions'), User::MENU_KEYS)
                );
            } elseif ($roleChanged) {
                // Promoted into the admin role with nothing ticked.
                $updateData['menu_permissions'] = [];
            }
        } elseif ($roleChanged) {
            // Leaving the admin role: the permission list no longer applies.
            $updateData['menu_permissions'] = null;
        }

        $user->update($updateData);

        return $this->itemResponse(
            $this->formatUser($user->fresh()),
            'User details & permissions updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if (! $currentUser->isSuperAdmin() && in_array($user->role, ['super_admin', 'admin'], true)) {
            return response()->json([
                'message' => 'Only a Super Admin can delete an admin account.',
            ], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
