<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestQuestionRel extends Model
{
    use SoftDeletes;

    protected $table = 'test_questions_rel';

    protected $fillable = [
        'test_id_fk',
        'question_id_fk',
        'lab_id_fk',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class, 'test_id_fk')->withTrashed();
    }

    public function patientQuestion()
    {
        return $this->hasOne(PatientQuestion::class, 'id', 'question_id_fk')->withTrashed();
    }
}
