<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DecisionSupportSeason extends Model
{
    protected $fillable = ['name', 'starts_on', 'ends_on', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
