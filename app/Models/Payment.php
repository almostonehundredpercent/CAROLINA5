<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['booking_id', 'amount', 'method', 'status', 'reference', 'notes', 'paid_at', 'recorded_by'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_at' => 'datetime']; }

    public function booking() { return $this->belongsTo(Booking::class); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
}
