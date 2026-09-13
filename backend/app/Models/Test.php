<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends Model
{
    use SoftDeletes;

    protected $table = 'tests';

    protected $fillable = [
        'test_group_id_fk',
        'lab_id_fk',
        'sample_id_fk',
        'test_duration',
        'duration_unit_id_fk',
        'category_id_fk',
        'name',
        'price',
        'order',
        'shortcut',
        'report_name',
        'interface_code',
        'unit',
        'result_type_id_fk',
        'selection_type_options',
        'is_contain_status',
        'is_print_alone',
        'for_customer_price',
        'result_comments',
        'content',
        'sub_tests',
        'is_special_test',
    ];

    protected $casts = [
        'selection_type_options' => 'json',
        'result_comments' => 'json',
        'content' => 'json',
        'sub_tests' => 'json',
        'is_contain_status' => 'boolean',
        'is_print_alone' => 'boolean',
        'is_special_test' => 'boolean',
    ];

    public function testGroup()
    {
        return $this->belongsTo(TestGroup::class, 'test_group_id_fk')->withTrashed();
    }

    /**
     * All test groups this test belongs to (via pivot).
     */
    public function testGroups()
    {
        return $this->belongsToMany(TestGroup::class, 'test_test_group_rel', 'test_id_fk', 'test_group_id_fk')
            ->withPivot('order')
            ->withTimestamps()
            ->withTrashed();
    }

    public function resultType()
    {
        return $this->belongsTo(ResultType::class, 'result_type_id_fk')->withTrashed();
    }

    public function testReferenceRanges()
    {
        return $this->hasMany(TestReferenceRange::class, 'test_id')->withTrashed();
    }

    public function questions()
    {
        return $this->belongsToMany(PatientQuestion::class, 'test_questions_rel', 'test_id_fk', 'question_id_fk');
    }

    public function sample()
    {
        return $this->belongsTo(Sample::class, 'sample_id_fk')->withTrashed();
    }

    public function durationUnit()
    {
        return $this->belongsTo(DurationUnit::class, 'duration_unit_id_fk')->withTrashed();
    }

    public function price_list_rel()
    {
        return $this->hasMany(PriceListRel::class, 'test_id_fk')->withTrashed();
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id_fk')->withTrashed();
    }

    public function lab()
    {
        return $this->belongsTo(User::class, 'lab_id_fk')->withTrashed();
    }
}
