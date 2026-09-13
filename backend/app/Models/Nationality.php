<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nationality extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'country_name',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class, 'nationality_id_fk');
    }
}
