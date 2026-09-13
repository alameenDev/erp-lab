<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientQuestion extends Model
{
    use SoftDeletes;

    protected $table = 'patient_questions';

    protected $fillable = [
        'question',
        'answer_type_id_fk',
        'answer_type_selection_values',
        'lab_id_fk',
    ];

    protected $casts = [
        'answer_type_selection_values' => 'array',
    ];

    public function answerType()
    {
        return $this->belongsTo(AnswerType::class, 'answer_type_id_fk')->withTrashed();
    }

    public function testQuestionsRel()
    {
        return $this->hasMany(TestQuestionRel::class, 'question_id_fk')->withTrashed();
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk');
    }
}
