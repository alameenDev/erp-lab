<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'name',
        'order',
        'result_type_id_fk',
        'culture_id_fk',
        'selection_type_options',
    ];

    protected $casts = [
        'selection_type_options' => 'json',
    ];

    public function resultType()
    {
        return $this->belongsTo(ResultType::class, 'result_type_id_fk')->withTrashed();
    }
}
