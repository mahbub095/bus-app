<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SmsConfig;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularUser;
    protected ?string $originalEnvContent = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Backup .env
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $this->originalEnvContent = file_get_contents($envPath);
        }

        // Create standard users
        $this->superAdmin = User::create([
            'name' => 'Rowan Rau MD',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
        ]);

        $this->regularUser = User::create([
            'name' => 'Operator User',
            'email' => 'admin_operator@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'menu_permissions' => ['coach-services'],
        ]);
    }

    protected function tearDown(): void
    {
        // Restore .env
        $envPath = base_path('.env');
        if ($this->originalEnvContent !== null) {
            file_put_contents($envPath, $this->originalEnvContent);
        }

        parent::tearDown();
    }

    public function test_non_super_admins_cannot_access_gateway_settings_routes(): void
    {
        $this->actingAs($this->regularUser);

        // SMS Update should be blocked
        $response = $this->post(route('admin.gateway-settings.update-sms'), []);
        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasErrors();

        // Mail Update should be blocked
        $response = $this->post(route('admin.gateway-settings.update-mail'), []);
        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasErrors();

        // ZiniPay Update should be blocked
        $response = $this->post(route('admin.gateway-settings.update-zinipay'), []);
        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasErrors();

        // Try testing SMS
        $response = $this->post(route('admin.gateway-settings.test-sms'), []);
        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasErrors();

        // Try testing Email
        $response = $this->post(route('admin.gateway-settings.test-email'), []);
        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHasErrors();
    }

    public function test_super_admin_can_update_sms_settings_independently(): void
    {
        $this->actingAs($this->superAdmin);

        $payload = [
            'gateway_name' => 'Test SMSBD',
            'gateway_driver' => 'bulksmsbd',
            'api_url' => 'https://api.example.com/sms',
            'api_key' => 'smskey123',
            'sender_id' => 'Sender',
            'message_template' => 'Test {PNR}',
            'is_active' => 'true',
        ];

        $response = $this->post(route('admin.gateway-settings.update-sms'), $payload);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success');

        // Verify SMS config stored in DB
        $smsConfig = SmsConfig::first();
        $this->assertNotNull($smsConfig);
        $this->assertEquals('Test SMSBD', $smsConfig->gateway_name);
        $this->assertEquals('bulksmsbd', $smsConfig->gateway_driver);
        $this->assertEquals('https://api.example.com/sms', $smsConfig->api_url);
        $this->assertEquals('smskey123', $smsConfig->api_key);
        $this->assertTrue($smsConfig->is_active);

        // Verify .env file is updated
        $envContent = file_get_contents(base_path('.env'));
        $this->assertStringContainsString('SMS_GATEWAY_NAME="Test SMSBD"', $envContent);
        $this->assertStringContainsString('SMS_GATEWAY_DRIVER=bulksmsbd', $envContent);
        $this->assertStringContainsString('SMS_GATEWAY_API_URL=https://api.example.com/sms', $envContent);
        $this->assertStringContainsString('SMS_GATEWAY_API_KEY=smskey123', $envContent);
        $this->assertStringContainsString('SMS_GATEWAY_SENDER_ID=Sender', $envContent);
        $this->assertStringContainsString('SMS_GATEWAY_ACTIVE=true', $envContent);
        $this->assertStringContainsString('SMS_GATEWAY_MESSAGE_TEMPLATE="Test {PNR}"', $envContent);
    }

    public function test_super_admin_can_update_mail_settings_independently(): void
    {
        $this->actingAs($this->superAdmin);

        $payload = [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.testmail.com',
            'mail_port' => 587,
            'mail_username' => 'testuser',
            'mail_password' => 'testpass',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'sender@test.com',
            'mail_from_name' => 'Test Sender',
        ];

        $response = $this->post(route('admin.gateway-settings.update-mail'), $payload);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success');

        // Verify Mail stored in site_settings
        $this->assertEquals('smtp', SiteSetting::getValue('mail_mailer'));
        $this->assertEquals('smtp.testmail.com', SiteSetting::getValue('mail_host'));
        $this->assertEquals('587', SiteSetting::getValue('mail_port'));
        $this->assertEquals('testuser', SiteSetting::getValue('mail_username'));
        $this->assertEquals('testpass', SiteSetting::getValue('mail_password'));
        $this->assertEquals('tls', SiteSetting::getValue('mail_encryption'));
        $this->assertEquals('sender@test.com', SiteSetting::getValue('mail_from_address'));
        $this->assertEquals('Test Sender', SiteSetting::getValue('mail_from_name'));

        // Verify .env file is updated
        $envContent = file_get_contents(base_path('.env'));
        $this->assertStringContainsString('MAIL_MAILER=smtp', $envContent);
        $this->assertStringContainsString('MAIL_HOST=smtp.testmail.com', $envContent);
        $this->assertStringContainsString('MAIL_PORT=587', $envContent);
        $this->assertStringContainsString('MAIL_USERNAME=testuser', $envContent);
        $this->assertStringContainsString('MAIL_PASSWORD=testpass', $envContent);
        $this->assertStringContainsString('MAIL_ENCRYPTION=tls', $envContent);
        $this->assertStringContainsString('MAIL_FROM_ADDRESS=sender@test.com', $envContent);
        $this->assertStringContainsString('MAIL_FROM_NAME="Test Sender"', $envContent);
    }

    public function test_super_admin_can_update_zinipay_settings_independently(): void
    {
        $this->actingAs($this->superAdmin);

        $payload = [
            'zinipay_api_key' => 'zini_key_123',
            'zinipay_base_url' => 'https://api.testzinipay.com',
        ];

        $response = $this->post(route('admin.gateway-settings.update-zinipay'), $payload);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success');

        // Verify ZiniPay stored in site_settings
        $this->assertEquals('zini_key_123', SiteSetting::getValue('zinipay_api_key'));
        $this->assertEquals('https://api.testzinipay.com', SiteSetting::getValue('zinipay_base_url'));

        // Verify .env file is updated
        $envContent = file_get_contents(base_path('.env'));
        $this->assertStringContainsString('ZINIPAY_API_KEY=zini_key_123', $envContent);
        $this->assertStringContainsString('ZINIPAY_BASE_URL=https://api.testzinipay.com', $envContent);
    }

    public function test_super_admin_can_send_test_email(): void
    {
        Mail::fake();
        $this->actingAs($this->superAdmin);

        $response = $this->post(route('admin.gateway-settings.test-email'), [
            'test_email' => 'receiver@test.com',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success');

        Mail::assertSent(\App\Mail\SystemTestMail::class, function (\App\Mail\SystemTestMail $mail) {
            return $mail->hasTo('receiver@test.com');
        });
    }

    public function test_super_admin_can_send_test_sms(): void
    {
        Http::fake([
            'api.sms.net.bd/*' => Http::response(['success' => true, 'message' => 'SMS submitted'], 200),
        ]);

        // Seed default config
        SmsConfig::create([
            'gateway_name' => 'SMS.NET.BD',
            'gateway_driver' => 'smsnetbd',
            'api_url' => 'https://api.sms.net.bd/sendsms',
            'api_key' => 'somekey',
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin);

        $response = $this->post(route('admin.gateway-settings.test-sms'), [
            'test_phone' => '01711112222',
            'test_message' => 'Hello test',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success');
    }
}
