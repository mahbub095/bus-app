<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Test Super Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
        ]);

        $this->admin = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->user = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);
    }

    /**
     * Test regular user cannot access admin dashboard.
     */
    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    /**
     * Test admin can access admin dashboard but not super admin routes.
     */
    public function test_admin_has_restricted_access(): void
    {
        // Admin accesses dashboard
        $response = $this->actingAs($this->admin)
            ->get('/admin');
        $response->assertStatus(200);

        // Admin tries to access site settings update
        $response = $this->actingAs($this->admin)
            ->post('/admin/site-settings', []);
        $response->assertRedirect('/admin');
        $response->assertSessionHasErrors();

        // Admin tries to access database migration route
        $response = $this->actingAs($this->admin)
            ->post('/admin/system/migrate', []);
        $response->assertRedirect('/admin');
        $response->assertSessionHasErrors();

        // Admin tries to update another user's details/role without users menu permission
        $this->admin->menu_permissions = ['coach-services'];
        $this->admin->save();

        $response = $this->actingAs($this->admin)
            ->put('/admin/users/' . $this->user->id, [
                'name' => 'Updated Customer',
                'email' => 'customer@test.com',
                'role' => 'admin',
            ]);
        $response->assertRedirect('/admin');
        $response->assertSessionHasErrors();
    }

    /**
     * Test super admin can access all settings and update user roles.
     */
    public function test_super_admin_has_full_access(): void
    {
        // Super Admin accesses dashboard
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin');
        $response->assertStatus(200);

        // Super Admin updates a user's details and role
        $response = $this->actingAs($this->superAdmin)
            ->put('/admin/users/' . $this->user->id, [
                'name' => 'Promoted Admin',
                'email' => 'promoted@test.com',
                'role' => 'admin',
            ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertEquals('admin', $this->user->fresh()->role);
        $this->assertEquals('Promoted Admin', $this->user->fresh()->name);
    }

    /**
     * Test admin and super admin can delete users, but cannot self-delete.
     */
    public function test_user_deletion_and_self_deletion_prevention(): void
    {
        // Admin deletes regular user
        $response = $this->actingAs($this->admin)
            ->delete('/admin/users/' . $this->user->id);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertNull(User::find($this->user->id));

        // Admin tries to delete themselves
        $response = $this->actingAs($this->admin)
            ->delete('/admin/users/' . $this->admin->id);
        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertNotNull(User::find($this->admin->id));
    }

    /**
     * Test admin cannot delete super_admin or admin users.
     */
    public function test_admin_cannot_delete_higher_or_equal_roles(): void
    {
        // Admin tries to delete Super Admin
        $response = $this->actingAs($this->admin)
            ->delete('/admin/users/' . $this->superAdmin->id);
        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertNotNull(User::find($this->superAdmin->id));

        // Let's create another admin
        $anotherAdmin = User::create([
            'name' => 'Another Admin',
            'email' => 'anotheradmin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        // Admin tries to delete another Admin
        $response = $this->actingAs($this->admin)
            ->delete('/admin/users/' . $anotherAdmin->id);
        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertNotNull(User::find($anotherAdmin->id));
    }

    /**
     * Test admin with users permission can update user details but cannot change user role.
     */
    public function test_admin_cannot_change_user_role(): void
    {
        $this->admin->menu_permissions = ['users'];
        $this->admin->save();

        // Admin tries to change user's role to admin
        $response = $this->actingAs($this->admin)
            ->put('/admin/users/' . $this->user->id, [
                'name' => 'Updated Customer Name',
                'email' => 'customer@test.com',
                'role' => 'admin',
            ]);
        
        $response->assertRedirect();
        $this->assertEquals('user', $this->user->fresh()->role); // role did NOT change
        $this->assertEquals('Updated Customer Name', $this->user->fresh()->name); // name did change
    }

    /**
     * Test admin can assign menu permissions to users.
     */
    public function test_admin_can_assign_menu_permissions(): void
    {
        $this->admin->menu_permissions = ['users'];
        $this->admin->save();

        $response = $this->actingAs($this->admin)
            ->put('/admin/users/' . $this->user->id, [
                'name' => 'Test Customer',
                'email' => 'customer@test.com',
                'menu_permissions' => ['bookings', 'stations']
            ]);

        $response->assertRedirect();
        $this->assertEquals(['bookings', 'stations'], $this->user->fresh()->menu_permissions);
    }

    /**
     * Test existing admins/super_admins roles cannot be modified even by super admin.
     */
    public function test_admin_and_super_admin_roles_are_locked(): void
    {
        // Super Admin tries to demote another admin
        $response = $this->actingAs($this->superAdmin)
            ->put('/admin/users/' . $this->admin->id, [
                'name' => 'Test Admin',
                'email' => 'admin@test.com',
                'role' => 'user',
            ]);

        $response->assertRedirect();
        $this->assertEquals('admin', $this->admin->fresh()->role); // remains admin
    }

    /**
     * Test route protection based on menu permissions.
     */
    public function test_menu_permission_route_protection(): void
    {
        // Admin with only stations permission tries to access bookings logs API
        $this->admin->menu_permissions = ['stations'];
        $this->admin->save();

        $response = $this->actingAs($this->admin)
            ->get('/admin/api/bookings/logs');
        $response->assertStatus(403); // Forbidden

        // Admin with bookings permission can access it
        $this->admin->menu_permissions = ['bookings'];
        $this->admin->save();

        $response = $this->actingAs($this->admin)
            ->get('/admin/api/bookings/logs');
        $response->assertStatus(200);
    }

    /**
     * Test super admin email address cannot be updated.
     */
    public function test_super_admin_email_cannot_be_updated(): void
    {
        $originalEmail = $this->superAdmin->email;

        $response = $this->actingAs($this->superAdmin)
            ->put('/admin/users/' . $this->superAdmin->id, [
                'name' => 'Updated Super Admin Name',
                'email' => 'newsuperadmin@test.com',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertEquals('Updated Super Admin Name', $this->superAdmin->fresh()->name);
        $this->assertEquals($originalEmail, $this->superAdmin->fresh()->email); // email remains unchanged
    }
}
