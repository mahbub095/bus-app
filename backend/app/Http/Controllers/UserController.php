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

        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email,' . $id,
            'role' => 'required|string|in:super_admin,admin,user',
        ]);

        $user->update([
            'name' => trim($request->input('name')),
            'email' => trim($request->input('email')),
            'role' => $request->input('role'),
        ]);

        return $this->adminTabRedirect($request)->with('success', 'User role/details updated successfully!');
    }

    /**
     * Delete the specified user (Admin and Super Admin).
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent self-deletion
        if (Auth::id() == $user->id) {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return $this->adminTabRedirect($request)->with('success', 'User deleted successfully!');
    }
}
