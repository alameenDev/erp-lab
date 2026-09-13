<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lab extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'user_id_fk',
        'parent_lab_id_fk',
        'sample_collector_id_fk',
        'discount_percentage',
        'price_list_id_fk',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id_fk')->withTrashed();
    }

    public function parentLab()
    {
        return $this->belongsTo(User::class, 'parent_lab_id_fk')->withTrashed();
    }

    public function sampleCollector()
    {
        return $this->belongsTo(User::class, 'sample_collector_id_fk')->withTrashed();
    }

    public function priceList()
    {
        return $this->belongsTo(PriceList::class, 'price_list_id_fk')->withTrashed();
    }
}
