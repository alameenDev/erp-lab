<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = ['name', 'content', 'test_id_fk', 'culture_id_fk', 'type', 'package_id_fk', 'test_group_id_fk', 'lab_id_fk'];

    protected $casts = [
        'content' => 'array',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class, 'test_id_fk', 'id')->withTrashed()->select('id', 'name');
    }

    public function culture()
    {
        return $this->belongsTo(Culture::class, 'culture_id_fk', 'id')->withTrashed()->select('id', 'name');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id_fk', 'id')->withTrashed()->select('id', 'name');
    }

    public function test_group()
    {
        return $this->belongsTo(TestGroup::class, 'test_group_id_fk', 'id')->withTrashed()->select('id', 'name');
    }
}
