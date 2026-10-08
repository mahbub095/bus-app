<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForgotResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);
    }

    public function test_forgot_password_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@test.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_forgot_password_valid_email_creates_token(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'customer@test.com',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'code']);

        $resetToken = DB::table('password_reset_tokens')
            ->where('email', 'customer@test.com')
            ->first();

        $this->assertNotNull($resetToken);
        $this->assertTrue(Hash::check($response->json('code'), $resetToken->token));
    }

    public function test_reset_password_invalid_code(): void
    {
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'customer@test.com',
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'customer@test.com',
            'code' => '000000',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('code');
    }

    public function test_reset_password_valid_code_resets_password(): void
    {
        $forgotResponse = $this->postJson('/api/auth/forgot-password', [
            'email' => 'customer@test.com',
        ]);
        
        $code = $forgotResponse->json('code');

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'customer@test.com',
            'code' => $code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);

        $this->assertNull(DB::table('password_reset_tokens')->where('email', 'customer@test.com')->first());

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'customer@test.com',
            'password' => 'newpassword123',
        ]);

        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonStructure(['token', 'user']);
    }
}
