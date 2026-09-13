<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Culture extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'name',
        'lab_id_fk',
        'precautions',
        'result_comments',
        'category_id_fk',
        'price',
        'price_for_customer',
        'test_group_id_fk',
        'sample_id_fk',
        'test_duration',
        'duration_unit_id_fk',
    ];

    protected $casts = [
        'result_comments' => 'json',
    ];

    public function attribute()
    {
        return $this->hasMany(Attribute::class, 'culture_id_fk')->withTrashed();
    }

    public function test_group()
    {
        return $this->belongsTo(TestGroup::class, 'test_group_id_fk')->withTrashed();
    }

    public function sample()
    {
        return $this->belongsTo(Sample::class, 'sample_id_fk')->withTrashed();
    }

    public function duration_unit()
    {
        return $this->belongsTo(DurationUnit::class, 'duration_unit_id_fk')->withTrashed();
    }

    public function price_list_rel()
    {
        return $this->hasMany(PriceListRel::class, 'culture_id_fk')->withTrashed();
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id_fk')->withTrashed();
    }
}
