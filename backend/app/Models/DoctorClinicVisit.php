<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorClinicVisit extends Model
{
    protected $fillable = [
        'doctor_user_id_fk','clinic_patient_id_fk','visit_date','appointment_time',
        'queue_number','status','age_at_visit','age_unit_at_visit',
        'chief_complaint','symptoms','symptom_duration','history_of_present_illness',
        'review_of_systems','medical_history','surgical_history','family_history',
        'allergies','current_medications','vitals','physical_examination',
        'assessment','diagnosis_code','plan','lab_requests','imaging_requests',
        'prescriptions','patient_instructions','follow_up_date','completed_at',
    ];

    protected $casts = [
        'visit_date' => 'date:Y-m-d',
        'follow_up_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'symptoms' => 'array',
        'vitals' => 'array',
        'lab_requests' => 'array',
        'imaging_requests' => 'array',
        'prescriptions' => 'array',
        'age_at_visit' => 'integer',
        'queue_number' => 'integer',
    ];

    public function patient()
    {
        return $this->belongsTo(DoctorClinicPatient::class, 'clinic_patient_id_fk');
    }

    public function revisions()
    {
        return $this->hasMany(DoctorClinicVisitRevision::class, 'visit_id_fk');
    }
}
