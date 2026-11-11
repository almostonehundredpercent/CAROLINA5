<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomPriceChangeRequest extends Model
{
    protected $fillable = [
        'room_id', 'requested_by', 'applied_by', 'reference', 'old_price', 'proposed_price',
        'owner_email', 'reason', 'status', 'approval_token_hash', 'approval_expires_at',
        'approved_at', 'rejected_at', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'proposed_price' => 'decimal:2',
            'approval_expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function applier()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
