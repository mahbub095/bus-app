<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AdminSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers the admin-configurable session expiry (Site Settings → Session
 * & Security):
 *  - the value is persisted and validated from the admin form
 *  - it reaches session.lifetime and the "remember me" cookie duration
 *  - an idle admin is signed out exactly at the configured cut-off
 *  - customer API tokens are out of scope
 */
class AdminSessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin routes sit behind the license gate.
        License::create([
            'purchase_code' => 'session-test-license',
            'license_type' => 'Regular License',
            'is_verified' => true,
            'verified_at' => now(),
        ]);
        Cache::forget('license_verified');

        $this->superAdmin = User::create([
            'name' => 'Session Super Admin',
            'email' => 'sessionadmin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
        ]);
    }

    private function setTimeout(int $minutes): void
    {
        SiteSetting::setValue(AdminSessionService::SETTING_KEY, (string) $minutes);
        SiteSetting::clearCache();
    }

    /** @return array<string, mixed> */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'site_title' => 'SonyaBus Test',
            'footer_company_name' => 'SonyaBus Test Ltd',
            'footer_copyright' => '© 2026 SonyaBus Test Ltd.',
            'admin_session_lifetime' => 45,
        ], $overrides);
    }

    public function test_session_expiry_is_saved_from_site_settings_form(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/site-settings', $this->settingsPayload(['admin_session_lifetime' => 45]));

        $response->assertRedirect('/admin');
        $response->assertSessionHasNoErrors();

        $this->assertSame('45', SiteSetting::getValue(AdminSessionService::SETTING_KEY));
        $this->assertSame(45, app(AdminSessionService::class)->minutes());
    }

    public function test_session_expiry_outside_allowed_range_is_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post('/admin/site-settings', $this->settingsPayload(['admin_session_lifetime' => 0]));

        $response->assertSessionHasErrors('admin_session_lifetime');

        // The rejected value never overwrote the stored default.
        $this->assertSame(
            (string) AdminSessionService::DEFAULT_MINUTES,
            SiteSetting::getValue(AdminSessionService::SETTING_KEY)
        );
        $this->assertSame(AdminSessionService::DEFAULT_MINUTES, app(AdminSessionService::class)->minutes());
    }

    public function test_configured_expiry_reaches_session_and_remember_config(): void
    {
        $this->setTimeout(45);

        $this->actingAs($this->superAdmin)->get('/admin')->assertOk();

        $this->assertSame(45, config('session.lifetime'));
        // "Remember me" must never outlive the configured policy.
        $this->assertSame(45, config('auth.guards.web.remember'));
    }

    public function test_admin_is_signed_out_after_idle_timeout(): void
    {
        $this->setTimeout(10);

        $this->actingAs($this->superAdmin)->get('/admin')->assertOk();

        $this->travel(11)->minutes();

        $this->get('/admin')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_admin_stays_signed_in_within_timeout(): void
    {
        $this->setTimeout(10);

        $this->actingAs($this->superAdmin)->get('/admin')->assertOk();

        $this->travel(9)->minutes();

        $this->get('/admin')->assertOk();
        $this->assertAuthenticated('web');
    }

    public function test_activity_extends_the_idle_window(): void
    {
        $this->setTimeout(10);

        $this->actingAs($this->superAdmin)->get('/admin')->assertOk();

        $this->travel(8)->minutes();
        $this->get('/admin')->assertOk();

        // 16 minutes after login, but only 8 since the last request.
        $this->travel(8)->minutes();
        $this->get('/admin')->assertOk();
    }

    public function test_customer_api_tokens_are_not_affected(): void
    {
        $customer = User::create([
            'name' => 'Session Customer',
            'email' => 'sessioncustomer@test.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $token = $customer->createToken('frontend-api')->plainTextToken;

        $this->setTimeout(1);
        $this->travel(10)->minutes();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'sessioncustomer@test.com');
    }
}
