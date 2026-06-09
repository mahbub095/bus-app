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
        $this->assertEquals('PAID', $booking->status);
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
        $this->assertEquals('PAID', $booking->status);
    }
}
