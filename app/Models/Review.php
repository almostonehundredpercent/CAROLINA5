<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'room_id', 'user_id', 'guest_name', 'rating',
        'cleanliness_rating', 'comfort_rating', 'value_rating', 'comment',
        'status', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function booking() { return $this->belongsTo(Booking::class); }
    public function room() { return $this->belongsTo(Room::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by'); }
}
