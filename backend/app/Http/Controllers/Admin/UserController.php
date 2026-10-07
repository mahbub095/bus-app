<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends BaseAdminController
{
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $currentUser = Auth::user();

        $canEditRole = $currentUser->isSuperAdmin() && ! in_array($user->role, ['super_admin', 'admin']);

        $rules = ['name' => 'required|string|max:100'];

        if ($user->role !== 'super_admin') {
            $rules['email'] = 'required|email|max:100|unique:users,email,'.$id;
        }

        if ($canEditRole) {
            $rules['role'] = 'required|string|in:super_admin,admin,user';
        }

        $validMenus = [
            'coach-services', 'bookings', 'cancel-requests', 'stations',
            'buses', 'routes', 'schedules', 'promotions', 'users', 'reports',
        ];

        if ($currentUser->isAdmin()) {
            // Determine the role that will be saved after this request.
            // If the super admin is changing the role, use the submitted value;
            // otherwise use the existing role on the user record.
            $effectiveRole = $canEditRole
                ? $request->input('role', $user->role)
                : $user->role;

            if ($effectiveRole === 'admin') {
                // nullable — no menu_permissions means full admin access (null in DB)
                $rules['menu_permissions']   = 'nullable|array';
                $rules['menu_permissions.*'] = 'in:' . implode(',', $validMenus);
            }
        }

        $request->validate($rules);

        $updateData = ['name' => trim($request->input('name'))];

        if ($user->role !== 'super_admin') {
            $updateData['email'] = trim($request->input('email'));
        }

        if ($canEditRole) {
            $updateData['role'] = $request->input('role');
        }

        if ($currentUser->isAdmin()) {
            if ($request->has('menu_permissions')) {
                $permissions = $request->input('menu_permissions');
                if (is_array($permissions) && count($permissions) > 0) {
                    $updateData['menu_permissions'] = array_values(array_intersect($permissions, $validMenus));
                } else {
                    // Empty array submitted → no permissions
                    $updateData['menu_permissions'] = [];
                }
            }
            // If 'menu_permissions' key is absent from the request, preserve existing value (do not overwrite)
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
