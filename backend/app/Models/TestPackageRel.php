<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestPackageRel extends Model
{
    // Table name (optional if it follows the naming convention)
    protected $table = 'test_package_rel';

    // Fillable properties for mass assignment
    protected $fillable = [
        'test_id_fk', 'package_id_fk',
    ];

    // Define relationships
    public function test()
    {
        return $this->belongsTo(Test::class, 'test_id_fk');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id_fk');
    }
}
