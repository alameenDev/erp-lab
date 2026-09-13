<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestReferenceRange extends Model
{
    use SoftDeletes;

    protected $table = 'test_reference_range';

    protected $casts = ['selection_type_options' => 'json'];

    protected $fillable = [
        'test_id',
        'gender_id_fk',
        'age_from',
        'age_to',
        'age_unit_id_fk',
        'from',
        'to',
        'selection_type_options',
        'notes',
        'lab_id_fk',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class, 'test_id')->withTrashed();
    }

    public function gender()
    {
        return $this->belongsTo(Gender::class, 'gender_id_fk');
    }

    public function ageUnit()
    {
        return $this->belongsTo(AgeUnit::class, 'age_unit_id_fk');
    }
}
