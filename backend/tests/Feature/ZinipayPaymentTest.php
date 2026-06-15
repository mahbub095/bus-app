<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Schedule;
use App\Services\ZinipayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZinipayPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_booking_lookup()
    {
        $schedule = Schedule::first();
        
        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => 'Alice Doe',
            'passenger_phone' => '01712345678',
            'passenger_email' => 'alice@example.com',
            'passenger_gender' => 'F',
            'seat_numbers' => 'D1',
            'total_fare' => 450.00,
            'payment_method' => 'ZiniPay',
            'status' => 'PENDING',
        ]);

        $response = $this->getJson("/api/bookings/public/{$booking->id}");

        $response->assertStatus(200)
            ->assertJsonPath('passenger_name', 'Alice Doe')
            ->assertJsonPath('status', 'PENDING');
    }

    public function test_callback_handles_completed_payment()
    {
        $schedule = Schedule::first();

        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => 'Alice Doe',
            'passenger_phone' => '01712345678',
            'passenger_email' => 'alice@example.com',
            'passenger_gender' => 'F',
            'seat_numbers' => 'D1',
            'total_fare' => 450.00,
            'payment_method' => 'ZiniPay',
            'status' => 'PENDING',
            'payment_invoice_id' => 'invoice_123',
        ]);

        // Mock ZinipayService
        $this->mock(ZinipayService::class, function ($mock) {
            $mock->shouldReceive('verifyPayment')
                ->once()
                ->with('invoice_123')
                ->andReturn('COMPLETED');
        });

        $response = $this->get("/payment/callback?booking_id={$booking->id}&source=frontend");

        $response->assertRedirect();
        $this->assertStringContainsString('payment=success', $response->headers->get('Location'));
        
        $booking->refresh();
        $this->assertEquals('SOLD', $booking->status);
    }

    public function test_webhook_verifies_and_completes_payment()
    {
        $schedule = Schedule::first();

        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => 'Alice Doe',
            'passenger_phone' => '01712345678',
            'passenger_email' => 'alice@example.com',
            'passenger_gender' => 'F',
            'seat_numbers' => 'D1',
            'total_fare' => 450.00,
            'payment_method' => 'ZiniPay',
            'status' => 'PENDING',
            'payment_invoice_id' => 'invoice_456',
        ]);

        $this->mock(ZinipayService::class, function ($mock) {
            $mock->shouldReceive('verifyPayment')
                ->once()
                ->with('invoice_456')
                ->andReturn('COMPLETED');
        });

        $response = $this->postJson("/api/payment/webhook", [
            'invoice_id' => 'invoice_456',
            'status' => 'COMPLETED',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Payment verified and booking completed');

        $booking->refresh();
        $this->assertEquals('SOLD', $booking->status);
    }

    public function test_callback_handles_cancelled_payment()
    {
        $schedule = Schedule::first();

        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => 'Alice Doe',
            'passenger_phone' => '01712345678',
            'passenger_email' => 'alice@example.com',
            'passenger_gender' => 'F',
            'seat_numbers' => 'D1',
            'total_fare' => 450.00,
            'payment_method' => 'ZiniPay',
            'status' => 'PENDING',
        ]);

        $response = $this->get("/payment/cancel?booking_id={$booking->id}&source=frontend");

        $response->assertRedirect();
        $this->assertStringContainsString('payment=cancelled', $response->headers->get('Location'));
        
        $booking->refresh();
        $this->assertEquals('CANCELLED', $booking->status); // must be CANCELLED so seat is released
    }

    public function test_customer_cannot_create_cash_booking()
    {
        $schedule = Schedule::first();
        $customer = \App\Models\User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $response = $this->actingAs($customer)
            ->postJson("/api/bookings", [
                'schedule_id' => $schedule->id,
                'passenger_name' => 'Alice Doe',
                'passenger_phone' => '01712345678',
                'passenger_email' => 'alice@example.com',
                'passenger_gender' => 'F',
                'seat_numbers' => 'D2',
                'payment_method' => 'Cash',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_cash_booking()
    {
        $schedule = Schedule::first();
        $admin = \App\Models\User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/bookings", [
                'schedule_id' => $schedule->id,
                'passenger_name' => 'Alice Doe',
                'passenger_phone' => '01712345678',
                'passenger_email' => 'alice@example.com',
                'passenger_gender' => 'F',
                'seat_numbers' => 'D5',
                'payment_method' => 'Cash',
            ]);

        $response->assertStatus(201);
        
        $booking = Booking::where('seat_numbers', 'D5')->first();
        $this->assertNotNull($booking);
        $this->assertEquals('BOOKED', $booking->status);
    }

    public function test_callback_handles_failed_payment()
    {
        $schedule = Schedule::first();

        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => 'Alice Doe',
            'passenger_phone' => '01712345678',
            'passenger_email' => 'alice@example.com',
            'passenger_gender' => 'F',
            'seat_numbers' => 'D6',
            'total_fare' => 450.00,
            'payment_method' => 'ZiniPay',
            'status' => 'PENDING',
            'payment_invoice_id' => 'invoice_789',
        ]);

        $this->mock(ZinipayService::class, function ($mock) {
            $mock->shouldReceive('verifyPayment')
                ->once()
                ->with('invoice_789')
                ->andReturn('FAILED');
        });

        $response = $this->get("/payment/callback?booking_id={$booking->id}&source=frontend");

        $response->assertRedirect();
        
        $booking->refresh();
        $this->assertEquals('CANCELLED', $booking->status); // must be CANCELLED so seat is released
    }

    public function test_webhook_verifies_and_fails_payment()
    {
        $schedule = Schedule::first();

        $booking = Booking::create([
            'schedule_id' => $schedule->id,
            'passenger_name' => 'Alice Doe',
            'passenger_phone' => '01712345678',
            'passenger_email' => 'alice@example.com',
            'passenger_gender' => 'F',
            'seat_numbers' => 'D7',
            'total_fare' => 450.00,
            'payment_method' => 'ZiniPay',
            'status' => 'PENDING',
            'payment_invoice_id' => 'invoice_012',
        ]);

        $this->mock(ZinipayService::class, function ($mock) {
            $mock->shouldReceive('verifyPayment')
                ->once()
                ->with('invoice_012')
                ->andReturn('FAILED');
        });

        $response = $this->postJson("/api/payment/webhook", [
            'invoice_id' => 'invoice_012',
            'status' => 'FAILED',
        ]);

        $response->assertStatus(200);

        $booking->refresh();
        $this->assertEquals('CANCELLED', $booking->status); // must be CANCELLED so seat is released
    }
}
