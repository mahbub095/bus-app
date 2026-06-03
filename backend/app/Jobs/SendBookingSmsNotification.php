<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\SmsGatewayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendBookingSmsNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Booking $booking)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(SmsGatewayService $smsGatewayService): void
    {
        $result = $smsGatewayService->sendBookingVerification($this->booking);

        if (! $result['success']) {
            Log::warning('Booking SMS was not sent.', [
                'booking_id' => $this->booking->id,
                'reason' => $result['message'],
            ]);
        }
    }
}
