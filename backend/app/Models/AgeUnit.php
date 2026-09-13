<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgeUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_name',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class, 'age_unit_id_fk');
    }
}
