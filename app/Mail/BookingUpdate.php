<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingUpdate extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Booking $booking, public string $subjectLine, public string $messageLine) {}
    public function build(): self { return $this->subject($this->subjectLine)->view('emails.booking-update'); }
}
