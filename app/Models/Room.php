<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    private const PUBLISHED_SHORT_STAY_HOURS = [
        'fan-room-solo' => 22,
        'air-conditioned-room-couple' => 6,
        'fan-room-couple' => 12,
        'air-conditioned-day-stay' => 12,
    ];
    protected $fillable = ['name', 'slug', 'description', 'image_url', 'room_type', 'beds', 'guests', 'price_per_night', 'rate_label', 'rental_hours', 'amenities', 'is_active', 'operational_status', 'operational_until'];
    protected function casts(): array { return ['amenities' => 'array', 'price_per_night' => 'decimal:2', 'rental_hours' => 'integer', 'is_active' => 'boolean', 'operational_until' => 'datetime']; }

    public function getRentalHoursAttribute($value): ?int
    {
        return self::PUBLISHED_SHORT_STAY_HOURS[$this->attributes['slug'] ?? ''] ?? ($value === null ? null : (int) $value);
    }

    public function getRateLabelAttribute($value): string
    {
        return $this->rental_hours ? "per {$this->rental_hours}-hour stay" : 'per night';
    }
    public function bookings() { return $this->hasMany(Booking::class); }
    public function approvedReviews() { return $this->hasMany(Review::class)->where('status', 'approved'); }

    public static function releaseExpiredOperationalBlocks(): void
    {
        static::whereIn('operational_status', ['cleaning', 'maintenance'])
            ->whereNotNull('operational_until')->where('operational_until', '<=', now())
            ->update(['operational_status' => 'available', 'operational_until' => null]);
    }
}
