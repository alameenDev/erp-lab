<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalPushDelivery extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['next_attempt_at' => 'datetime'];
}
