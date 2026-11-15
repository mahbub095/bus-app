<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends BaseAdminController
{
    /**
     * Menu-permission keys that may be assigned to an Admin account.
     *
     * @return array<int, string>
     */
    private function validMenus(): array
    {
        return [
            'coach-services', 'bookings', 'cancel-requests', 'stations',
            'buses', 'routes', 'schedules', 'promotions', 'users', 'reports',
            'license', 'site-settings', 'gateways', 'profile',
        ];
    }

    /**
     * Create a new user account. Super Admin only.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return $this->adminTabRedirect($request)->withErrors([
                'message' => 'Only a Super Admin can create user accounts.',
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:super_admin,admin,user',
        ]);

        $validMenus = $this->validMenus();
        $role = $validated['role'];

        // An Admin account is useless without at least one menu permission.
        if ($role === 'admin') {
            $request->validate([
                'menu_permissions' => 'required|array|min:1',
                'menu_permissions.*' => 'in:'.implode(',', $validMenus),
            ], [
                'menu_permissions.required' => 'Select at least one menu permission for the Admin role.',
                'menu_permissions.min' => 'Select at least one menu permission for the Admin role.',
            ]);
        }

        // NULL means "every menu" — only plain Admins store an explicit list.
        $permissions = $role === 'admin'
            ? array_values(array_intersect((array) $request->input('menu_permissions', []), $validMenus))
            : null;

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'password' => $validated['password'], // hashed by the model cast
            'role' => $role,
            'menu_permissions' => $permissions,
        ]);

        return $this->adminTabRedirect($request)
            ->with('success', "User account {$user->email} created successfully!");
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $currentUser = Auth::user();

        // Mirrors the delete rule: only a Super Admin may touch an admin account,
        // otherwise a staff admin could grant itself the system permissions.
        if (! $currentUser->isSuperAdmin() && in_array($user->role, ['super_admin', 'admin'])) {
            return $this->adminTabRedirect($request)->withErrors([
                'message' => 'Only a Super Admin can modify admin accounts.',
            ]);
        }

        // A Super Admin may re-assign anyone's role except their own — demoting
        // yourself is the one way to lock the install out of every menu.
        $canEditRole = $currentUser->isSuperAdmin() && $currentUser->id !== $user->id;

        $rules = ['name' => 'required|string|max:100'];

        if ($user->role !== 'super_admin') {
            $rules['email'] = 'required|email|max:100|unique:users,email,'.$id;
        }

        if ($canEditRole) {
            $rules['role'] = 'required|string|in:super_admin,admin,user';
        }

        $validMenus = $this->validMenus();

        // Role that will be stored once this request is applied.
        $effectiveRole = $canEditRole
            ? $request->input('role', $user->role)
            : $user->role;

        $roleChanged = $effectiveRole !== $user->role;

        if ($currentUser->isAdmin() && $effectiveRole === 'admin') {
            // Omitting menu_permissions keeps the stored values; an explicit
            // empty list is rejected because an admin needs at least one menu.
            $rules['menu_permissions']   = 'nullable|array|min:1';
            $rules['menu_permissions.*'] = 'in:' . implode(',', $validMenus);
        }

        $request->validate($rules, [
            'menu_permissions.min' => 'Select at least one menu permission for the Admin role.',
        ]);

        $updateData = ['name' => trim($request->input('name'))];

        if ($user->role !== 'super_admin') {
            $updateData['email'] = trim($request->input('email'));
        }

        if ($canEditRole) {
            $updateData['role'] = $effectiveRole;
        }

        // Menu permissions only apply to the admin role. Super admins always get
        // every menu and plain users never see the admin dashboard at all.
        if ($effectiveRole === 'admin') {
            if ($request->has('menu_permissions')) {
                $updateData['menu_permissions'] = array_values(
                    array_intersect((array) $request->input('menu_permissions'), $validMenus)
                );
            } elseif ($roleChanged) {
                // Promoted into the admin role with nothing ticked — the client
                // blocks this, but never leave a NULL (= all menus) behind.
                $updateData['menu_permissions'] = [];
            }
        } elseif ($roleChanged) {
            // Leaving the admin role: the permission list no longer applies.
            $updateData['menu_permissions'] = null;
        }

        $user->update($updateData);

        return $this->adminTabRedirect($request)->with('success', 'User details & permissions updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $currentUser = Auth::user();

        if ($currentUser->id == $user->id) {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'You cannot delete your own account.']);
        }

        if (! $currentUser->isSuperAdmin() && in_array($user->role, ['super_admin', 'admin'])) {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'Admin cannot delete higher or equal roles like super_admin or admin.']);
        }

        $user->delete();

        return $this->adminTabRedirect($request)->with('success', 'User deleted successfully!');
    }
}
