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

        // Admin tries to update another user's details/role
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
}
