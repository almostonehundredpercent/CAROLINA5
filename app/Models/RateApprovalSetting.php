<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateApprovalSetting extends Model
{
    protected $fillable = ['recipient_email', 'updated_by'];
}
