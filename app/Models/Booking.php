<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = ['user_id', 'guest_name', 'guest_email', 'guest_phone', 'room_id', 'reference', 'check_in', 'check_out', 'check_in_at', 'check_out_at', 'booking_type', 'hours', 'guests', 'nights', 'total_amount', 'hold_expires_at', 'status', 'payment_method', 'special_request', 'add_ons', 'staff_notes', 'checked_in_at', 'checked_out_at'];
    protected function casts(): array { return ['check_in' => 'date', 'check_out' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime', 'total_amount' => 'decimal:2', 'add_ons' => 'array', 'hold_expires_at' => 'datetime', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime']; }
    protected static function booted(): void { static::creating(function (Booking $booking) { $booking->reference ??= 'CAR-' . strtoupper(Str::random(8)); }); }
    public function user() { return $this->belongsTo(User::class); }
    public function room() { return $this->belongsTo(Room::class); }
    public function activityLogs() { return $this->hasMany(ActivityLog::class); }
    public function review() { return $this->hasOne(Review::class); }

    /** Only staff-confirmed stays block inventory; requests are a queue, not a promise. */
    public function scopeBlocking($query)
    {
        return $query->where('status', 'confirmed');
    }

    public static function releaseExpiredHolds(): void
    {
        static::where('status', 'pending')->whereNotNull('hold_expires_at')->where('hold_expires_at', '<=', now())
            ->update(['status' => 'cancelled']);
    }

    public function scopeOverlapping($query, $startsAt, $endsAt)
    {
        return $query->where(function ($query) use ($startsAt, $endsAt) {
            $query->where(fn ($timed) => $timed->whereNotNull('check_in_at')->where('check_in_at', '<', $endsAt)->where('check_out_at', '>', $startsAt))
                ->orWhere(fn ($legacy) => $legacy->whereNull('check_in_at')->where('check_in', '<', $endsAt->toDateString())->where('check_out', '>', $startsAt->toDateString()));
        });
    }
}
