<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientAiTurn extends Model
{
    protected $guarded = [];
    protected $hidden = ['message', 'answer'];
    protected $casts = ['message' => 'encrypted', 'answer' => 'encrypted:array', 'attempt' => 'integer'];
}
