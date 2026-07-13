<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = ['user_id', 'room_id', 'reference', 'check_in', 'check_out', 'guests', 'nights', 'total_amount', 'status', 'payment_method', 'special_request'];
    protected function casts(): array { return ['check_in' => 'date', 'check_out' => 'date', 'total_amount' => 'decimal:2']; }
    protected static function booted(): void { static::creating(function (Booking $booking) { $booking->reference ??= 'CAR-' . strtoupper(Str::random(8)); }); }
    public function user() { return $this->belongsTo(User::class); }
    public function room() { return $this->belongsTo(Room::class); }
}
