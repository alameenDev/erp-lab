<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OwnerNotification extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['event_key'];
    protected $casts = ['data' => 'array', 'read_at' => 'datetime', 'expires_at' => 'datetime'];
}
