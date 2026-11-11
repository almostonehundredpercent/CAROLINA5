<?php

namespace App\Mail;

use App\Models\RoomPriceChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RoomPriceChangeRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RoomPriceChangeRequest $change, public string $reviewUrl) {}

    public function build(): self
    {
        return $this->subject('Rate approval requested · '.$this->change->reference)
            ->view('emails.room-price-change-requested');
    }
}
