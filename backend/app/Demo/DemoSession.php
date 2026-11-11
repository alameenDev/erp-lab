<?php
namespace App\Demo;
use Illuminate\Database\Eloquent\Model;
class DemoSession extends Model {
    protected $table = 'learning_demo_sessions';
    protected $guarded = [];
    protected $hidden = ['secret_hash'];
    protected $casts = ['identities' => 'array', 'examples' => 'array', 'expires_at' => 'datetime'];
}
