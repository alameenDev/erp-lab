<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnswerType extends Model
{
    use SoftDeletes;

    protected $table = 'answer_types';

    protected $fillable = [
        'answer',
    ];

    public function patientQuestions()
    {
        return $this->hasMany(PatientQuestion::class, 'answer_type_id_fk')->withTrashed();
    }
}
