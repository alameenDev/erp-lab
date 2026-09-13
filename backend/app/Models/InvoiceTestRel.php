<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceTestRel extends Model
{
    protected $table = 'invoice_test_rels';

    protected $fillable = [
        'invoice_id_fk',
        'test_id_fk',
        'culture_id_fk',
        'test_group_id_fk',
        'package_id_fk',
        'to_lab_id_fk',
        'is_sample_received',
        'questions',
        'price',
        'last_result',
        'last_result_data',
        'attribute',
        'result',
        'comment',
        'result_status_id_fk',
        'result_status_text',
        'is_done',
        'package_tests',
        'package_cultures',
        'test_group_tests',
        'test_group_cultures',
        'content',
        'sub_tests',
        'is_special_test',
    ];

    public $timestamps = true;

    protected $casts = [
        'package_tests' => 'json',
        'package_cultures' => 'json',
        'test_group_tests' => 'json',
        'test_group_cultures' => 'json',
        'is_done' => 'boolean',
        'is_sample_received' => 'boolean',
        'is_special_test' => 'boolean',
        'questions' => 'json',
        'last_result_data' => 'json',
        'attribute' => 'json',
        'content' => 'json',
        'sub_tests' => 'json',
    ];

    /**
     * Relationship to the Invoice model
     */
    // public function invoice()
    // {
    //     return $this->belongsTo(Invoice::class, 'invoice_id_fk');
    // }

    /**
     * Relationship to the Test model
     */
    public function test()
    {
        return $this->belongsTo(Test::class, 'test_id_fk')->withTrashed();
    }

    public function testReferenceRange()
    {
        return $this->hasMany(TestReferenceRange::class, 'test_id', 'test_id_fk')->withTrashed();
    }

    /**
     * Relationship to the Culture model
     */
    public function culture()
    {
        return $this->belongsTo(Culture::class, 'culture_id_fk')->withTrashed();
    }

    /**
     * Relationship to the Package model
     */
    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id_fk')->withTrashed();
    }

    /**
     * Relationship to the TestGroup model
     */
    public function testGroup()
    {
        return $this->belongsTo(TestGroup::class, 'test_group_id_fk')->withTrashed();
    }

    /**
     * Relationship to the Package model
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id_fk')->withTrashed();
    }

    /**
     * Relationship to the Lab (destination lab)
     */
    public function toLab()
    {
        return $this->belongsTo(User::class, 'to_lab_id_fk')->withTrashed();
    }

    /**
     * Relationship to the ResultStatus model
     */
    public function resultStatus()
    {
        return $this->belongsTo(ResultStatus::class, 'result_status_id_fk');
    }
}
