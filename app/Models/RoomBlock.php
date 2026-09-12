<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBlock extends Model
{
    protected $fillable = ['room_id', 'status', 'starts_at', 'ends_at', 'completed_at', 'completed_by', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function room() { return $this->belongsTo(Room::class); }

    public function scopeOverlapping($query, $startsAt, $endsAt)
    {
        return $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }
}
