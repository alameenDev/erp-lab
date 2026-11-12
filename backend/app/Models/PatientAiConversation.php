<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientAiConversation extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['scope', 'context_hash'];
    protected $casts = ['scope' => 'encrypted:array', 'consented_at' => 'datetime'];

    public function patient() { return $this->belongsTo(Patient::class)->withTrashed(); }
    public function turns() { return $this->hasMany(PatientAiTurn::class, 'conversation_id'); }
    public function latestTurn() { return $this->hasOne(PatientAiTurn::class, 'conversation_id')->latestOfMany(); }
}
