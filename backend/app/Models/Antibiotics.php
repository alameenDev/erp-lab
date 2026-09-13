<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Antibiotics extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'scientific_name',
        'common_name',
        'short_name',
        'lab_id',
    ];

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id');
    }
}
