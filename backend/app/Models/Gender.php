<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gender extends Model
{
    use HasFactory;

    protected $fillable = [
        'gender_type',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class, 'gender_id_fk');
    }
}
