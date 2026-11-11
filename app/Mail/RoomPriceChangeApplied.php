<?php

namespace App\Mail;

use App\Models\RoomPriceChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RoomPriceChangeApplied extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RoomPriceChangeRequest $change) {}

    public function build(): self
    {
        return $this->subject('Approved room rate applied · '.$this->change->reference)
            ->view('emails.room-price-change-applied');
    }
}
