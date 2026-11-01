<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorClinicPatient extends Model
{
    protected $fillable = ['doctor_user_id_fk', 'name', 'age_value', 'age_unit', 'phone', 'gender', 'notes'];

    protected $casts = ['age_value' => 'integer'];

    public function visits()
    {
        return $this->hasMany(DoctorClinicVisit::class, 'clinic_patient_id_fk');
    }
}
