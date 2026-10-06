<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalPushSubscription extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['subscription', 'endpoint_hash', 'portal_access_token_id'];
    protected $casts = ['subscription' => 'encrypted:array', 'results_enabled' => 'boolean', 'offers_enabled' => 'boolean'];
}
