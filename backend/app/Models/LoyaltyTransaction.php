<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    protected $fillable = [
        'patient_id_fk',
        'lab_id_fk',
        'type',
        'points',
        'description',
        'reference_type',
        'reference_id',
        'expires_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id_fk');
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk');
    }
}
