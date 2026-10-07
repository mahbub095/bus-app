<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the CheckMaintenanceMode middleware:
 *  - Maintenance ON blocks public API routes with 503
 *  - /api/site-settings is exempt even during maintenance
 *  - Authenticated booking routes also blocked during maintenance
 *  - Maintenance OFF lets all requests through
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        // Ensure cache is cleared so SiteSetting reads fresh values
        SiteSetting::clearCache();
    }

    protected function tearDown(): void
    {
        // Always restore maintenance off after each test
        SiteSetting::setValue('maintenance_mode', 'false');
        SiteSetting::clearCache();
        parent::tearDown();
    }

    /** @test */
    public function public_search_is_blocked_during_maintenance(): void
    {
        SiteSetting::setValue('maintenance_mode', 'true');
        SiteSetting::setValue('maintenance_message', 'Back soon!');
        SiteSetting::clearCache();

        $response = $this->getJson('/api/search?from=1&to=2&date=' . now()->format('Y-m-d'));

        $response->assertStatus(503)
            ->assertJsonPath('maintenance', true)
            ->assertJsonPath('message', 'Back soon!');
    }

    /** @test */
    public function site_settings_endpoint_is_exempt_during_maintenance(): void
    {
        SiteSetting::setValue('maintenance_mode', 'true');
        SiteSetting::clearCache();

        $response = $this->getJson('/api/site-settings');

        // Must NOT return 503 — the frontend needs this to show the maintenance page
        $response->assertStatus(200);
    }

    /** @test */
    public function stations_endpoint_is_blocked_during_maintenance(): void
    {
        SiteSetting::setValue('maintenance_mode', 'true');
        SiteSetting::clearCache();

        $response = $this->getJson('/api/stations');
        $response->assertStatus(503);
    }

    /** @test */
    public function authenticated_booking_endpoint_is_blocked_during_maintenance(): void
    {
        SiteSetting::setValue('maintenance_mode', 'true');
        SiteSetting::clearCache();

        $customer = User::create([
            'name'     => 'Maint Customer',
            'email'    => 'maint@test.com',
            'password' => bcrypt('pass'),
            'role'     => 'user',
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/bookings/mine');

        $response->assertStatus(503);
    }

    /** @test */
    public function public_api_works_normally_when_maintenance_is_off(): void
    {
        SiteSetting::setValue('maintenance_mode', 'false');
        SiteSetting::clearCache();

        $response = $this->getJson('/api/stations');
        $response->assertStatus(200);
    }

    /** @test */
    public function promotions_endpoint_is_blocked_during_maintenance(): void
    {
        SiteSetting::setValue('maintenance_mode', 'true');
        SiteSetting::clearCache();

        $response = $this->getJson('/api/promotions');
        $response->assertStatus(503);
    }

    /** @test */
    public function auth_register_is_not_blocked_during_maintenance(): void
    {
        // Auth routes are NOT wrapped in CheckMaintenanceMode
        SiteSetting::setValue('maintenance_mode', 'true');
        SiteSetting::clearCache();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@test.com',
            'password' => 'wrong',
        ]);

        // Should get 422 (validation error), not 503
        $response->assertStatus(422);
    }
}
