<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\SmsGatewayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $smsGatewayService->sendBookingVerification($this->booking);
    }
}
