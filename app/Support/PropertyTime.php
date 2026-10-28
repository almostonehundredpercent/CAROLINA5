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
        $localNow = Carbon::now('Asia/Manila');

        return Carbon::parse($localNow->format('Y-m-d H:i:s'), 'UTC');
    }

    public static function today(): string
    {
        return Carbon::now('Asia/Manila')->toDateString();
    }
}
