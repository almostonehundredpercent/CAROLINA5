<?php

namespace App\Models;

use App\Support\PropertyTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'image_url', 'room_type', 'beds', 'guests', 'price_per_night', 'rate_label', 'rental_hours', 'default_check_in_time', 'amenities', 'is_active', 'operational_status', 'operational_until'];

    protected function casts(): array
    {
        return ['amenities' => 'array', 'price_per_night' => 'decimal:2', 'rental_hours' => 'integer', 'is_active' => 'boolean', 'operational_until' => 'datetime'];
    }

    public function getRateLabelAttribute($value): string
    {
        return $this->rental_hours ? "per {$this->rental_hours}-hour stay" : 'per night';
    }

    public function responsiveImageSrcset(): ?string
    {
        $url = parse_url($this->image_url ?? '');
        if (! is_array($url) || ($url['scheme'] ?? null) !== 'https' || ($url['host'] ?? null) !== 'images.unsplash.com') {
            return null;
        }

        parse_str($url['query'] ?? '', $parameters);
        $base = 'https://images.unsplash.com'.($url['path'] ?? '');

        return collect([480 => 70, 800 => 75, 1000 => 80])
            ->map(function (int $quality, int $width) use ($base, $parameters): string {
                $options = array_merge($parameters, ['w' => $width, 'q' => $quality]);

                return $base.'?'.http_build_query($options).' '.$width.'w';
            })
            ->implode(', ');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function promoCodes()
    {
        return $this->belongsToMany(PromoCode::class);
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Booking::class);
    }

    public function blocks()
    {
        return $this->hasMany(RoomBlock::class);
    }

    public function approvedReviews()
    {
        return $this->hasMany(Review::class)->where('status', 'approved');
    }

    public static function releaseExpiredOperationalBlocks(): void
    {
        static::whereIn('operational_status', ['cleaning', 'maintenance'])
            ->whereNotNull('operational_until')->where('operational_until', '<=', PropertyTime::now())
            ->update(['operational_status' => 'available', 'operational_until' => null]);
    }
}
