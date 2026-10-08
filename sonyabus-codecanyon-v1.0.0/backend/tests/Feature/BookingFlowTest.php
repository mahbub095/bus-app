<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Schedule;
use App\Models\SmsConfig;
use App\Models\User;
use App\Services\SmsGatewayService;
use App\Services\ZinipayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers:
 *  - Booking creation: bKash/Nagad/Card → SOLD  (Bug #2 regression)
 *  - Booking creation: Cash → BOOKED (admin only)
 *  - Booking creation: ZiniPay → PENDING + payment_url
 *  - Promo code discount applied correctly
 *  - Max 4 seats validation
 *  - Unavailable seat rejected
 *  - Customer cancel flow (PENDING, SOLD, already cancelled)
 *  - Seat hold / release
 *  - holdSeat blocks a seat with recent PENDING booking (Bug #5 regression)
 *  - Admin SMS sent for BOOKED (cash) bookings  (Bug #3 regression)
 *  - resolveStatus: bkash/nagad/card → SOLD, not BOOKED  (Bug #2 regression)
 */
class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $admin;
    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->customer = User::create([
            'name'     => 'Test Customer',
            'email'    => 'customer@flowtest.com',
            'password' => bcrypt('password123'),
            'role'     => 'user',
        ]);

        $this->admin = User::create([
            'name'     => 'Test Admin',
            'email'    => 'admin@flowtest.com',
            'password' => bcrypt('password123'),
            'role'     => 'admin',
        ]);

        $this->schedule = Schedule::first();

        // Disable real SMS sending in all tests
        $this->mock(SmsGatewayService::class, function ($mock) {
            $mock->shouldReceive('sendBookingVerification')
                ->andReturn(['success' => true, 'message' => 'SMS sent (mocked).']);
        });
    }

    // =========================================================================
    // resolveStatus / payment method → booking status
    // =========================================================================

    /** @test */
    public function bkash_payment_creates_booking_with_sold_status(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'Jane Doe',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'jane@test.com',
                'seat_numbers'    => 'F1',
                'payment_method'  => 'bKash',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('booking.status', 'SOLD');

        $this->assertDatabaseHas('bookings', [
            'seat_numbers'   => 'F1',
            'payment_method' => 'bKash',
            'status'         => 'SOLD',
        ]);
    }

    /** @test */
    public function nagad_payment_creates_booking_with_sold_status(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'Jane Doe',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'jane@test.com',
                'seat_numbers'    => 'F2',
                'payment_method'  => 'Nagad',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('booking.status', 'SOLD');
    }

    /** @test */
    public function card_payment_creates_booking_with_sold_status(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'Jane Doe',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'jane@test.com',
                'seat_numbers'    => 'F3',
                'payment_method'  => 'Card',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('booking.status', 'SOLD');
    }

    /** @test */
    public function zinipay_booking_creates_pending_status_and_returns_payment_url(): void
    {
        $this->mock(ZinipayService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')
                ->once()
                ->andReturn([
                    'payment_url' => 'https://pay.zinipay.com/invoice_test_001',
                    'invoice_id'  => 'invoice_test_001',
                ]);
        });

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'Jane Doe',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'jane@test.com',
                'seat_numbers'    => 'F4',
                'payment_method'  => 'ZiniPay',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('booking.status', 'PENDING')
            ->assertJsonStructure(['payment_url', 'invoice_id']);
    }

    /** @test */
    public function customer_cannot_create_cash_booking(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'Jane Doe',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'jane@test.com',
                'seat_numbers'    => 'G1',
                'payment_method'  => 'Cash',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('bookings', ['seat_numbers' => 'G1']);
    }

    /** @test */
    public function admin_can_create_cash_booking_with_booked_status(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'Cash Passenger',
                'passenger_phone' => '01799999999',
                'passenger_email' => 'cash@test.com',
                'seat_numbers'    => 'G2',
                'payment_method'  => 'Cash',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('booking.status', 'BOOKED');
    }

    // =========================================================================
    // Promo code
    // =========================================================================

    /** @test */
    public function valid_promo_code_reduces_total_fare(): void
    {
        $promo = Promotion::where('code', 'SONYANEW')->first();
        $baseFare = (float) $this->schedule->fare;

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'Promo User',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'promo@test.com',
                'seat_numbers'    => 'G3',
                'payment_method'  => 'bKash',
                'promo_code'      => 'SONYANEW',
            ]);

        $response->assertStatus(201);

        $booking = Booking::where('seat_numbers', 'G3')->first();
        $this->assertNotNull($booking);
        $expectedFare = max(0, $baseFare - $promo->discount_amount);
        $this->assertEquals($expectedFare, (float) $booking->total_fare);
    }

    /** @test */
    public function invalid_promo_code_does_not_change_fare(): void
    {
        $baseFare = (float) $this->schedule->fare;

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'No Promo User',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'nopromo@test.com',
                'seat_numbers'    => 'G4',
                'payment_method'  => 'bKash',
                'promo_code'      => 'INVALIDCODE999',
            ]);

        $response->assertStatus(201);

        $booking = Booking::where('seat_numbers', 'G4')->first();
        $this->assertEquals($baseFare, (float) $booking->total_fare);
    }

    // =========================================================================
    // Seat validation
    // =========================================================================

    /** @test */
    public function cannot_book_more_than_4_seats(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'Greedy User',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'greedy@test.com',
                'seat_numbers'    => 'F1,F2,F3,F4,F5',
                'payment_method'  => 'bKash',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'You can select a maximum of 4 seats per booking.');
    }

    /** @test */
    public function cannot_book_already_sold_seat(): void
    {
        // First, book seat E1
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'First User',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'first@test.com',
                'seat_numbers'    => 'E1',
                'payment_method'  => 'bKash',
            ])->assertStatus(201);

        // Create second customer, try to book same seat
        $secondCustomer = User::create([
            'name' => 'Second', 'email' => 'second@test.com',
            'password' => bcrypt('pass'), 'role' => 'user',
        ]);

        $response = $this->actingAs($secondCustomer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'Second User',
                'passenger_phone' => '01722222222',
                'passenger_email' => 'second@test.com',
                'seat_numbers'    => 'E1',
                'payment_method'  => 'Nagad',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Seat E1 is not available. Please select another seat.');
    }

    /** @test */
    public function cannot_book_blocked_seat(): void
    {
        // ScheduleSeeder blocks D3,D4 on first schedule, index 0
        $blockedSchedule = Schedule::where('blocked_seats', 'LIKE', '%D3%')->first();
        if (! $blockedSchedule) {
            $this->markTestSkipped('No schedule with blocked seats D3 found in seed data.');
        }

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $blockedSchedule->id,
                'passenger_name'  => 'Blocked Seat User',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'blocked@test.com',
                'seat_numbers'    => 'D3',
                'payment_method'  => 'bKash',
            ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // Cancel flow
    // =========================================================================

    /** @test */
    public function customer_can_cancel_pending_booking_immediately(): void
    {
        $booking = Booking::create([
            'user_id'         => $this->customer->id,
            'schedule_id'     => $this->schedule->id,
            'passenger_name'  => 'Cancel Test',
            'passenger_phone' => '01711111111',
            'passenger_email' => 'cancel@test.com',
            'seat_numbers'    => 'H1',
            'total_fare'      => 900.00,
            'payment_method'  => 'ZiniPay',
            'status'          => 'PENDING',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'CANCELLED');
        $this->assertEquals('CANCELLED', $booking->fresh()->status);
    }

    /** @test */
    public function customer_cancel_of_paid_booking_creates_cancel_request(): void
    {
        $booking = Booking::create([
            'user_id'         => $this->customer->id,
            'schedule_id'     => $this->schedule->id,
            'passenger_name'  => 'Cancel Test',
            'passenger_phone' => '01711111111',
            'passenger_email' => 'cancel@test.com',
            'seat_numbers'    => 'H2',
            'total_fare'      => 900.00,
            'payment_method'  => 'bKash',
            'status'          => 'SOLD',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'CANCEL_REQUESTED');
        $this->assertEquals('CANCEL_REQUESTED', $booking->fresh()->status);
    }

    /** @test */
    public function customer_cannot_cancel_another_users_booking(): void
    {
        $other = User::create([
            'name' => 'Other', 'email' => 'other@test.com',
            'password' => bcrypt('pass'), 'role' => 'user',
        ]);

        $booking = Booking::create([
            'user_id'         => $other->id,
            'schedule_id'     => $this->schedule->id,
            'passenger_name'  => 'Other User',
            'passenger_phone' => '01733333333',
            'passenger_email' => 'other@test.com',
            'seat_numbers'    => 'H3',
            'total_fare'      => 900.00,
            'payment_method'  => 'bKash',
            'status'          => 'SOLD',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(403);
        $this->assertEquals('SOLD', $booking->fresh()->status);
    }

    /** @test */
    public function cancelling_already_cancelled_booking_returns_400(): void
    {
        $booking = Booking::create([
            'user_id'         => $this->customer->id,
            'schedule_id'     => $this->schedule->id,
            'passenger_name'  => 'Already Cancelled',
            'passenger_phone' => '01711111111',
            'passenger_email' => 'cancelled@test.com',
            'seat_numbers'    => 'H4',
            'total_fare'      => 900.00,
            'payment_method'  => 'bKash',
            'status'          => 'CANCELLED',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Ticket is already cancelled.');
    }

    // =========================================================================
    // Seat hold / release
    // =========================================================================

    /** @test */
    public function customer_can_hold_an_available_seat(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/seats/hold', [
                'schedule_id' => $this->schedule->id,
                'seat_number' => 'I1',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'expires_at'])
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function hold_fails_if_seat_is_already_held_by_another_user(): void
    {
        // Use the SeatMapService directly to simulate a hold by another user
        // (HTTP-based holds cannot persist across separate test requests
        // when CACHE_STORE=array resets per-request; we test the service layer directly)
        $other = User::create([
            'name' => 'Holder', 'email' => 'holder@test.com',
            'password' => bcrypt('pass'), 'role' => 'user',
        ]);

        /** @var \App\Services\SeatMapService $seatMapService */
        $seatMapService = app(\App\Services\SeatMapService::class);

        // Other user holds I2
        $result = $seatMapService->holdSeat($this->schedule->id, 'I2', $other->id);
        $this->assertTrue($result['success'], 'Other user should be able to hold the seat.');

        // Current customer tries to hold same seat in the same request context (same cache)
        $result2 = $seatMapService->holdSeat($this->schedule->id, 'I2', $this->customer->id);
        $this->assertFalse($result2['success']);
        $this->assertEquals('Seat is temporarily reserved by another user.', $result2['message']);
    }

    /** @test */
    public function hold_fails_if_seat_has_recent_pending_booking(): void
    {
        // Create a PENDING booking for seat I3 (within 10-min window)
        Booking::create([
            'user_id'         => $this->admin->id,
            'schedule_id'     => $this->schedule->id,
            'passenger_name'  => 'Pending Passenger',
            'passenger_phone' => '01711111111',
            'passenger_email' => 'pending@test.com',
            'seat_numbers'    => 'I3',
            'total_fare'      => 900.00,
            'payment_method'  => 'ZiniPay',
            'status'          => 'PENDING',
            'created_at'      => now()->subMinutes(5), // within 10-min window
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/seats/hold', [
                'schedule_id' => $this->schedule->id,
                'seat_number' => 'I3',
            ]);

        // Bug #5 fix: seat should be unavailable due to recent PENDING booking
        $response->assertStatus(422)
            ->assertJsonPath('message', 'Seat is not available.');
    }

    /** @test */
    public function customer_can_release_their_own_seat_hold(): void
    {
        // Hold seat first
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/seats/hold', [
                'schedule_id' => $this->schedule->id,
                'seat_number' => 'I4',
            ])->assertStatus(200);

        // Release it
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/seats/release', [
                'schedule_id' => $this->schedule->id,
                'seat_number' => 'I4',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Another user can now hold the same seat
        $other = User::create([
            'name' => 'Releaser', 'email' => 'releaser@test.com',
            'password' => bcrypt('pass'), 'role' => 'user',
        ]);

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/seats/hold', [
                'schedule_id' => $this->schedule->id,
                'seat_number' => 'I4',
            ])->assertStatus(200);
    }

    // =========================================================================
    // Admin SMS on BOOKED (cash) – Bug #3 regression
    // =========================================================================

    /** @test */
    public function admin_cash_booking_triggers_sms_notification(): void
    {
        $smsMock = $this->mock(SmsGatewayService::class, function ($mock) {
            $mock->shouldReceive('sendBookingVerification')
                ->once()  // must be called exactly once
                ->andReturn(['success' => true, 'message' => 'SMS sent.']);
        });

        // Admin creates a cash (BOOKED) booking via admin panel CRUD route
        $this->admin->menu_permissions = ['bookings'];
        $this->admin->save();

        $this->actingAs($this->admin)
            ->post('/admin/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'SMS Test Passenger',
                'passenger_phone' => '01799999999',
                'passenger_email' => 'smstest@test.com',
                'passenger_gender'=> 'M',
                'seat_numbers'    => 'E2',
                'payment_method'  => 'Cash',
                'status'          => 'BOOKED',
                'total_fare'      => $this->schedule->fare,
            ]);

        // Mockery will assert ->once() was honoured at test teardown
    }

    // =========================================================================
    // My bookings list
    // =========================================================================

    /** @test */
    public function customer_can_retrieve_own_bookings(): void
    {
        Booking::create([
            'user_id'         => $this->customer->id,
            'schedule_id'     => $this->schedule->id,
            'passenger_name'  => 'My Booking',
            'passenger_phone' => '01711111111',
            'passenger_email' => 'mybooking@test.com',
            'seat_numbers'    => 'A4',
            'total_fare'      => 900.00,
            'payment_method'  => 'bKash',
            'status'          => 'SOLD',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/bookings/mine');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.passenger_name', 'My Booking');
    }

    /** @test */
    public function pnr_format_is_correct_in_booking_response(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', [
                'schedule_id'     => $this->schedule->id,
                'passenger_name'  => 'PNR Test',
                'passenger_phone' => '01711111111',
                'passenger_email' => 'pnr@test.com',
                'seat_numbers'    => 'E3',
                'payment_method'  => 'bKash',
            ]);

        $response->assertStatus(201);
        $pnr = $response->json('booking.pnr');
        $this->assertMatchesRegularExpression('/^SE\d{5}$/', $pnr,
            "PNR should be in format SE00001 but got: {$pnr}");
    }
}
