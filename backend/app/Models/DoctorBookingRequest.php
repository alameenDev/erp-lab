<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorBookingRequest extends Model
{
    protected $fillable = [
        'doctor_id_fk',
        'lab_id_fk',
        'patient_id_fk',
        'patient_name',
        'phone',
        'preferred_date',
        'notes',
        'status',
    ];

    protected $casts = [
        'preferred_date' => 'datetime',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id_fk');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id_fk');
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk');
    }
}
