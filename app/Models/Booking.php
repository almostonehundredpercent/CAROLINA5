<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = ['user_id', 'guest_name', 'guest_email', 'guest_phone', 'billing_street', 'billing_city', 'billing_province', 'billing_postal_code', 'billing_verified_at', 'room_id', 'reference', 'check_in', 'check_out', 'check_in_at', 'check_out_at', 'booking_type', 'hours', 'guests', 'nights', 'total_amount', 'deposit_amount', 'deposit_status', 'payment_reference', 'payment_proof_data', 'payment_proof_mime', 'deposit_submitted_at', 'deposit_due_at', 'deposit_verified_at', 'deposit_verified_by', 'status', 'payment_method', 'special_request', 'add_ons', 'checked_in_at', 'checked_out_at'];
    protected function casts(): array { return ['check_in' => 'date', 'check_out' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime', 'total_amount' => 'decimal:2', 'deposit_amount' => 'decimal:2', 'add_ons' => 'array', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'billing_verified_at' => 'datetime', 'deposit_submitted_at' => 'datetime', 'deposit_due_at' => 'datetime', 'deposit_verified_at' => 'datetime']; }
    protected static function booted(): void { static::creating(function (Booking $booking) { $booking->reference ??= 'CAR-' . strtoupper(Str::random(8)); }); }
    public function user() { return $this->belongsTo(User::class); }
    public function room() { return $this->belongsTo(Room::class); }
    public function activityLogs() { return $this->hasMany(ActivityLog::class); }
}
