<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestNote extends Model
{
    protected $fillable = ['guest_key', 'author_id', 'content'];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
