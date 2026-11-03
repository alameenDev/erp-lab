<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CollectorPushSubscription extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['subscription','endpoint_hash','auth_token_id'];
    protected $casts = ['subscription'=>'encrypted:array'];
}
