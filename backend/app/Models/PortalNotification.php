<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PortalNotification extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected $casts = ['read_at' => 'datetime'];
}
