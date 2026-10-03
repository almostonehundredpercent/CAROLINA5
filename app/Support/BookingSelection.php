<?php

namespace App\Support;

use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingSelection
{
    public static function query(Request $request): array
    {
        $rules = [
            'check_in' => ['date_format:Y-m-d', 'after_or_equal:'.Carbon::now('Asia/Manila')->toDateString()],
            'stay' => ['in:day,month,3,6,12,22,24,48,72,96,120,168,720'],
            'check_in_time' => ['regex:/^(0[6-9]|1[0-9]|2[0-3]):00$/'],
            'guests' => ['integer', 'min:1', 'max:100'],
            'mode' => ['in:dates,hourly'],
        ];
        $selection = [];
        foreach ($rules as $field => $rule) {
            if ($request->filled($field) && Validator::make([$field => $request->input($field)], [$field => $rule])->passes()) {
                $selection[$field] = (string) $request->input($field);
            }
        }

        return $selection;
    }

    public static function hours(array $selection, ?Room $room = null): int
    {
        return match ($selection['stay'] ?? '') {
            'month' => 720,
            'day' => 24,
            '' => $room?->rental_hours ?: 3,
            default => (int) $selection['stay'],
        };
    }
}
