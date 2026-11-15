<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\License;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AdminSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers the token-authenticated admin API (routes/api.php → API\Admin\*):
 *  - admin login issues a token, customers are rejected
 *  - menu permissions are enforced exactly like the Blade dashboard
 *  - the dashboard / CRUD / settings endpoints answer with dashboard data
 *  - the token honours the admin idle timeout from Site Settings
 */
class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $bookingsAdmin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin (and admin API write) routes sit behind the license gate.
        License::create([
            'purchase_code' => 'admin-api-test-license',
            'license_type' => 'Regular License',
            'is_verified' => true,
            'verified_at' => now(),
        ]);
        Cache::forget('license_verified');

        // Demo stations, coaches, routes, schedules, bookings and coupons.
        $this->seed();

        $this->superAdmin = $this->makeUser('api-super@test.com', 'super_admin');
        $this->bookingsAdmin = $this->makeUser('api-bookings@test.com', 'admin', ['bookings']);
        $this->customer = $this->makeUser('api-customer@test.com', 'user');
    }

    private function makeUser(string $email, string $role, ?array $menus = null): User
    {
        return User::create([
            'name' => 'API '.$role,
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => $role,
            'menu_permissions' => $menus,
        ]);
    }

    private function setSetting(string $key, int $value): void
    {
        SiteSetting::setValue($key, (string) $value);
        SiteSetting::clearCache();
    }

    /**
     * Laravel resolves auth guards once per app instance; a fresh request in
     * production rebuilds them, so the tests must do it too.
     */
    private function reloadGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function login(User $user): string
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();

        return (string) $response->json('token');
    }

    public function test_guest_cannot_reach_the_admin_api(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_admin_login_issues_a_token_with_menus(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => $this->superAdmin->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', $this->superAdmin->email)
            ->assertJsonStructure([
                'token',
                'expires_at',
                'expires_in',
                'session' => ['admin_idle_timeout_minutes', 'customer_token_lifetime_minutes'],
                'user',
                'menus',
            ]);

        $this->assertContains('bookings', $response->json('menus'));
        $this->assertSame(AdminSessionService::DEFAULT_MINUTES * 60, $response->json('expires_in'));
    }

    public function test_customers_cannot_use_the_admin_api(): void
    {
        $this->postJson('/api/admin/login', [
            'email' => $this->customer->email,
            'password' => 'password123',
        ])->assertForbidden();

        // A valid customer token must not open admin endpoints either.
        $token = $this->postJson('/api/auth/login', [
            'email' => $this->customer->email,
            'password' => 'password123',
        ])->json('token');

        $this->reloadGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_menu_permission_is_enforced_on_the_api(): void
    {
        $token = $this->login($this->bookingsAdmin);
        $this->reloadGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/stations')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/bookings')
            ->assertOk();
    }

    public function test_dashboard_returns_metrics_analytics_and_lists(): void
    {
        $token = $this->login($this->superAdmin);
        $this->reloadGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'metrics' => ['total_sales', 'active_bookings', 'cancelled_bookings', 'total_schedules'],
                'analytics' => ['period', 'metrics', 'charts'],
                'session',
                'lists' => ['stations', 'all_stations', 'buses', 'routes', 'schedules', 'promotions', 'users'],
            ]);
    }

    public function test_station_crud_through_the_api(): void
    {
        $token = $this->login($this->superAdmin);
        $this->reloadGuards();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/stations', ['name' => 'api terminal']);

        $create->assertCreated()->assertJsonPath('data.name', 'API TERMINAL');
        $id = (int) $create->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/admin/stations/{$id}", ['name' => 'api terminal two'])
            ->assertOk()
            ->assertJsonPath('data.name', 'API TERMINAL TWO');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/admin/stations/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('stations', ['id' => $id]);
    }

    public function test_booking_can_be_cancelled_through_the_api(): void
    {
        $booking = Booking::where('status', '!=', 'CANCELLED')->firstOrFail();

        $token = $this->login($this->bookingsAdmin);
        $this->reloadGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/admin/bookings/{$booking->id}/cancel")
            ->assertOk();

        $this->assertSame('CANCELLED', $booking->fresh()->status);
    }

    public function test_site_settings_endpoint_updates_the_session_policy(): void
    {
        $token = $this->login($this->superAdmin);
        $this->reloadGuards();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/admin/site-settings', [
                'admin_session_lifetime' => 90,
                'customer_session_lifetime' => 600,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.session.admin_session_lifetime', 90)
            ->assertJsonPath('data.session.customer_session_lifetime', 600);

        $this->assertSame('90', SiteSetting::getValue(AdminSessionService::SETTING_KEY));
        $this->assertSame('600', SiteSetting::getValue(AdminSessionService::CUSTOMER_SETTING_KEY));
    }

    public function test_session_policy_outside_the_allowed_range_is_rejected(): void
    {
        $token = $this->login($this->superAdmin);
        $this->reloadGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/admin/site-settings', ['admin_session_lifetime' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('admin_session_lifetime');

        $this->assertSame(
            (string) AdminSessionService::DEFAULT_MINUTES,
            SiteSetting::getValue(AdminSessionService::SETTING_KEY)
        );
    }

    public function test_admin_token_expires_after_the_configured_lifetime(): void
    {
        $this->setSetting(AdminSessionService::SETTING_KEY, 30);

        $token = $this->login($this->superAdmin);
        $this->reloadGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/me')
            ->assertOk();

        $this->travel(31)->minutes();
        $this->reloadGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/me')
            ->assertUnauthorized();
    }

    public function test_reports_catalogue_filters_and_preview(): void
    {
        $token = $this->login($this->superAdmin);
        $this->reloadGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonFragment(['type' => 'selling']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/reports/filters')
            ->assertOk()
            ->assertJsonStructure(['periods', 'routes', 'operators', 'payment_methods']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/reports/selling?period=yearly')
            ->assertOk()
            ->assertJsonStructure(['type', 'summary', 'rows', 'total_count']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/reports/unknown-type')
            ->assertNotFound();
    }
}
