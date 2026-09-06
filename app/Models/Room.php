<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'slug', 'description', 'image_url', 'room_type', 'beds', 'guests', 'price_per_night', 'amenities', 'is_active', 'operational_status', 'operational_until'];
    protected function casts(): array { return ['amenities' => 'array', 'price_per_night' => 'decimal:2', 'is_active' => 'boolean', 'operational_until' => 'datetime']; }
    public function bookings() { return $this->hasMany(Booking::class); }
}
