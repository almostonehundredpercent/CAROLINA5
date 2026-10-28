<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Carolina stores booking and room-operation times as property-local wall
 * clocks in UTC-formatted timestamps. Use the Manila clock value in that same
 * representation whenever comparing an interval with the current time.
 */
class PropertyTime
{
    public static function now(): Carbon
    {
        return self::wallClock(Carbon::now('UTC'));
    }

    /** Convert a real UTC instant to the property's wall clock stored as UTC. */
    public static function wallClock(Carbon $instant): Carbon
    {
        $localTime = $instant->copy()->setTimezone('Asia/Manila');

        return Carbon::parse($localTime->format('Y-m-d H:i:s'), 'UTC');
    }

    public static function today(): string
    {
        return Carbon::now('Asia/Manila')->toDateString();
    }
}
