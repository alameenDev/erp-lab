<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CulturePackageRel extends Model
{
    // Table name (optional if it follows the naming convention)
    protected $table = 'culture_package_rel';

    // Fillable properties for mass assignment
    protected $fillable = [
        'culture_id_fk', 'package_id_fk',
    ];

    // Define relationships
    public function culture()
    {
        return $this->belongsTo(Culture::class, 'culture_id_fk');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id_fk');
    }
}
