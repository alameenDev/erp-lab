<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id_fk',
        'prescription',
        'required_tests',
        'address',
        'at_home',
        'lng',
        'lat',
        'booking_date',
        'lab_id_fk',
    ];

    protected $casts = [
        'at_home' => 'boolean',
        'booking_date' => 'datetime',
        'required_tests' => 'array',
    ];

    // Relationship with Patient
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id_fk')->withTrashed();
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }

    // Relationship with BookingTestRel
    public function bookingTestRel()
    {
        return $this->hasMany(BookingTestRel::class, 'booking_id_fk');
    }
}
