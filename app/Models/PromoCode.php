<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = ['code', 'name', 'description', 'discount_type', 'discount_value', 'minimum_hours', 'maximum_hours', 'starts_at', 'ends_at', 'usage_limit', 'times_used', 'is_active'];

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
