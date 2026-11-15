<?php

namespace App\Support;

use App\Models\PromoCode;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PromoPricing
{
    /** @return array{promo: PromoCode, original: float, discount: float, total: float} */
    public static function quote(string $code, Room $room, int $hours, float $original, ?CarbonInterface $checkIn = null, bool $lock = false): array
    {
        $normalized = Str::upper(trim($code));
        $query = PromoCode::query()->where('code', $normalized);
        if ($lock) {
            $query->lockForUpdate();
        }
        $promo = $query->first();

        if (! $promo || ! $promo->is_active || $promo->review_status !== 'approved') {
            throw ValidationException::withMessages(['promo_code' => 'This promo code is not valid.']);
        }
        if (($promo->starts_at && now()->lt($promo->starts_at)) || ($promo->ends_at && now()->gt($promo->ends_at))) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code is not currently available.']);
        }
        if ($promo->stay_start_date || $promo->stay_end_date || $promo->stay_day_type) {
            if (! $checkIn) {
                throw ValidationException::withMessages(['promo_code' => 'Choose the stay check-in date before applying this promo.']);
            }
            $stayDate = $checkIn->toDateString();
            if (($promo->stay_start_date && $stayDate < $promo->stay_start_date->toDateString())
                || ($promo->stay_end_date && $stayDate > $promo->stay_end_date->toDateString())) {
                throw ValidationException::withMessages(['promo_code' => 'This promo is not available for the selected stay date.']);
            }
            if (($promo->stay_day_type === 'weekday' && $checkIn->isWeekend())
                || ($promo->stay_day_type === 'weekend' && ! $checkIn->isWeekend())) {
                throw ValidationException::withMessages(['promo_code' => 'This promo is not available for the selected check-in day.']);
            }
        }
        if ($promo->usage_limit !== null && $promo->times_used >= $promo->usage_limit) {
            throw ValidationException::withMessages(['promo_code' => 'This promo has reached its redemption limit.']);
        }
        if (! $promo->rooms()->exists() || ! $promo->rooms()->whereKey($room->id)->exists()) {
            throw ValidationException::withMessages(['promo_code' => 'This promo is available only for selected rooms.']);
        }
        if ($promo->minimum_hours !== null && $hours < $promo->minimum_hours) {
            throw ValidationException::withMessages(['promo_code' => 'This promo requires a stay of at least '.self::duration($promo->minimum_hours).'.']);
        }
        if ($promo->maximum_hours !== null && $hours > $promo->maximum_hours) {
            throw ValidationException::withMessages(['promo_code' => 'This promo is limited to a stay of '.self::duration($promo->maximum_hours).'.']);
        }

        $discount = match ($promo->discount_type) {
            'percentage' => round($original * min(100, (float) $promo->discount_value) / 100, 2),
            'fixed_amount' => min($original, (float) $promo->discount_value),
            'fixed_total' => max(0, $original - (float) $promo->discount_value),
        };

        if ($discount <= 0) {
            throw ValidationException::withMessages(['promo_code' => 'This promo does not reduce the price of this stay.']);
        }

        return ['promo' => $promo, 'original' => $original, 'discount' => $discount, 'total' => max(0, $original - $discount)];
    }

    private static function duration(int $hours): string
    {
        if ($hours === 720) {
            return '1 month';
        }

        return $hours % 24 === 0 ? ($hours / 24).' days' : $hours.' hours';
    }
}
