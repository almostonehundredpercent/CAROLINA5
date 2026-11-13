<?php

namespace App\Support;

use App\Models\Room;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/** Shared package rates used by the public booking flow. */
class StayPricing
{
    public const STARTING_PRICE = 350;
    public const EXTRA_GUEST_RATE = 300;
    public const EXTRA_HOUR_RATE = 100;

    private const RATES = [
        3 => ['aircon' => 350],
        6 => ['aircon' => 450],
        8 => ['aircon' => 550],
        12 => ['aircon' => 650],
        22 => ['fan' => 450, 'aircon' => 850],
        720 => ['aircon' => 12000],
    ];

    private const ROOM_SIX_SLUG = 'whole-house-panal-20-guests';

    public static function rates(?Room $room = null): array
    {
        $rates = self::RATES;
        if ($room?->slug === self::ROOM_SIX_SLUG) {
            unset($rates[3]);
        }

        return $rates;
    }

    public static function durations(?Room $room = null): array
    {
        return array_keys(self::rates($room));
    }

    public static function hasDuration(int $hours, ?Room $room = null): bool
    {
        return isset(self::rates($room)[$hours]);
    }

    public static function isOffered(int $hours, string $coolingType, ?Room $room = null): bool
    {
        return isset(self::rates($room)[$hours][$coolingType]);
    }

    public static function rate(int $hours, string $coolingType, ?Room $room = null): float
    {
        if (! self::isOffered($hours, $coolingType, $room)) {
            throw ValidationException::withMessages([
                'cooling_type' => 'That cooling option is not offered for the selected stay length.',
            ]);
        }

        return (float) self::rates($room)[$hours][$coolingType];
    }

    public static function total(int $hours, string $coolingType, int $guests, ?Room $room = null): float
    {
        $extraGuests = max(0, $guests - 2);

        return round(self::rate($hours, $coolingType, $room) + $extraGuests * self::EXTRA_GUEST_RATE, 2);
    }

    public static function durationLabel(int $hours): string
    {
        return $hours === 720 ? '1 month' : $hours.' hours';
    }

    public static function startingRate(int $hours, ?Room $room = null): float
    {
        return min(self::rates($room)[$hours] ?? [self::STARTING_PRICE]);
    }

    public static function startingPrice(?Room $room = null): float
    {
        return min(array_map(fn (array $rates): float => (float) min($rates), self::rates($room)));
    }

    public static function startingPackageLabel(?Room $room = null): string
    {
        $rates = self::rates($room);
        $hours = array_key_first($rates);
        $cooling = array_key_first($rates[$hours]);

        return self::durationLabel((int) $hours).' '.($cooling === 'fan' ? 'fan' : 'air-conditioned').' stay';
    }

    public static function packageOptionLabel(int $hours, ?Room $room = null): string
    {
        $rates = self::rates($room)[$hours] ?? [];
        $priceOptions = [];
        foreach ($rates as $cooling => $amount) {
            $type = $cooling === 'fan' ? 'Fan' : 'Air-conditioned';
            $priceOptions[] = $type.' ₱'.number_format((float) $amount);
        }

        return self::durationLabel($hours).' · '.implode(' / ', $priceOptions);
    }

    public static function checkoutAt(Carbon $checkIn, int $hours): Carbon
    {
        return $hours === 720 ? $checkIn->copy()->addMonthNoOverflow() : $checkIn->copy()->addHours($hours);
    }
}
