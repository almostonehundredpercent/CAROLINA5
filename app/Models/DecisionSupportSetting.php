<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DecisionSupportSetting extends Model
{
    protected $fillable = ['low_occupancy_threshold'];

    protected function casts(): array
    {
        return ['low_occupancy_threshold' => 'integer'];
    }
}
