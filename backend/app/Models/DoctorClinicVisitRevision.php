<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorClinicVisitRevision extends Model
{
    protected $fillable = ['visit_id_fk', 'doctor_user_id_fk', 'previous_values'];
    protected $casts = ['previous_values' => 'array'];
}
