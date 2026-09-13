<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DurationUnit extends Model
{
    use SoftDeletes;

    protected $table = 'duration_units';

    protected $fillable = [
        'unit',
    ];

    public function testGroups()
    {
        return $this->hasMany(TestGroup::class, 'duration_unit_id_fk')->withTrashed();
    }
}
