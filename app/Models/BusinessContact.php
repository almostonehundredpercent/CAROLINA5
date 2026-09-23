<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BusinessContact extends Model
{
    protected $fillable = ['phone', 'email', 'address', 'hours', 'facebook_url'];

    public static function current(): ?self
    {
        return Cache::remember('business-contact', now()->addHour(), fn () => static::query()->first());
    }
}
