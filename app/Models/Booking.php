<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Booking extends Model
{
    protected $fillable = ['user_id', 'guest_name', 'guest_email', 'guest_phone', 'room_id', 'promo_code_id', 'reference', 'submission_token', 'check_in', 'check_out', 'check_in_at', 'check_out_at', 'booking_type', 'hours', 'guests', 'children_count', 'pets_count', 'nights', 'total_amount', 'promo_code', 'original_amount', 'discount_amount', 'hold_expires_at', 'status', 'payment_method', 'payment_status', 'paid_at', 'special_request', 'add_ons', 'staff_notes', 'checked_in_at', 'checked_out_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['check_in' => 'date', 'check_out' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime', 'total_amount' => 'decimal:2', 'original_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'add_ons' => 'array', 'hold_expires_at' => 'datetime', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->reference ??= 'CAR-'.strtoupper(Str::random(8));
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function refundCutoffAt()
    {
        $checkIn = $this->check_in_at ?? $this->check_in->copy()->startOfDay();

        return $checkIn->copy()->subDays(3);
    }

    public function isRefundEligible(): bool
    {
        $cancelledOrRequestedAt = $this->cancelled_at ?? now();

        return $cancelledOrRequestedAt->lte($this->refundCutoffAt());
    }

    public function getOperationalStatusAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }
        if ($this->checked_out_at) {
            return 'checked_out';
        }
        if ($this->checked_in_at) {
            return 'checked_in';
        }

        return $this->status;
    }

    public static function holdMinutes(): int
    {
        return max(1, min(1440, (int) config('booking.hold_minutes', 30)));
    }

    public function hasProtectedInventory(): bool
    {
        return $this->status === 'confirmed' || $this->checked_in_at || $this->paid_at || $this->deposit_verified_at
            || in_array($this->payment_status, ['paid', 'refunded'], true)
            || ($this->exists && $this->payments()->whereIn('status', ['paid', 'refunded'])->exists());
    }

    public function hasExpiredHold(): bool
    {
        $deadline = $this->holdDeadline();

        return $this->status === 'pending' && ! $this->hasProtectedInventory() && $deadline && $deadline->lte(now());
    }

    public function holdDeadline()
    {
        return $this->hold_expires_at ?? $this->created_at?->copy()->addMinutes(static::holdMinutes());
    }

    /** One shared inventory rule, including ledger and verified legacy deposits. */
    public function scopeBlocking($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed'])->whereNull('checked_out_at')
            ->where(fn ($active) => $active->where('status', 'confirmed')
                ->orWhereNotNull('checked_in_at')->orWhereNotNull('paid_at')->orWhereNotNull('deposit_verified_at')
                ->orWhereIn('payment_status', ['paid', 'refunded'])
                ->orWhereHas('payments', fn ($paid) => $paid->whereIn('status', ['paid', 'refunded']))
                ->orWhere('hold_expires_at', '>', now())
                ->orWhere(fn ($legacy) => $legacy->whereNull('hold_expires_at')->where('created_at', '>', now()->subMinutes(static::holdMinutes()))));
    }

    public function scopeUnpaidUnconfirmed($query)
    {
        return $query->where('status', 'pending')->whereNull('checked_in_at')->whereNull('checked_out_at')
            ->whereNull('paid_at')->whereNull('deposit_verified_at')
            ->where(fn ($unpaid) => $unpaid->whereNull('payment_status')->orWhereNotIn('payment_status', ['paid', 'refunded']))
            ->whereDoesntHave('payments', fn ($paid) => $paid->whereIn('status', ['paid', 'refunded']));
    }

    public function save(array $options = [])
    {
        if ($this->status === 'pending' && ! $this->hasProtectedInventory() && ! $this->hold_expires_at) {
            $this->hold_expires_at = now()->addMinutes(static::holdMinutes());
        }
        if (! in_array($this->status, ['pending', 'confirmed'], true) || $this->checked_out_at
            || ($this->exists && ! $this->isDirty(['room_id', 'status', 'check_in', 'check_out', 'check_in_at', 'check_out_at', 'checked_out_at', 'hold_expires_at']))) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            $room = Room::whereKey($this->room_id)->lockForUpdate()->firstOrFail();
            $start = $this->check_in_at ?? $this->check_in->copy()->startOfDay();
            $end = $this->check_out_at ?? $this->check_out->copy()->startOfDay();
            $query = $room->bookings()->blocking()->overlapping($start, $end);
            if ($this->exists) {
                $query->whereKeyNot($this->id);
            }
            if ($query->exists() || $room->blocks()->overlapping($start, $end)->exists()) {
                throw ValidationException::withMessages(['availability' => 'This room is reserved during part of your stay. Please choose another date or time.']);
            }

            return parent::save($options);
        });
    }

    public static function releaseExpiredHolds(): void
    {
        // Availability excludes expired holds directly; cleanup is optional.
        static::unpaidUnconfirmed()->whereNotNull('hold_expires_at')->where('hold_expires_at', '<=', now())
            ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Unpaid reservation hold expired.']);
    }

    public function scopeOverlapping($query, $startsAt, $endsAt)
    {
        return $query->where(function ($query) use ($startsAt, $endsAt) {
            $query->where(fn ($timed) => $timed->whereNotNull('check_in_at')->where('check_in_at', '<', $endsAt)->where('check_out_at', '>', $startsAt))
                ->orWhere(fn ($legacy) => $legacy->whereNull('check_in_at')->whereDate('check_in', '<', $endsAt->copy()->ceilDay()->toDateString())->whereDate('check_out', '>', $startsAt->toDateString()));
        });
    }
}
