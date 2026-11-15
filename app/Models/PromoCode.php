<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = ['code', 'name', 'description', 'discount_type', 'discount_value', 'minimum_hours', 'maximum_hours', 'starts_at', 'ends_at', 'usage_limit', 'times_used', 'is_active', 'review_status', 'carol_generation_key', 'carol_category', 'carol_notes', 'stay_start_date', 'stay_end_date', 'stay_day_type'];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'minimum_hours' => 'integer',
            'maximum_hours' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'times_used' => 'integer',
            'is_active' => 'boolean',
            'stay_start_date' => 'date',
            'stay_end_date' => 'date',
        ];
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
