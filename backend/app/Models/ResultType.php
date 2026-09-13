<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResultType extends Model
{
    use SoftDeletes;

    protected $table = 'result_types';

    protected $fillable = [
        'result_type_name',
    ];

    public function tests()
    {
        return $this->hasMany(Test::class, 'result_type_id_fk')->withTrashed();
    }
}
