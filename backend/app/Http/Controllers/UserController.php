<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Update the specified user role and details (Super Admin only).
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $currentUser = Auth::user();

        // Only Super Admin can change a user's role, and even then, only if the user is not currently an admin or super_admin
        $canEditRole = $currentUser->isSuperAdmin() && !in_array($user->role, ['super_admin', 'admin']);

        $rules = [
            'name' => 'required|string|max:100',
        ];

        if ($user->role !== 'super_admin') {
            $rules['email'] = 'required|email|max:100|unique:users,email,' . $id;
        }

        if ($canEditRole) {
            $rules['role'] = 'required|string|in:super_admin,admin,user';
        }

        $request->validate($rules);

        $updateData = [
            'name' => trim($request->input('name')),
        ];

        if ($user->role !== 'super_admin') {
            $updateData['email'] = trim($request->input('email'));
        }

        if ($canEditRole) {
            $updateData['role'] = $request->input('role');
        }

        // Only Admin and Super Admin can assign/manage menu permissions
        if ($currentUser->isAdmin()) {
            if ($request->has('menu_permissions')) {
                $permissions = $request->input('menu_permissions');
                if (is_array($permissions)) {
                    $validMenus = ['coach-services', 'bookings', 'cancel-requests', 'stations', 'buses', 'routes', 'schedules', 'promotions', 'users', 'reports'];
                    $updateData['menu_permissions'] = array_values(array_intersect($permissions, $validMenus));
                } else {
                    $updateData['menu_permissions'] = [];
                }
            } else {
                $updateData['menu_permissions'] = [];
            }
        }

        $user->update($updateData);

        return $this->adminTabRedirect($request)->with('success', 'User details & permissions updated successfully!');
    }

    /**
     * Delete the specified user (Admin and Super Admin).
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $currentUser = Auth::user();

        // Prevent self-deletion
        if ($currentUser->id == $user->id) {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'You cannot delete your own account.']);
        }

        // Admin role permission can not delete higher role permissions like super_admin
        if (!$currentUser->isSuperAdmin() && in_array($user->role, ['super_admin', 'admin'])) {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'Admin cannot delete higher or equal roles like super_admin or admin.']);
        }

        $user->delete();

        return $this->adminTabRedirect($request)->with('success', 'User deleted successfully!');
    }
}
