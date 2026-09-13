<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestGroup extends Model
{
    use SoftDeletes;

    protected $table = 'test_groups';

    protected $casts = [
        'result_comments' => 'json',
        'is_print_alone' => 'boolean',
        'formula' => 'array',
    ];

    protected $fillable = [
        'lab_id_fk',
        'category_id_fk',
        'group_name',
        'shortcut',
        'original_price',
        'for_customer_price',
        'sample_id_fk',
        'test_duration',
        'duration_unit_id_fk',
        'precautions',
        'formula',
        'is_print_alone',
        'result_comments',
    ];

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id_fk')->withTrashed();
    }

    public function sample()
    {
        return $this->belongsTo(Sample::class, 'sample_id_fk')->withTrashed();
    }

    public function durationUnit()
    {
        return $this->belongsTo(DurationUnit::class, 'duration_unit_id_fk')->withTrashed();
    }

    public function testGroupComments()
    {
        return $this->hasOne(TestGroupComment::class, 'test_group_id_fk')->withTrashed();
    }

    public function price_list_rel()
    {
        return $this->hasMany(PriceListRel::class, 'test_group_id_fk')->withTrashed();
    }

    public function tests()
    {
        return $this->belongsToMany(Test::class, 'test_test_group_rel', 'test_group_id_fk', 'test_id_fk')
            ->withPivot('order')
            ->withTimestamps()
            ->withTrashed()
            ->orderBy('pivot_order');
    }

    public function culture()
    {
        return $this->hasMany(Culture::class, 'test_group_id_fk')->withTrashed();
    }
}
