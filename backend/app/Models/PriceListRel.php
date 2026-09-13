<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceListRel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lab_id_fk',
        'price_list_id_fk',
        'test_id_fk',
        'test_group_id_fk',
        'culture_id_fk',
        'package_id_fk',
        'original_price',
        'price_for_customer',
    ];

    protected $table = 'price_list_rels';

    public function priceList()
    {
        return $this->belongsTo(PriceList::class, 'price_list_id_fk')->withTrashed();
    }

    public function test()
    {
        return $this->belongsTo(Test::class, 'test_id_fk')->withTrashed();
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }

    public function testGroup()
    {
        return $this->belongsTo(TestGroup::class, 'test_group_id_fk')->withTrashed();
    }

    public function culture()
    {
        return $this->belongsTo(Culture::class, 'culture_id_fk')->withTrashed();
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id_fk');
    }
}
