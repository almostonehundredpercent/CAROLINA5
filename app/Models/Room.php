<?php

namespace App\Models;

use App\Support\PropertyTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        if ($value !== null) {
            return (string) $value;
        }

        return $this->rental_hours ? "per {$this->rental_hours}-hour stay" : 'per night';
    }

    public function getBookingGuestLimitAttribute(): int
    {
        return max(2, (int) ($this->attributes['guests'] ?? 2));
    }

    public function versionedImageUrl(?string $imageUrl = null): ?string
    {
        $imageUrl ??= $this->image_url;
        $parts = parse_url($imageUrl ?? '');
        $path = is_array($parts) ? ($parts['path'] ?? null) : null;
        $roomFiveFallback = $this->slug === 'whole-house-panal-10-guests'
            ? asset('images/rooms/room-5-angle-1.jpg')
            : null;

        // If Room 5 has an old relative path from an admin upload, verify it
        // against the public disk instead of emitting a URL that breaks on this route.
        if ($roomFiveFallback && is_string($path) && ! isset($parts['scheme'])) {
            if (str_starts_with($path, '/images/rooms/')) {
                if (! is_file(public_path(ltrim($path, '/')))) {
                    return $this->versionedImageUrl($roomFiveFallback);
                }
            } else {
                $storedPath = ltrim(preg_replace('~^/?storage/~', '', $path), '/');

                if ($storedPath !== '' && ! Storage::disk('public')->exists($storedPath)) {
                    return $this->versionedImageUrl($roomFiveFallback);
                }
            }
        }

        // Admin uploads are stored on Laravel's public disk. Convert the saved
        // disk path to an absolute /storage URL so it works from nested routes.
        if (is_string($path)
            && preg_match('~^(?:/)?(?:storage/)?room-images/([A-Za-z0-9._-]+)$~', $path, $matches)) {
            $storedPath = 'room-images/'.$matches[1];
            $disk = Storage::disk('public');

            if ($disk->exists($storedPath)) {
                $storedUrl = $disk->url($storedPath);
                $storedParts = parse_url($storedUrl);
                $query = [];
                parse_str(is_array($storedParts) ? ($storedParts['query'] ?? '') : '', $query);

                try {
                    $query['v'] = $disk->lastModified($storedPath);
                } catch (\Throwable) {
                    // The URL remains usable if the storage driver has no mtime support.
                }

                $origin = is_array($storedParts) && isset($storedParts['scheme'], $storedParts['host'])
                    ? $storedParts['scheme'].'://'.$storedParts['host'].(isset($storedParts['port']) ? ':'.$storedParts['port'] : '')
                    : '';

                return $origin.($storedParts['path'] ?? $storedUrl)
                    .(count($query) ? '?'.http_build_query($query) : '')
                    .(is_array($storedParts) && isset($storedParts['fragment']) ? '#'.$storedParts['fragment'] : '');
            }

            // Render deployments may not retain uploaded files between deploys.
            // Keep Room 5 visible using its bundled photo if that upload is gone.
            if ($roomFiveFallback) {
                return $this->versionedImageUrl($roomFiveFallback);
            }
        }

        if (! $imageUrl && $roomFiveFallback) {
            return $this->versionedImageUrl($roomFiveFallback);
        }

        if (! is_string($path) || ! preg_match('~^/images/rooms/(room-\d+-angle-[1-4]\.(?:jpg|png)|room-\d+-angle-[1-4]-\d+\.webp)$~', $path, $matches)) {
            return $imageUrl;
        }

        $filePath = public_path('images/rooms/'.$matches[1]);
        if (! is_file($filePath)) {
            return $imageUrl;
        }

        $query = [];
        parse_str(is_array($parts) ? ($parts['query'] ?? '') : '', $query);
        $query['v'] = substr(sha1_file($filePath), 0, 12);
        $origin = is_array($parts) && isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : '';

        return $origin.$path.'?'.http_build_query($query).(is_array($parts) && isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
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

    public function responsiveWebpSrcset(?string $imageUrl = null): ?string
    {
        $path = parse_url($imageUrl ?? $this->image_url ?? '', PHP_URL_PATH);
        if (! is_string($path) || ! preg_match('~^/images/rooms/(room-\d+-angle-[1-4])\.jpg$~', $path, $matches)) {
            return null;
        }

        $variants = collect([480, 800, 1200])
            ->map(fn (int $width): string => "{$matches[1]}-{$width}")
            ->filter(fn (string $filename): bool => is_file(public_path("images/rooms/{$filename}.webp")))
            ->map(fn (string $filename): string => $this->versionedImageUrl(asset("images/rooms/{$filename}.webp")).' '.substr($filename, strrpos($filename, '-') + 1).'w');

        return $variants->isEmpty() ? null : $variants->implode(', ');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function priceChangeRequests()
    {
        return $this->hasMany(RoomPriceChangeRequest::class);
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
