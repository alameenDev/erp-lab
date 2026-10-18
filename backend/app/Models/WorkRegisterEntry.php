<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkRegisterEntry extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['tests' => 'array', 'groups' => 'array', 'packages' => 'array',
        'price' => 'integer', 'paid' => 'integer', 'position' => 'integer', 'age' => 'integer'];
}
