<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'lab_id_fk',
        'discount',
    ];

    public function priceListRels()
    {
        return $this->hasMany(PriceListRel::class, 'price_list_id_fk')->withTrashed();
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }

    // public function tests()
    // {
    //     return $this->belongsToMany(Test::class, 'test_price_list_rel', 'id', 'test_id_fk')->withTrashed();
    // }

    // public function testGroups()
    // {
    //     return $this->belongsToMany(TestGroup::class, 'test_price_list_rel', 'id', 'test_group_id_fk')->withTrashed();
    // }

    // public function cultures()
    // {
    //     return $this->belongsToMany(Culture::class, 'test_price_list_rel', 'id', 'culture_id_fk')->withTrashed();
    // }

    // public function packages()
    // {
    //     return $this->belongsToMany(Package::class, 'test_price_list_rel', 'id', 'package_id_fk');
    // }
}
