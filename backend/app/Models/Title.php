<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Title extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'title',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class, 'title_id_fk');
    }
}
