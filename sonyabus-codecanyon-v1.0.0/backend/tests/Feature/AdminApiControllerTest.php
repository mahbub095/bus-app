<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $adminNoPerms;
    private User $adminBookings;
    private User $adminCoach;
    private User $adminCancel;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
        ]);

        $this->adminNoPerms = User::create([
            'name' => 'Admin No Perms',
            'email' => 'adminnoperms@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'menu_permissions' => [],
        ]);

        $this->adminBookings = User::create([
            'name' => 'Admin Bookings',
            'email' => 'adminbookings@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'menu_permissions' => ['bookings'],
        ]);

        $this->adminCoach = User::create([
            'name' => 'Admin Coach',
            'email' => 'admincoach@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'menu_permissions' => ['coach-services'],
        ]);

        $this->adminCancel = User::create([
            'name' => 'Admin Cancel',
            'email' => 'admincancel@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'menu_permissions' => ['cancel-requests'],
        ]);

        $this->customer = User::create([
            'name' => 'Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);
    }

    public function test_guest_cannot_access_admin_ajax_endpoints(): void
    {
        $response = $this->getJson('/admin/api/bookings/logs');
        $response->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_ajax_endpoints(): void
    {
        $response = $this->actingAs($this->customer)
            ->getJson('/admin/api/bookings/logs');
        $response->assertStatus(403);
    }

    public function test_booking_logs_permission_check(): void
    {
        $response = $this->actingAs($this->adminNoPerms)
            ->getJson('/admin/api/bookings/logs');
        $response->assertStatus(403);

        $response = $this->actingAs($this->adminBookings)
            ->getJson('/admin/api/bookings/logs');
        $response->assertStatus(200);
        $response->assertJsonStructure(['bookings', 'updated_at']);
    }

    public function test_coach_services_permission_check(): void
    {
        $stationA = Station::first();
        $stationB = Station::skip(1)->first();

        $response = $this->actingAs($this->adminNoPerms)
            ->getJson("/admin/api/coach-services/search?from={$stationA->id}&to={$stationB->id}&date=" . now()->format('Y-m-d'));
        $response->assertStatus(403);

        $response = $this->actingAs($this->adminCoach)
            ->getJson("/admin/api/coach-services/search?from={$stationA->id}&to={$stationB->id}&date=" . now()->format('Y-m-d'));
        $response->assertStatus(200);

        $schedule = Schedule::first();

        $response = $this->actingAs($this->adminNoPerms)
            ->postJson("/admin/api/schedules/{$schedule->id}/seats/toggle-block", ['seat' => 'A1']);
        $response->assertStatus(403);

        $response = $this->actingAs($this->adminCoach)
            ->postJson("/admin/api/schedules/{$schedule->id}/seats/toggle-block", ['seat' => 'E1']);
        $response->assertStatus(200);
        $response->assertJsonPath('seat', 'E1');
    }

    public function test_cancel_requests_permission_check(): void
    {
        $schedule = Schedule::first();
        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => 'John Cancel',
            'passenger_phone' => '01700000000',
            'passenger_email' => 'john@test.com',
            'passenger_gender' => 'M',
            'seat_numbers' => 'B2',
            'total_fare' => 500.00,
            'payment_method' => 'Cash',
            'status' => 'CANCEL_REQUESTED',
        ]);

        $response = $this->actingAs($this->adminNoPerms)
            ->getJson('/admin/api/cancel-requests/logs');
        $response->assertStatus(403);

        $response = $this->actingAs($this->adminCancel)
            ->getJson('/admin/api/cancel-requests/logs');
        $response->assertStatus(200);
        $response->assertJsonStructure(['cancel_requests', 'updated_at']);

        $response = $this->actingAs($this->adminNoPerms)
            ->post("/admin/bookings/{$booking->id}/approve-cancel");
        $response->assertRedirect();
        $response->assertSessionHasErrors('message');

        $response = $this->actingAs($this->adminCancel)
            ->post("/admin/bookings/{$booking->id}/approve-cancel");
        $response->assertRedirect();
        $this->assertEquals('CANCELLED', $booking->fresh()->status);
    }
}
