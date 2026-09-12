<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['booking_id', 'user_id', 'subject_type', 'subject_id', 'event', 'description', 'before_values', 'after_values'];

    protected function casts(): array { return ['before_values' => 'array', 'after_values' => 'array']; }

    public function booking() { return $this->belongsTo(Booking::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function subject() { return $this->morphTo(); }
}
