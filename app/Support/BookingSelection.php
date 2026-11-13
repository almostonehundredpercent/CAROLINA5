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
            'stay' => ['in:3,6,8,12,22,month,720'],
            'check_in_time' => ['regex:/^(0[6-9]|1[0-9]|2[0-3]):00$/'],
            'guests' => ['integer', 'min:1', 'max:100'],
            'mode' => ['in:hourly'],
            'cooling_type' => ['in:fan,aircon'],
            'sort' => ['in:recommended,price_asc,price_desc'],
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
            '' => 3,
            default => (int) $selection['stay'],
        };
    }
}
