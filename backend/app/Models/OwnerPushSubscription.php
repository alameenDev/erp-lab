<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OwnerPushSubscription extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['subscription', 'endpoint_hash', 'auth_token_id'];
    protected $casts = ['subscription' => 'encrypted:array', 'invoice_enabled' => 'boolean', 'daily_enabled' => 'boolean'];
}
