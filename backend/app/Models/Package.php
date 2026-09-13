<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;

    // Table name (optional if it follows the naming convention)
    protected $table = 'packages';

    protected $casts = [
        'is_constant_price' => 'boolean',
        'formula' => 'array',
    ];

    // Fillable properties for mass assignment
    protected $fillable = [
        'name', 'shortcut', 'price', 'is_constant_price', 'lab_id_fk', 'formula',
    ];

    // Define any relationships here
    // Example: If Package has a relation with another table
    public function tests()
    {
        return $this->belongsToMany(Test::class, 'test_package_rel', 'package_id_fk', 'test_id_fk');
    }

    public function cultures()
    {
        return $this->belongsToMany(Culture::class, 'culture_package_rel', 'package_id_fk', 'culture_id_fk');
    }

    public function testGroups()
    {
        return $this->belongsToMany(TestGroup::class, 'test_group_package_rel', 'package_id_fk', 'test_group_id_fk');
    }

    public function price_list_rel()
    {
        return $this->hasMany(PriceListRel::class, 'package_id_fk')->withTrashed();
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }

    // Add more relationships as needed
}
