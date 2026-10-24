<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingTimeAdjustment extends Model
{
    protected $fillable = [
        'booking_id', 'room_block_id', 'payment_id', 'created_by', 'type', 'hours', 'amount',
        'previous_start', 'previous_end', 'proposed_start', 'proposed_end', 'status', 'expires_at',
        'applied_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'previous_start' => 'datetime', 'previous_end' => 'datetime',
            'proposed_start' => 'datetime', 'proposed_end' => 'datetime', 'expires_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomBlock()
    {
        return $this->belongsTo(RoomBlock::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function quoteAmount(Booking $booking, int $hours): float
    {
        $ratePeriodHours = max(1, (int) ($booking->room?->rental_hours ?: 24));

        return round((float) $booking->room?->price_per_night * ($hours / $ratePeriodHours), 2);
    }
}
