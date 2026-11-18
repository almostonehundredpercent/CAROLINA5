<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DecisionSupportSetting extends Model
{
    protected $fillable = [
        'low_occupancy_threshold',
        'unpaid_hold_reminders_enabled',
        'unpaid_hold_reminder_dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'low_occupancy_threshold' => 'integer',
            'unpaid_hold_reminders_enabled' => 'boolean',
            'unpaid_hold_reminder_dismissed_at' => 'datetime',
        ];
    }
}
