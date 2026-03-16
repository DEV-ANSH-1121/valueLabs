<?php

namespace App\Jobs;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Booking $booking) {}

    public function handle(): void
    {
        Mail::to($this->booking->email)->send(
            new BookingConfirmationMail($this->booking),
        );
    }
}
