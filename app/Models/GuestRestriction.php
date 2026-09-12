<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestRestriction extends Model
{
    protected $fillable = ['guest_key', 'reason', 'created_by', 'removed_at', 'removed_by'];
    protected function casts(): array { return ['removed_at' => 'datetime']; }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function removedBy() { return $this->belongsTo(User::class, 'removed_by'); }
}
