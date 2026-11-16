<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GuestReviewInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public string $reviewUrl) {}

    public function build(): self
    {
        return $this->subject('How was your stay at Carolina?')->view('emails.guest-review-invitation');
    }
}
