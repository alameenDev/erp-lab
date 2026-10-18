<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkRegisterEntry extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $hidden = ['price', 'paid'];
    protected $casts = ['tests' => 'array', 'groups' => 'array', 'packages' => 'array',
        'work_register_amount' => 'integer', 'position' => 'integer', 'age' => 'integer'];
}
