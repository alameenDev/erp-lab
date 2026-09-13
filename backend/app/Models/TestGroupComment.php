<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestGroupComment extends Model
{
    use SoftDeletes;

    protected $table = 'test_groups_comment';

    protected $fillable = [
        'test_group_id_fk',
        'comment',
        'lab_id_fk',
    ];

    public function testGroup()
    {
        return $this->belongsTo(TestGroup::class, 'test_group_id_fk')->withTrashed();
    }
}
