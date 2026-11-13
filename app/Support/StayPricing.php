<?php

namespace App\Support;

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

    public static function durations(): array
    {
        return array_keys(self::RATES);
    }

    public static function rates(): array
    {
        return self::RATES;
    }

    public static function isOffered(int $hours, string $coolingType): bool
    {
        return isset(self::RATES[$hours][$coolingType]);
    }

    public static function rate(int $hours, string $coolingType): float
    {
        if (! self::isOffered($hours, $coolingType)) {
            throw ValidationException::withMessages([
                'cooling_type' => 'That cooling option is not offered for the selected stay length.',
            ]);
        }

        return (float) self::RATES[$hours][$coolingType];
    }

    public static function total(int $hours, string $coolingType, int $guests): float
    {
        $extraGuests = max(0, $guests - 2);

        return round(self::rate($hours, $coolingType) + $extraGuests * self::EXTRA_GUEST_RATE, 2);
    }

    public static function durationLabel(int $hours): string
    {
        return $hours === 720 ? '1 month' : $hours.' hours';
    }

    public static function startingRate(int $hours): float
    {
        return min(self::RATES[$hours] ?? [self::STARTING_PRICE]);
    }

    public static function checkoutAt(Carbon $checkIn, int $hours): Carbon
    {
        return $hours === 720 ? $checkIn->copy()->addMonthNoOverflow() : $checkIn->copy()->addHours($hours);
    }
}
