<?php

namespace App\Services;

use App\Mail\GuestReviewInvitation;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class GuestReviewInvitationService
{
    public function send(Booking $booking): bool
    {
        $recipient = $booking->guest_email ?: $booking->user?->email;
        if (! $recipient || ! $booking->checked_out_at || $booking->status === 'cancelled') {
            return false;
        }

        $claimed = DB::transaction(function () use ($booking) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (! $locked->checked_out_at || $locked->status === 'cancelled' || $locked->review_invited_at || $locked->review()->exists()) {
                return false;
            }
            $locked->review_invited_at = now();
            $locked->saveQuietly();

            return true;
        });

        if (! $claimed) {
            return false;
        }

        $booking->loadMissing(['room', 'user']);
        $reviewUrl = URL::temporarySignedRoute(
            'bookings.guest-review.form',
            $booking->checked_out_at->copy()->addDays(30),
            ['booking' => $booking->id],
        );

        try {
            Mail::to($recipient)->send(new GuestReviewInvitation($booking, $reviewUrl));

            return true;
        } catch (Throwable $exception) {
            Booking::whereKey($booking->id)->update(['review_invited_at' => null]);
            report($exception);

            return false;
        }
    }
}
